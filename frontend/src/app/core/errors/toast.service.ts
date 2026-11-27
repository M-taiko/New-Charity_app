import { Injectable, Injector, inject, signal } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';

export interface Toast {
  id: number;
  message: string;
  tone: 'success' | 'error' | 'info';
  /** N-06: a change that can still be undone — the toast keeps its button until this runs or the time is up */
  undo?: () => void;
}

/** what `success()` and `info()` take beyond the message */
export interface ToastOptions {
  /** undoes the change when the user clicks "تراجع" within the undo window */
  readonly undo?: () => void;
}

/** how long a toast with an undo button stays on screen (N-06) */
export const UNDO_DURATION_MS = 8000;
const SUCCESS_MS = 3500;
const ERROR_MS = 7000;

/** App-wide success/error messages (toast-host block, plan 21.1). */
@Injectable({ providedIn: 'root' })
export class ToastService {
  readonly toasts = signal<Toast[]>([]);

  private nextId = 1;

  private injector = inject(Injector);

  private timers = new Map<number, ReturnType<typeof setTimeout>>();

  // Lazy: constructing TranslateService eagerly would create a DI cycle
  // HttpClient -> interceptor -> ToastService -> TranslateService -> loader -> HttpClient (NG0200)
  private get translate(): TranslateService {
    return this.injector.get(TranslateService);
  }

  success(message: string, options: ToastOptions = {}): void {
    this.push(message, 'success', options.undo);
  }

  error(message: string): void {
    this.push(message, 'error');
  }

  info(message: string, options: ToastOptions = {}): void {
    this.push(message, 'info', options.undo);
  }

  /** Shows the translation of an error code if it exists, otherwise the raw message (plan 14.9). */
  apiError(message: string, code?: string): void {
    const key = code ? `errors.codes.${code}` : '';
    const translated = key && this.translate.instant(key);
    this.push(translated !== key && translated ? translated : message, 'error');
  }

  dismiss(id: number): void {
    const timer = this.timers.get(id);
    if (timer !== undefined) {
      clearTimeout(timer);
      this.timers.delete(id);
    }
    this.toasts.update((list) => list.filter((t) => t.id !== id));
  }

  /**
   * N-06: run a toast's undo. The toast goes first, so a slow undo cannot be
   * clicked twice; the caller shows whatever comes back.
   */
  undo(id: number): boolean {
    const toast = this.toasts().find((t) => t.id === id);
    if (!toast?.undo) {
      return false;
    }
    this.dismiss(id);
    toast.undo();
    return true;
  }

  /** N-06: the keyboard way in (Ctrl+Z) — the newest toast that still has an undo */
  undoLatest(): boolean {
    const latest = [...this.toasts()].reverse().find((t) => t.undo !== undefined);
    return latest ? this.undo(latest.id) : false;
  }

  private push(message: string, tone: Toast['tone'], undo?: () => void): void {
    const translated = this.translate.instant(message);
    const text = translated !== message ? translated : message;
    const toast: Toast = { id: this.nextId++, message: text, tone, ...(undo ? { undo } : {}) };
    this.toasts.update((list) => [...list.slice(-3), toast]);
    const duration = undo ? UNDO_DURATION_MS : tone === 'error' ? ERROR_MS : SUCCESS_MS;
    this.timers.set(toast.id, setTimeout(() => { this.dismiss(toast.id); }, duration));
  }
}
