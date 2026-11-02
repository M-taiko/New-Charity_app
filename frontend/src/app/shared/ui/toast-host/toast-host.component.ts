import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { ToastService } from '@core/errors/toast.service';

/** Success and error messages, app-wide (plan 16.2). */
@Component({
  selector: 'app-toast-host',
  standalone: true,
  template: `
    <div class="toast-host" role="status" aria-live="polite">
      @for (toast of svc.toasts(); track toast.id) {
        <div class="toast-host__item" [class.toast-host__item--error]="toast.tone === 'error'">
          {{ toast.message }}
          <button type="button" class="topbar__icon-btn" style="margin-inline-start: auto" (click)="svc.dismiss(toast.id)" aria-label="×">
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
}
