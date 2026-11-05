import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { ToastService } from '@core/errors/toast.service';

/** Success / error / info messages, app-wide (plan 16.2 + T6-40 semantic colors). */
@Component({
  selector: 'app-toast-host',
  standalone: true,
  template: `
    <div class="toast-host" role="status" aria-live="polite">
      @for (toast of svc.toasts(); track toast.id) {
        <div class="toast-host__item toast-host__item--{{ toast.tone }}">
          <svg class="toast-host__icon" width="16" height="16" aria-hidden="true">
            <use [attr.href]="'assets/icons/sprite.svg#tabler-' + iconFor(toast.tone)" />
          </svg>
          <span class="toast-host__text">{{ toast.message }}</span>
          <button type="button" class="topbar__icon-btn toast-host__close" (click)="svc.dismiss(toast.id)" aria-label="×">
            ×
          </button>
        </div>
      }
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ToastHostComponent {
  readonly svc = inject(ToastService);

  iconFor(tone: 'success' | 'error' | 'info'): string {
    if (tone === 'success') {
      return 'check';
    }
    return tone === 'error' ? 'alert-triangle' : 'info';
  }
}
