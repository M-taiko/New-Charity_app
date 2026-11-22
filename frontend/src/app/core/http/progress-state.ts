import { signal } from '@angular/core';

/** True while the browser reports the API unreachable; the shell shows an offline notice. */
export const offline = signal(false);

/**
 * In-flight API request count (UX-6.1): drives the slim top progress bar
 * under the topbar. Plain signals — interceptors have no injection context
 * for DI services but module-scope signals work everywhere.
 */
export const pendingRequests = signal(0);

export function trackRequestStart(): void {
  pendingRequests.update((n) => n + 1);
}

export function trackRequestEnd(): void {
  pendingRequests.update((n) => Math.max(0, n - 1));
}
