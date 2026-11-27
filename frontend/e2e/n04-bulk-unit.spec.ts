import { expect, test } from '@playwright/test';
import { bulkFailure, bulkToastText, isBulkAnswer, refusedList, type BulkAnswer } from '../src/app/shared/lib/bulk-result';
import { fromPiasters, sumDecimals, toPiasters } from '../src/app/shared/lib/money-sum';

/**
 * N-04 — the pure part of the bulk actions: the total of the selected
 * amounts (exactly — never through a float) and the result toast built from
 * the server's per-id answer.
 */

test.describe('N-04 the total of what was selected', () => {
  test('amounts are added exactly, where floats would drift', () => {
    expect(0.1 + 0.2).not.toBe(0.3);
    expect(sumDecimals(['0.10', '0.20'])).toBe('0.30');
    expect(sumDecimals(['39.00', '20.00'])).toBe('59.00');
    expect(sumDecimals(['1.99', '2.01'])).toBe('4.00');
    expect(sumDecimals(['0.01', '0.02', '0.03'])).toBe('0.06');
  });

  test('whole piasters, carried and borrowed', () => {
    expect(toPiasters('59.00')).toBe(5900);
    expect(toPiasters('1.05')).toBe(105);
    expect(toPiasters('0.5')).toBe(50);
    expect(toPiasters('123456789.99')).toBe(12345678999);
    expect(toPiasters('-12.34')).toBe(-1234);
    expect(toPiasters(7)).toBe(700);
    expect(toPiasters('0.005')).toBe(0);
    expect(fromPiasters(5900)).toBe('59.00');
    expect(fromPiasters(105)).toBe('1.05');
    expect(fromPiasters(4)).toBe('0.04');
    expect(fromPiasters(-1234)).toBe('-12.34');
    expect(sumDecimals(['-10.00', '4.50'])).toBe('-5.50');
  });

  test('what is not an amount counts as nothing, and nothing sums to zero', () => {
    expect(sumDecimals([])).toBe('0.00');
    expect(sumDecimals([null, undefined, '', 'abc', '1.2.3'])).toBe('0.00');
    expect(toPiasters(null)).toBe(0);
    expect(toPiasters(' 12.30 ')).toBe(1230);
  });
});

const ANSWER: BulkAnswer = {
  data: {
    results: [
      { id: 12, status: 'done' },
      { id: 13, status: 'done' },
      { id: 14, status: 'refused', code: 'expense.already_reviewed', message: 'المصروف مراجَع أصلاً' },
      { id: 999, status: 'refused', code: 'expense.not_found' },
    ],
    done: 2,
    refused: 2,
    amount: '59.00',
  },
  message: 'تمت مراجعة 2',
};

test.describe('N-04 the result of a bulk action', () => {
  test('an answer is recognised by its per-id lines', () => {
    expect(isBulkAnswer(ANSWER)).toBe(true);
    expect(isBulkAnswer(null)).toBe(false);
    expect(isBulkAnswer({ message: 'ok' })).toBe(false);
    expect(isBulkAnswer({ data: null })).toBe(false);
    expect(isBulkAnswer({ data: { results: 'no' } })).toBe(false);
  });

  test('what was refused and why, one line an id — the done ones are not listed', () => {
    expect(refusedList(ANSWER, '، ')).toBe('#14 (المصروف مراجَع أصلاً)، #999 (expense.not_found)');
    expect(refusedList({ ...ANSWER, data: { ...ANSWER.data, results: ANSWER.data.results.slice(0, 2) } }, '، ')).toBe('');
  });

  test('the toast: the message alone when all went through, the refusals added when some did not', () => {
    const allDone: BulkAnswer = { ...ANSWER, data: { ...ANSWER.data, results: ANSWER.data.results.slice(0, 2), refused: 0 } };
    expect(bulkToastText(allDone, '، ', 'لم يُنفَّذ')).toBe('تمت مراجعة 2');
    expect(bulkToastText(ANSWER, '، ', 'لم يُنفَّذ')).toBe('تمت مراجعة 2 — لم يُنفَّذ: #14 (المصروف مراجَع أصلاً)، #999 (expense.not_found)');
  });

  test('a failed request gives the server message, or the generic one', () => {
    expect(bulkFailure({ error: { message: 'ليس لديك صلاحية لهذا الإجراء', code: 'bulk.forbidden' } })).toEqual({ message: 'ليس لديك صلاحية لهذا الإجراء', code: 'bulk.forbidden' });
    expect(bulkFailure({ error: { message: '' } })).toEqual({ message: 'errors.generic' });
    expect(bulkFailure(null)).toEqual({ message: 'errors.generic' });
    expect(bulkFailure(new Error('socket hang up'))).toEqual({ message: 'errors.generic' });
  });
});
