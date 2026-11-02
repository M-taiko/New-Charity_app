import { Injectable, Injector, inject, signal } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';

export interface Toast {
  id: number;
  message: string;
  tone: 'success' | 'error' | 'info';
}

/** App-wide success/error messages (toast-host block, plan 21.1). */
@Injectable({ providedIn: 'root' })
export class ToastService {
  readonly toasts = signal<Toast[]>([]);

  private nextId = 1;

  private injector = inject(Injector);

  // Lazy: constructing TranslateService eagerly would create a DI cycle
  // HttpClient -> interceptor -> ToastService -> TranslateService -> loader -> HttpClient (NG0200)
  private get translate(): TranslateService {
    return this.injector.get(TranslateService);
  }

  success(message: string): void {
    this.push(message, 'success');
  }

  error(message: string): void {
    this.push(message, 'error');
  }

  info(message: string): void {
    this.push(message, 'info');
  }

  /** Shows the translation of an error code if it exists, otherwise the raw message (plan 14.9). */
  apiError(message: string, code?: string): void {
    const key = code ? `errors.codes.${code}` : '';
    const translated = key && this.translate.instant(key);
    this.push(translated !== key && translated ? translated : message, 'error');
  }

  dismiss(id: number): void {
    this.toasts.update((list) => list.filter((t) => t.id !== id));
  }

  private push(message: string, tone: Toast['tone']): void {
    const translated = this.translate.instant(message);
    const text = translated !== message ? translated : message;
    const toast: Toast = { id: this.nextId++, message: text, tone };
    this.toasts.update((list) => [...list.slice(-3), toast]);
    setTimeout(() => { this.dismiss(toast.id); }, toast.tone === 'error' ? 7000 : 3500);
  }
}
