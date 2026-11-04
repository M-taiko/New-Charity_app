import { ChangeDetectionStrategy, Component, computed, input, model, output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { DialogModule } from 'primeng/dialog';
import { ButtonModule } from 'primeng/button';
import type { HttpErrorResponse } from '@angular/common/http';
import type { ApiErrorBody } from '../../models/api.models';

/**
 * One pattern for every modal form (plan 11.14): inline field errors stay in
 * the page form; this host shows the general alert, keeps values on error,
 * and disables submit while sending (T25).
 */
@Component({
  selector: 'app-form-dialog',
  standalone: true,
  imports: [TranslatePipe, DialogModule, ButtonModule, FormsModule],
  template: `
    <p-dialog appendTo="body"
      [visible]="visible()"
      (visibleChange)="visible.set($event)"
      [header]="header() | translate"
      [modal]="true"
      [draggable]="false"
      [resizable]="false"
      styleClass="dialog"
      [breakpoints]="{ '576px': '100vw' }"
      [style]="{ width: 'min(560px, 94vw)' }"
      [closable]="!sending()"
    >
      <ng-content />
      @if (alert()) {
        <div class="dialog__alert" role="alert">
          <svg width="16" height="16" aria-hidden="true">
            <use href="assets/icons/sprite.svg#tabler-alert-triangle" />
          </svg>
          <span>{{ alert() }}</span>
        </div>
      }
      <p-footer>
        <div class="u-flex u-gap-2 u-justify-end">
          <p-button
            [label]="'common.cancel' | translate"
            severity="secondary"
            [text]="true"
            (onClick)="cancel()"
            [disabled]="sending()"
          />
          <p-button
            [label]="submitLabel() | translate"
            (onClick)="submit.emit()"
            [loading]="sending()"
            [disabled]="sending()"
          />
        </div>
      </p-footer>
    </p-dialog>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FormDialogComponent {
  header = input.required<string>();
  submitLabel = input('common.save');
  sending = input(false);
  error = input<HttpErrorResponse | null>(null);

  visible = model(false);
  submit = output();
  closed = output();

  alert = computed(() => {
    const err = this.error();
    if (!err) {
      return null;
    }
    const body = err.error as ApiErrorBody | null;
    return body?.message ?? null;
  });

  open(): void {
    this.visible.set(true);
  }

  close(): void {
    this.visible.set(false);
    this.closed.emit();
  }

  cancel(): void {
    this.close();
  }
}
