import { ChangeDetectionStrategy, Component, computed, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { DialogModule } from 'primeng/dialog';
import { ButtonModule } from 'primeng/button';
import { TextareaModule } from 'primeng/textarea';
import type { HttpErrorResponse } from '@angular/common/http';
import type { ApiErrorBody } from '@shared/models/api.models';

/**
 * The shared reason/notes dialog (T3-02): every reject / cancel / resolve
 * action that the API requires a reason for goes through here — no action
 * ever sends hard-coded human text. The page receives the typed text via
 * `submit` and posts it under the field name its endpoint expects.
 */
@Component({
  selector: 'app-reason-dialog',
  standalone: true,
  imports: [TranslatePipe, DialogModule, ButtonModule, FormsModule, TextareaModule],
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
      [style]="{ width: 'min(480px, 94vw)' }"
      [closable]="!sending()"
    >
      <div class="field">
        <label class="field__label" for="reason-dialog-input">{{ label() | translate }} *</label>
        <textarea
          id="reason-dialog-input"
          pTextarea
          rows="3"
          [(ngModel)]="reason"
          name="reason"
          [maxlength]="maxLength()"
          class="u-full"
          (keydown.control.enter)="confirm()"
        ></textarea>
        @if (invalid()) {
          <p class="field__error">{{ 'validation.reason_min' | translate: { min: minLength() } }}</p>
        }
      </div>
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
          <p-button [label]="'common.cancel' | translate" severity="secondary" [text]="true" [disabled]="sending()" (onClick)="visible.set(false)" />
          <p-button [label]="submitLabel() | translate" [loading]="sending()" [disabled]="sending()" (onClick)="confirm()" />
        </div>
      </p-footer>
    </p-dialog>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ReasonDialogComponent {
  header = input.required<string>();
  label = input('common.reason');
  submitLabel = input('common.confirm');
  /** set while the caller's request is in flight */
  sending = input(false);
  /** a failed request — its message is shown inside the dialog */
  error = input<HttpErrorResponse | null>(null);
  minLength = input(3);
  maxLength = input(1000);

  submit = output<string>();

  visible = signal(false);
  reason = signal('');
  private tried = signal(false);

  invalid = computed(() => this.tried() && this.reason().trim().length < this.minLength());

  alert = computed(() => {
    const err = this.error();
    if (!err) {
      return null;
    }
    const body = err.error as ApiErrorBody | null;
    return body?.message ?? null;
  });

  open(): void {
    this.reason.set('');
    this.tried.set(false);
    this.visible.set(true);
  }

  done(): void {
    this.visible.set(false);
  }

  confirm(): void {
    this.tried.set(true);
    const value = this.reason().trim();
    if (value.length < this.minLength()) {
      return;
    }
    this.submit.emit(value);
  }
}
