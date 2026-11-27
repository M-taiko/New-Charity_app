import { HttpErrorResponse } from '@angular/common/http';
import type { ApiErrorBody } from '@shared/models/api.models';

/**
 * Server 422 field errors for forms (Task4 T4-23): Laravel FormRequest
 * validation returns `{ message, errors: { field: [messages] } }` — this
 * pulls the first message for one field so pages can bind it straight into
 * `<app-field [error]="serverFieldError(error(), 'name')">`.
 *
 * Accepts anything (the page's captured error signal value); returns null
 * unless it is a 422-shaped HttpErrorResponse carrying that field.
 */
export function serverFieldError(error: unknown, field: string): string | null {
  if (!(error instanceof HttpErrorResponse)) {
    return null;
  }
  const body = error.error as ApiErrorBody | null;
  const errors = body?.errors;
  if (!errors) {
    return null;
  }
  const messages = errors[field] ?? errors[`${field}.0`];
  return Array.isArray(messages) && messages.length > 0 ? messages[0] : null;
}

/**
 * What a failed request said (N-06): the server's message and business code,
 * for `toast.apiError(failure.message, failure.code)`. Accepts anything a
 * promise's rejection hands over; the generic key when it said nothing.
 */
export function apiFailure(err: unknown): { message: string; code?: string } {
  const body = (err instanceof HttpErrorResponse ? err.error : null) as ApiErrorBody | null;
  const message = typeof body?.message === 'string' && body.message !== '' ? body.message : 'errors.generic';
  return body?.code ? { message, code: body.code } : { message };
}
