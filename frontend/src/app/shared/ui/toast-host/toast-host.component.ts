import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { ToastService } from '@core/errors/toast.service';
import { iconHref } from '@shared/ui/icon/icon-symbol';
import { isEditableTarget, isUndoKey, undoShortcutLabel } from '@shared/lib/undo-shortcut';

/** Success / error / info messages, app-wide (plan 16.2 + T6-40 semantic colors). */
@Component({
  selector: 'app-toast-host',
  standalone: true,
  imports: [TranslatePipe],
  host: {
    '(document:keydown)': 'onKeydown($event)',
  },
  template: `
    <div class="toast-host" role="status" aria-live="polite">
      @for (toast of svc.toasts(); track toast.id) {
        <div class="toast-host__item toast-host__item--{{ toast.tone }}">
          <svg class="toast-host__icon" width="16" height="16" aria-hidden="true">
            <use [attr.href]="iconHref(iconFor(toast.tone))" />
          </svg>
          <span class="toast-host__text">{{ toast.message }}</span>
          @if (toast.undo) {
            <button type="button" class="toast-host__undo" (click)="svc.undo(toast.id)">
              {{ 'common.undo' | translate }}
              <kbd class="toast-host__key" aria-hidden="true">{{ shortcut }}</kbd>
            </button>
          }
          <button type="button" class="topbar__icon-btn toast-host__close" (click)="svc.dismiss(toast.id)" [attr.aria-label]="'common.close' | translate">
            <span aria-hidden="true">×</span>
          </button>
        </div>
      }
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ToastHostComponent {
  readonly iconHref = iconHref;

  readonly svc = inject(ToastService);

  /** the shortcut as this platform writes it */
  readonly shortcut = undoShortcutLabel();

  onKeydown(event: KeyboardEvent): void {
    if (isUndoKey(event) && !isEditableTarget(event.target) && this.svc.undoLatest()) {
      event.preventDefault();
    }
  }

  iconFor(tone: 'success' | 'error' | 'info'): string {
    if (tone === 'success') {
      return 'check';
    }
    return tone === 'error' ? 'alert-triangle' : 'info';
  }
}
