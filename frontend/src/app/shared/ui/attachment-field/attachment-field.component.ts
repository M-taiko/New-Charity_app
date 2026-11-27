import { ChangeDetectionStrategy, Component, OnDestroy, computed, inject, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { ButtonModule } from 'primeng/button';
import { FileUploadModule } from 'primeng/fileupload';
import { exceedsLimit, megabytes, shrinkImage } from '@shared/lib/image-shrink';

/** what the server's `mimes:` rule accepts for an expense attachment */
const DEFAULT_TYPES: readonly string[] = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

/** the extension of a file name, in lower case and without the dot ("" when there is none) */
function extension(name: string): string {
  const match = /\.([^.]+)$/.exec(name);
  return match ? match[1].toLowerCase() : '';
}

/**
 * Attachment field (plan 11.9, T24): current file with open/replace/remove, type and size validation.
 * Removal in edit requests applies only on approval.
 *
 * N-05, camera capture:
 * - on phones a second button, "التقاط صورة", opens the camera (the back one)
 *   through a file input with capture="environment". On a wider screen it is
 *   not shown: the field is exactly what it was.
 * - a photo over the limit is shrunk on the client until it fits, instead of
 *   being sent and refused by the server.
 * - the chosen file is shown: a thumbnail for an image, its name and size, and
 *   a working "remove". Neither existed: the file was invisible until upload,
 *   and "remove" only raised an event nobody handled.
 * - the type and size checks are real. The size check used to be PrimeNG's,
 *   whose message the basic mode never shows, so an oversized file reached the
 *   server and came back as a refusal.
 */
@Component({
  selector: 'app-attachment-field',
  standalone: true,
  imports: [TranslatePipe, ButtonModule, FileUploadModule, FormsModule],
  template: `
    <div class="u-column u-gap-2 u-full">
      @if (current() && !removed() && !picked()) {
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
            <p-button [label]="'common.remove' | translate" size="small" [text]="true" severity="danger" (onClick)="removeCurrent()" />
          }
        </div>
      }

      @if (picked(); as file) {
        <!-- N-05: what was chosen, and a way back -->
        <div class="attachments__file attachment-field__preview">
          @if (previewUrl()) {
            <img [src]="previewUrl()" alt="" class="attachment-field__thumb" />
          } @else {
            <svg width="16" height="16" aria-hidden="true">
              <use href="assets/icons/sprite.svg#tabler-paperclip" />
            </svg>
          }
          <span class="attachment-field__meta">
            <span class="attachments__name u-bidi">{{ file.name }}</span>
            <span class="u-small u-muted u-bidi">{{ 'common.file_size_label' | translate: { size: megabytes(file.size) } }}</span>
            @if (shrunk(); as note) {
              <span class="field__hint u-bidi">{{ 'common.file_shrunk' | translate: note }}</span>
            }
          </span>
          <p-button [label]="'common.remove' | translate" size="small" [text]="true" severity="danger" (onClick)="clearPicked()" />
        </div>
      }

      @if (removed()) {
        <p class="field__hint">{{ 'expense.form.attachment_removed' | translate }}</p>
        <p-button [label]="'common.undo' | translate" size="small" [text]="true" (onClick)="removed.set(false)" />
      }

      @if (!removed()) {
        <div class="attachment-field__pick">
          <p-fileupload
            mode="basic"
            [customUpload]="true"
            (onSelect)="onPick($event)"
            [chooseLabel]="current() ? ('common.replace' | translate) : ('common.choose_file' | translate)"
            [auto]="true"
            [accept]="accept()"
          />
          <!-- N-05: phones only — take a photo with the back camera.
               The button is the control; the input it opens stays out of the tab order -->
          <p-button
            class="attachment-field__capture"
            [label]="'common.take_photo' | translate"
            severity="secondary"
            [outlined]="true"
            size="small"
            (onClick)="captureInput.click()"
          />
          <input
            #captureInput
            type="file"
            accept="image/*"
            capture="environment"
            class="u-visually-hidden"
            [tabindex]="-1"
            [attr.aria-label]="'common.take_photo' | translate"
            (change)="onCapture($event)"
          />
        </div>
        <!-- T6-03: one file hint, one place — types, size, replace behavior -->
        <p class="field__hint">{{ hint() | translate }}</p>
        @if (error() !== '') {
          <p class="field__error" role="alert">{{ error() | translate: { types: typesLabel() } }}</p>
        }
      }
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AttachmentFieldComponent implements OnDestroy {
  current = input<string | null>(null);
  downloadUrl = input<string | null>(null);
  canRemove = input(true);
  /** the limit in bytes; the server's rule is `max:2048` kilobytes */
  maxSize = input(2_000_000);
  /**
   * the extensions the server's `mimes:` rule accepts, without dots. The
   * default is the expense rule; the procurement endpoints take no Word
   * document, and their pages say so here instead of letting the picker offer
   * a file the server then refuses.
   */
  types = input<string[]>([...DEFAULT_TYPES]);
  /** i18n key of the line under the buttons */
  hint = input('common.file_hint');

  removed = signal(false);
  error = signal('');
  /** the file that will be uploaded (the shrunk one, for a photo that was over the limit) */
  readonly picked = signal<File | null>(null);
  /** that the chosen file was shrunk, with the before and after sizes */
  readonly shrunk = signal<{ from: string; to: string } | null>(null);

  private translate = inject(TranslateService);

  private preview = signal<string | null>(null);
  readonly previewUrl = this.preview.asReadonly();

  fileChange = output<File | null>();
  remove = output();

  /** the file picker offers what the server accepts */
  readonly accept = computed(() => this.types().map((type) => '.' + type).join(','));

  readonly typesLabel = computed(() => this.types().map((type) => type.toUpperCase()).join(this.translate.instant('common.list_separator')));

  megabytes = megabytes;

  /** the existing file is removed — the page sends remove_attachment */
  removeCurrent(): void {
    this.removed.set(true);
    this.remove.emit();
  }

  /** the newly chosen file is dropped again */
  clearPicked(): void {
    this.setPreview(null);
    this.picked.set(null);
    this.shrunk.set(null);
    this.fileChange.emit(null);
  }

  onPick(event: { files: File[] }): void {
    const file = event.files?.[0];
    if (file) {
      void this.take(file);
    }
  }

  onCapture(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    // the same photo can be taken again: the input must not keep the old choice
    input.value = '';
    if (file) {
      void this.take(file);
    }
  }

  private async take(file: File): Promise<void> {
    this.error.set('');
    if (!this.types().includes(extension(file.name))) {
      this.error.set('validation.attachment_type');
      return;
    }
    let upload: File = file;
    let note: { from: string; to: string } | null = null;
    if (exceedsLimit(file.size, this.maxSize())) {
      if (!file.type.startsWith('image/')) {
        this.error.set('validation.file_size');
        return;
      }
      try {
        const shrunk = await shrinkImage(file, file.name, this.maxSize());
        if (!shrunk.under) {
          this.error.set('validation.file_size');
          return;
        }
        upload = shrunk.file;
        note = { from: megabytes(file.size), to: megabytes(shrunk.file.size) };
      } catch {
        // it says it is an image but the browser cannot decode it
        this.error.set('validation.file_size');
        return;
      }
    }
    this.removed.set(false);
    this.picked.set(upload);
    this.shrunk.set(note);
    this.setPreview(upload.type.startsWith('image/') ? URL.createObjectURL(upload) : null);
    this.fileChange.emit(upload);
  }

  private setPreview(url: string | null): void {
    const old = this.preview();
    if (old) {
      URL.revokeObjectURL(old);
    }
    this.preview.set(url);
  }

  ngOnDestroy(): void {
    this.setPreview(null);
  }
}
