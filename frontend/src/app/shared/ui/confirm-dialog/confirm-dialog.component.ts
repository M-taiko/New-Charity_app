import { ChangeDetectionStrategy, Component, computed, input, output, signal } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { DialogModule } from 'primeng/dialog';
import { ButtonModule } from 'primeng/button';
import { actionStyle } from '../actions/action-kind';
import type { ActionKind } from '../actions/action-kind';

/**
 * Decision confirm (plan 11.14 + T6-13), one pattern everywhere: the confirm
 * button follows the action-kind table — red for destructive kinds, green for
 * approve kinds. Pass `confirmKind="approve"` for approval confirms.
 */
@Component({
  selector: 'app-confirm-dialog',
  standalone: true,
  imports: [TranslatePipe, DialogModule, ButtonModule],
  template: `
    <p-dialog appendTo="body"
      [visible]="visible()"
      (visibleChange)="visible.set($event)"
      [header]="header() | translate"
      [modal]="true"
      [draggable]="false"
      [resizable]="false"
      [dismissableMask]="true"
      [closeOnEscape]="true"
      [style]="{ width: 'min(440px, 94vw)' }"
    >
      <p>{{ message() | translate: { name: context() } }}</p>
      <p-footer>
        <div class="u-flex u-justify-end">
          <p-button [label]="'common.cancel' | translate" severity="secondary" [text]="true" (onClick)="visible.set(false)" />
          <p-button [label]="confirmLabel() | translate" [severity]="confirmSeverity()" (onClick)="confirmed.emit(); visible.set(false)" />
        </div>
      </p-footer>
    </p-dialog>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ConfirmDialogComponent {
  header = input('common.confirm');
  message = input.required<string>();
  context = input<Record<string, string>>({});
  confirmLabel = input('common.confirm');
  /** the action being confirmed — decides the confirm button color (T6-13) */
  confirmKind = input<ActionKind>('delete');
  visible = signal(false);
  confirmed = output();

  confirmSeverity = computed(() => actionStyle(this.confirmKind()).severity ?? 'danger');

  open(): void {
    this.visible.set(true);
  }
}
