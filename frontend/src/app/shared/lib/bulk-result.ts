import type { HttpErrorResponse } from '@angular/common/http';

/** N-04: what a bulk endpoint answers for one id */
export interface BulkLine {
  readonly id: number;
  readonly status: 'done' | 'refused';
  readonly code?: string;
  readonly message?: string;
}

/** N-04: the answer of a bulk action — the action contract ({ data, message, level }) with one line per id */
export interface BulkAnswer {
  readonly data: {
    readonly results: readonly BulkLine[];
    readonly done: number;
    readonly refused: number;
    /** the total of the records that were changed, a two-decimal string */
    readonly amount: string;
  };
  readonly message: string;
}

export function isBulkAnswer(body: unknown): body is BulkAnswer {
  // read as unknown throughout: an answer off the wire may be anything
  const data: unknown = (body as { data?: unknown } | null)?.data;
  if (typeof data !== 'object' || data === null) {
    return false;
  }

  return Array.isArray((data as { results?: unknown }).results);
}

/** N-04: what was refused and why, as "#12 (السبب)" lines — '' when nothing was */
export function refusedList(answer: BulkAnswer, separator: string): string {
  return answer.data.results
    .filter((line) => line.status === 'refused')
    .map((line) => `#${line.id} (${line.message ?? line.code ?? ''})`)
    .join(separator);
}

/** N-04: the result toast — the server's message, then the refusals when there are any */
export function bulkToastText(answer: BulkAnswer, separator: string, refusedLabel: string): string {
  const refused = refusedList(answer, separator);

  return refused === '' ? answer.message : `${answer.message} — ${refusedLabel}: ${refused}`;
}

/** N-04: a failed bulk request's message */
export function bulkFailure(err: unknown): { message: string; code?: string } {
  const response = err as Partial<HttpErrorResponse> | null;
  const body = response?.error as { message?: string; code?: string } | null | undefined;
  if (body && typeof body.message === 'string' && body.message !== '') {
    return { message: body.message, code: body.code };
  }

  return { message: 'errors.generic' };
}
