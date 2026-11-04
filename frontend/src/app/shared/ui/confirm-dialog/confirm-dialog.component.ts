import { ChangeDetectionStrategy, Component, input, output, signal } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { DialogModule } from 'primeng/dialog';
import { ButtonModule } from 'primeng/button';

/** Destructive-action confirm (plan 11.14), one pattern everywhere. */
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
      [style]="{ width: 'min(440px, 94vw)' }"
    >
      <p>{{ message() | translate: { name: context() } }}</p>
      <p-footer>
        <div class="u-flex u-justify-end">
          <p-button [label]="'common.cancel' | translate" severity="secondary" [text]="true" (onClick)="visible.set(false)" />
          <p-button [label]="confirmLabel() | translate" severity="danger" (onClick)="confirmed.emit(); visible.set(false)" />
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
  visible = signal(false);
  confirmed = output();

  open(): void {
    this.visible.set(true);
  }
}
