import { ChangeDetectionStrategy, Component, computed, input, output, signal } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { DialogModule } from 'primeng/dialog';
import { SharedModule } from 'primeng/api';
import { ButtonModule } from 'primeng/button';
import { actionStyle } from '../actions/action-kind';
import type { ActionKind } from '../actions/action-kind';
import { KeyValueListComponent, type KeyValueItem } from '../key-value-list/key-value-list.component';

/**
 * Decision confirm (plan 11.14 + T6-13), one pattern everywhere: the confirm
 * button follows the action-kind table — red for destructive kinds, green for
 * approve kinds. Pass `confirmKind="approve"` for approval confirms.
 *
 * N-06: a kind without a colour of its own (`submit`, `save`, …) gives the
 * dialog's ONE primary button. The decisions added in round 6 use it: in the
 * light theme a filled green or amber confirm reads at 2.54:1 and 2.15:1 —
 * under WCAG AA — while the brand primary is compliant in both themes.
 *
 * N-06: `details` says what is being confirmed — the amount, the other party,
 * the treasury — as label/value lines above the question. Every confirm of a
 * money movement carries its amount here; the page formats the values through
 * the shared pipes.
 */
@Component({
  selector: 'app-confirm-dialog',
  standalone: true,
  imports: [ SharedModule,TranslatePipe, DialogModule, ButtonModule, KeyValueListComponent],
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
      @if (details().length) {
        <div class="confirm-details">
          <app-key-value-list [items]="details()" />
        </div>
      }
      <p>{{ message() | translate: context() }}</p>
      <ng-template pTemplate="footer">
        <div class="u-flex u-justify-end">
          <p-button [label]="'common.cancel' | translate" severity="secondary" [text]="true" (onClick)="visible.set(false)" />
          <p-button [label]="confirmLabel() | translate" [severity]="confirmSeverity()" (onClick)="confirm()" />
        </div>
      </ng-template>
    </p-dialog>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ConfirmDialogComponent {
  header = input('common.confirm');
  message = input.required<string>();
  /** interpolations for the message, e.g. `{ name: '…' }` for "{{ name }}" */
  context = input<Record<string, unknown>>({});
  /** what is being confirmed (N-06) — the amount of a money movement first */
  details = input<readonly KeyValueItem[]>([]);
  confirmLabel = input('common.confirm');
  /** the action being confirmed — decides the confirm button color (T6-13) */
  confirmKind = input<ActionKind>('delete');
  visible = signal(false);
  confirmed = output();

  /** one answer per opening: a second click during the close animation confirms nothing (N-06) */
  private answered = signal(false);

  /** the kind's colour; undefined for a kind with none — PrimeNG's default, the brand primary */
  confirmSeverity = computed(() => actionStyle(this.confirmKind()).severity);

  open(): void {
    this.answered.set(false);
    this.visible.set(true);
  }

  confirm(): void {
    if (!this.visible() || this.answered()) {
      return;
    }
    this.answered.set(true);
    this.confirmed.emit();
    this.visible.set(false);
  }
}
