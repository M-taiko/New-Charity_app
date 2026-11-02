import { signal } from '@angular/core';

/** True while the browser reports the API unreachable; the shell shows an offline notice. */
export const offline = signal(false);
