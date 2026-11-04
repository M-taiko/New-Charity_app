import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';

/**
 * The one field wrapper (T4-23): label + required marker + hint + control +
 * error, with for/id wiring and aria-describedby on the error. Pages project
 * the control itself and pass the server 422 message via `error` (resolved
 * text, not a key).
 */
@Component({
  selector: 'app-field',
  standalone: true,
  imports: [TranslatePipe],
  template: `
    <div class="field" [class.field--invalid]="error() !== null">
      @if (label() !== '') {
        <label class="field__label" [attr.for]="controlId() || null">
          {{ label() | translate }}
          @if (required()) {
            <span class="field__required" aria-hidden="true">*</span>
            <span class="u-visually-hidden">({{ 'common.required_field' | translate }})</span>
          }
        </label>
      }
      @if (hint() !== '') {
        <p class="field__hint" [attr.id]="describedId()">
          {{ hint() | translate }}
        </p>
      }
      <ng-content />
      @if (error(); as message) {
        <p class="field__error" role="alert" [attr.id]="errorId()">
          <svg width="14" height="14" aria-hidden="true">
            <use href="assets/icons/sprite.svg#tabler-alert-triangle" />
          </svg>
          <span>{{ message }}</span>
        </p>
      }
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FieldComponent {
  /** i18n key */
  label = input('');
  /** the projected control's id — wires label/for and aria-describedby */
  controlId = input('');
  required = input(false);
  /** i18n key */
  hint = input('');
  /** resolved error text for this field (server validation message), or null */
  error = input<string | null>(null);

  errorId = (): string => (this.controlId() ? `${this.controlId()}-error` : '');
  describedId = (): string => (this.controlId() ? `${this.controlId()}-hint` : '');
}
