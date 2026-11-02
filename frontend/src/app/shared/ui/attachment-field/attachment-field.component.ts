import { ChangeDetectionStrategy, Component, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { ButtonModule } from 'primeng/button';
import { FileUploadModule } from 'primeng/fileupload';

/**
 * Attachment field (plan 11.9, T24): current file with open/replace/remove,
 * type and size validation. Removal in edit requests applies only on approval.
 */
@Component({
  selector: 'app-attachment-field',
  standalone: true,
  imports: [TranslatePipe, ButtonModule, FileUploadModule, FormsModule],
  template: `
    <div class="u-column u-gap-2 u-full">
      @if (current() && !removed()) {
        <div class="attachments__file">
          <svg width="16" height="16" aria-hidden="true">
            <use href="assets/icons/sprite.svg#tabler-paperclip" />
          </svg>
          <span class="attachments__name u-bidi">{{ current() }}</span>
          @if (downloadUrl()) {
            <a [href]="downloadUrl()" target="_blank" rel="noopener">
              {{ 'common.open' | translate }}
            </a>
          }
          @if (canRemove()) {
            <p-button [label]="'common.remove' | translate" size="small" [text]="true" severity="danger" (onClick)="remove.emit()" />
          }
        </div>
      }

      @if (!removed()) {
        <p-fileupload
          mode="basic"
          [customUpload]="true"
          (onSelect)="onSelect($event)"
          [chooseLabel]="current() ? ('common.replace' | translate) : ('common.choose_file' | translate)"
          [auto]="true"
          [maxFileSize]="maxSize()"
        />
        @if (error()) {
          <p class="field__error">{{ error() | translate }}</p>
        }
      }
      @else {
        <p class="field__hint">{{ 'expense.form.attachment_removed' | translate }}</p>
        <p-button [label]="'common.undo' | translate" size="small" [text]="true" (onClick)="removed.set(false)" />
      }
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AttachmentFieldComponent {
  current = input<string | null>(null);
  downloadUrl = input<string | null>(null);
  canRemove = input(true);
  maxSize = input(5_000_000);

  removed = signal(false);
  error = signal('');

  fileChange = output<File | null>();
  remove = output();

  onSelect(event: { files: File[] }): void {
    const file = event.files?.[0];
    if (!file) {
      return;
    }
    if (!['image/jpeg', 'image/png', 'image/webp', 'application/pdf'].includes(file.type)) {
      this.error.set('validation.file_type');
      return;
    }
    this.error.set('');
    this.removed.set(false);
    this.fileChange.emit(file);
  }
}
