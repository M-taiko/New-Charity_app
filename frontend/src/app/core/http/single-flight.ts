import type { HttpInterceptorFn, HttpRequest } from '@angular/common/http';
import { defer, finalize, share, type Observable } from 'rxjs';

/** requests the server may safely receive twice: nothing changes on its side */
const SAFE_TO_REPEAT = new Set(['GET', 'HEAD', 'OPTIONS']);

/** may this request be sent again on its own (a retry, a second subscription)? */
export function isSafeToRepeat(method: string): boolean {
  return SAFE_TO_REPEAT.has(method.toUpperCase());
}

/** what makes two writes the same request: the method, the URL and the body */
export function writeKey(method: string, url: string, body: unknown): string {
  return `${method.toUpperCase()} ${url} ${bodyKey(body)}`;
}

function bodyKey(body: unknown): string {
  if (body === null || body === undefined) {
    return '';
  }
  // FormData and Blob exist only in a browser — the shapes are read structurally
  if (typeof FormData !== 'undefined' && body instanceof FormData) {
    const parts: string[] = [];
    body.forEach((value, name) => {
      parts.push(`${name}=${typeof value === 'string' ? value : `file:${value.name}:${value.size}:${value.type}`}`);
    });
    return `form:${parts.join('&')}`;
  }
  if (typeof Blob !== 'undefined' && body instanceof Blob) {
    return `blob:${body.size}:${body.type}`;
  }
  if (body instanceof ArrayBuffer) {
    return `bytes:${body.byteLength}`;
  }
  if (typeof body === 'object') {
    try {
      return JSON.stringify(body);
    } catch {
      // a circular reference cannot be serialised — one key for all of those
      return 'unserializable';
    }
  }
  return typeof body === 'string' ? body : 'other';
}

/** the part of the request `writeKey` reads */
export interface WriteIdentity {
  readonly method: string;
  readonly urlWithParams: string;
  readonly body: unknown;
}

/** one in-flight request per identical write */
const inFlight = new Map<string, Observable<unknown>>();

/**
 * N-06: one request at a time per identical write. A double click — or a
 * handler reached twice before the button's disabled state is drawn — would
 * send the same POST twice and create the record twice. Callers that ask for
 * the same write while it is in flight all get the one answer the server gave.
 *
 * Reads are never held back, and nothing is stored once the request ends:
 * the same write asked for later is a new request, as it should be.
 */
export function shareWrite<T>(req: WriteIdentity, send: () => Observable<T>): Observable<T> {
  const key = writeKey(req.method, req.urlWithParams, req.body);
  const running = inFlight.get(key) as Observable<T> | undefined;
  if (running) {
    return running;
  }
  const shared = defer(send).pipe(
    finalize(() => { inFlight.delete(key); }),
    share({ resetOnComplete: true, resetOnError: true }),
  );
  inFlight.set(key, shared);
  return shared;
}

/**
 * Placed after the URL interceptor (the key holds the full URL) and before the
 * error interceptor, so its 419 re-send is one request from here.
 */
export const singleFlightInterceptor: HttpInterceptorFn = (req: HttpRequest<unknown>, next) =>
  isSafeToRepeat(req.method) ? next(req) : shareWrite(req, () => next(req));
