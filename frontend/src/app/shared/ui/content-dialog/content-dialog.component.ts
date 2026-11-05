import { ChangeDetectionStrategy, Component, input, model, output } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { DialogModule } from 'primeng/dialog';

/**
 * Shared wrapper for read-only content dialogs (T6-16): transactions pop-ups,
 * attachment previews, report drill-downs. One place owns the dialog behavior —
 * appended to body, not draggable/resizable, closes on outside click and Esc,
 * and emits `dismissed` on any hide so pages can reset their state.
 */
@Component({
  selector: 'app-content-dialog',
  standalone: true,
  imports: [TranslatePipe, DialogModule],
  template: `
    <p-dialog appendTo="body"
      [visible]="visible()"
      (visibleChange)="onVisibleChange($event)"
      [header]="header() | translate"
      [modal]="true"
      [draggable]="false"
      [resizable]="false"
      styleClass="dialog"
      [breakpoints]="{ '576px': '100vw' }"
      [style]="{ width: width() }"
      [dismissableMask]="true"
      [closeOnEscape]="true"
    >
      <ng-content />
    </p-dialog>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ContentDialogComponent {
  /** i18n key for the dialog title */
  header = input.required<string>();
  /** CSS width of the dialog, e.g. 'min(680px, 94vw)' */
  width = input('min(680px, 94vw)');

  visible = model(false);
  /** fires on ANY hide (mask click, Esc, header X) */
  dismissed = output();

  onVisibleChange(next: boolean): void {
    if (!next) {
      this.dismissed.emit();
    }
    this.visible.set(next);
  }
}
