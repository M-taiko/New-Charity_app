import { ChangeDetectionStrategy, Component, computed, input, model, output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { DialogModule } from 'primeng/dialog';
import { SharedModule } from 'primeng/api';
import { ButtonModule } from 'primeng/button';
import type { HttpErrorResponse } from '@angular/common/http';
import type { ApiErrorBody } from '../../models/api.models';
import { KeyValueListComponent, type KeyValueItem } from '../key-value-list/key-value-list.component';

/**
 * One pattern for every modal form (plan 11.14): inline field errors stay in
 * the page form; this host shows the general alert, keeps values on error,
 * and disables submit while sending (T25).
 *
 * N-06: `details` says what the form decides about, above its fields.
 */
@Component({
  selector: 'app-form-dialog',
  standalone: true,
  imports: [ SharedModule,TranslatePipe, DialogModule, ButtonModule, FormsModule, KeyValueListComponent],
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
      [style]="{ width: 'min(560px, 94vw)' }"
      [closable]="!sending()"
      [dismissableMask]="!sending()"
      [closeOnEscape]="!sending()"
    >
      @if (details().length) {
        <div class="confirm-details">
          <app-key-value-list [items]="details()" />
        </div>
      }
      <ng-content />
      @if (alert()) {
        <div class="dialog__alert" role="alert">
          <svg width="16" height="16" aria-hidden="true">
            <use href="assets/icons/sprite.svg#tabler-alert-triangle" />
          </svg>
          <span>{{ alert() }}</span>
        </div>
      }
      <ng-template pTemplate="footer">
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
            (onClick)="submitNow()"
            [loading]="sending()"
            [disabled]="sending()"
          />
        </div>
      </ng-template>
    </p-dialog>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FormDialogComponent {
  header = input.required<string>();
  submitLabel = input('common.save');
  sending = input(false);
  error = input<HttpErrorResponse | null>(null);
  /** what the form decides about (N-06) — the amount of a money movement first */
  details = input<readonly KeyValueItem[]>([]);

  visible = model(false);
  submit = output();
  closed = output();
  /** fires on ANY hide (mask click, Esc, cancel button) — pages that need to react (T6-16) */
  dismissed = output();

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

  /** N-06: the disabled state is drawn after the handler ran — a click that lands before then submits nothing */
  submitNow(): void {
    if (this.sending()) {
      return;
    }
    this.submit.emit();
  }

  onVisibleChange(next: boolean): void {
    if (!next) {
      this.dismissed.emit();
    }
    this.visible.set(next);
  }

  close(): void {
    this.onVisibleChange(false);
    this.closed.emit();
  }

  cancel(): void {
    this.close();
  }
}
