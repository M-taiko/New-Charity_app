import { expect, test } from '@playwright/test';
import { Observable, lastValueFrom, of, throwError } from 'rxjs';
import { isSafeToRepeat, shareWrite, writeKey, type WriteIdentity } from '../src/app/core/http/single-flight';
import { markedAt, unreadBody } from '../src/app/shared/lib/notification-undo';
import { isEditableTarget, isUndoKey, undoShortcutLabel } from '../src/app/shared/lib/undo-shortcut';

/**
 * N-06 — the pure decisions under the feedback and safety work:
 * one request per identical write, which requests may repeat on their own,
 * what an undo of "mark as read" sends, and the undo shortcut.
 */

const REQUEST: WriteIdentity = { method: 'POST', urlWithParams: '/api/v1/expenses/5/mark-reviewed', body: {} };

/** a `send` that counts itself and answers after a tick */
function slowSend(answer: () => Observable<string> = () => of('ok')): { send: () => Observable<string>; calls: () => number } {
  let calls = 0;
  const send = (): Observable<string> => {
    calls++;
    return new Observable<string>((subscriber) => {
      const timer = setTimeout(() => answer().subscribe(subscriber), 10);
      return () => clearTimeout(timer);
    });
  };
  return { send, calls: () => calls };
}

test.describe('N-06 one request per identical write', () => {
  test('two callers of the same write in flight share one request and its answer', async () => {
    const { send, calls } = slowSend();
    const answers = await Promise.all([lastValueFrom(shareWrite(REQUEST, send)), lastValueFrom(shareWrite(REQUEST, send))]);
    expect(answers).toEqual(['ok', 'ok']);
    expect(calls()).toBe(1);
  });

  test('once the request ended, the same write asked for again is a new request', async () => {
    const { send, calls } = slowSend();
    await lastValueFrom(shareWrite(REQUEST, send));
    await lastValueFrom(shareWrite(REQUEST, send));
    await lastValueFrom(shareWrite(REQUEST, send));
    expect(calls()).toBe(3);
  });

  test('a different body, URL or method is a different write', async () => {
    const { send, calls } = slowSend();
    const others: WriteIdentity[] = [
      { ...REQUEST, body: { note: 'b' } },
      { ...REQUEST, urlWithParams: '/api/v1/expenses/6/mark-reviewed' },
      { ...REQUEST, method: 'PUT' },
    ];
    const all = [REQUEST, ...others].map((request) => lastValueFrom(shareWrite(request, send)));
    await Promise.all(all);
    expect(calls()).toBe(4);
  });

  test('after a failure the write is not held: asking again sends again', async () => {
    let fail = true;
    const { send, calls } = slowSend(() => (fail ? throwError(() => new Error('refused')) : of('ok')));
    await lastValueFrom(shareWrite(REQUEST, send)).catch(() => undefined);
    expect(calls()).toBe(1);
    fail = false;
    expect(await lastValueFrom(shareWrite(REQUEST, send))).toBe('ok');
    expect(calls()).toBe(2);
  });

  test('a caller that gives up before the answer does not hold the write either', async () => {
    const { send, calls } = slowSend();
    const subscription = shareWrite(REQUEST, send).subscribe();
    expect(calls()).toBe(1);
    subscription.unsubscribe();
    await new Promise((resolve) => setTimeout(resolve, 30));
    await lastValueFrom(shareWrite(REQUEST, send));
    expect(calls()).toBe(2);
  });

  test('only reads may be sent again on their own', () => {
    expect(isSafeToRepeat('GET')).toBe(true);
    expect(isSafeToRepeat('HEAD')).toBe(true);
    expect(isSafeToRepeat('OPTIONS')).toBe(true);
    expect(isSafeToRepeat('POST')).toBe(false);
    expect(isSafeToRepeat('PUT')).toBe(false);
    expect(isSafeToRepeat('PATCH')).toBe(false);
    expect(isSafeToRepeat('DELETE')).toBe(false);
  });

  test('the key tells writes apart by method, URL and body — and a form apart by its fields', () => {
    expect(writeKey('post', '/a', null)).toBe(writeKey('POST', '/a', undefined));
    expect(writeKey('POST', '/a', { a: 1 })).not.toBe(writeKey('POST', '/a', { a: 2 }));
    expect(writeKey('POST', '/a', {})).not.toBe(writeKey('POST', '/b', {}));

    const form = new FormData();
    form.append('title', 'طلب');
    form.append('attachment', new Blob([new Uint8Array(4)]), 'a.jpg');
    const sameForm = new FormData();
    sameForm.append('title', 'طلب');
    sameForm.append('attachment', new Blob([new Uint8Array(4)]), 'a.jpg');
    const otherFile = new FormData();
    otherFile.append('title', 'طلب');
    otherFile.append('attachment', new Blob([new Uint8Array(5)]), 'b.jpg');
    expect(writeKey('POST', '/a', form)).toBe(writeKey('POST', '/a', sameForm));
    expect(writeKey('POST', '/a', form)).not.toBe(writeKey('POST', '/a', otherFile));
  });
});

test.describe('N-06 undoing a mark-as-read', () => {
  test('the instant the server named is read from the answer', () => {
    expect(markedAt({ data: { marked_at: '2026-11-25T10:00:00+00:00' } })).toBe('2026-11-25T10:00:00+00:00');
    expect(markedAt({ data: null })).toBeNull();
    expect(markedAt({ data: { marked_at: '' } })).toBeNull();
    expect(markedAt({ data: { marked_at: 1764055200 } })).toBeNull();
    expect(markedAt(undefined)).toBeNull();
  });

  test('an undo sends the ids, or the instant — never both', () => {
    expect(unreadBody({ ids: [3, 4] })).toEqual({ ids: [3, 4] });
    expect(unreadBody({ since: '2026-11-25T10:00:00+00:00' })).toEqual({ since: '2026-11-25T10:00:00+00:00' });
  });
});

test.describe('N-06 the undo shortcut', () => {
  const key = (init: Partial<{ key: string; ctrlKey: boolean; metaKey: boolean; shiftKey: boolean; altKey: boolean }>) => ({ key: 'z', ctrlKey: false, metaKey: false, shiftKey: false, altKey: false, ...init });

  test('Ctrl+Z or Cmd+Z undoes; with Shift, Alt, or another key, it does not', () => {
    expect(isUndoKey(key({ ctrlKey: true }))).toBe(true);
    expect(isUndoKey(key({ metaKey: true }))).toBe(true);
    expect(isUndoKey(key({ ctrlKey: true, key: 'Z' }))).toBe(true);
    expect(isUndoKey(key({ ctrlKey: true, shiftKey: true }))).toBe(false);
    expect(isUndoKey(key({ ctrlKey: true, altKey: true }))).toBe(false);
    expect(isUndoKey(key({ ctrlKey: true, key: 'y' }))).toBe(false);
    expect(isUndoKey(key({}))).toBe(false);
  });

  test('inside a field the shortcut belongs to the field', () => {
    const field = (tagName: string, type?: string) => ({ closest: () => ({ tagName, type }) });
    expect(isEditableTarget({ isContentEditable: true })).toBe(true);
    expect(isEditableTarget(field('TEXTAREA'))).toBe(true);
    expect(isEditableTarget(field('SELECT'))).toBe(true);
    expect(isEditableTarget(field('INPUT', 'search'))).toBe(true);
    // a checkbox or a button holds no text to undo
    expect(isEditableTarget(field('INPUT', 'checkbox'))).toBe(false);
    expect(isEditableTarget(field('INPUT', 'submit'))).toBe(false);
    expect(isEditableTarget({ closest: () => null })).toBe(false);
    expect(isEditableTarget(null)).toBe(false);
    expect(isEditableTarget('body')).toBe(false);
  });

  test('the shortcut is written the way the platform does', () => {
    expect(undoShortcutLabel(undefined)).toBe('Ctrl+Z');
    expect(undoShortcutLabel({ userAgentData: { platform: 'macOS' }, userAgent: 'Mozilla/5.0 (Macintosh)' })).toBe('⌘Z');
    expect(undoShortcutLabel({ userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X)' })).toBe('⌘Z');
    expect(undoShortcutLabel({ userAgentData: { platform: 'Windows' }, userAgent: 'Mozilla/5.0 (Windows NT 10.0)' })).toBe('Ctrl+Z');
  });
});
