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
