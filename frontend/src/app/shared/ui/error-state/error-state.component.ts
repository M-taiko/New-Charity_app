import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';
import { ButtonModule } from 'primeng/button';
import { HttpErrorResponse } from '@angular/common/http';
import type { ApiErrorBody } from '@shared/models/api.models';

/**
 * The one failed-load state (T4-20): every page that reads a resource shows
 * this instead of a blank screen — API message/code included, Retry wired to
 * `resource.reload()`, and 403/404 mapped to the standard error copy.
 */
@Component({
  selector: 'app-error-state',
  standalone: true,
  imports: [TranslatePipe, RouterLink, ButtonModule],
  template: `
    <div class="error-state" role="alert">
      <svg class="error-state__icon" aria-hidden="true">
        <use href="assets/icons/sprite.svg#tabler-cloud-off" />
      </svg>
      <p class="error-state__title">{{ titleKey() | translate }}</p>
      <p class="error-state__text">{{ detail() }}</p>
      <div class="u-flex u-gap-2 u-flex-wrap u-mt-2">
        <p-button [label]="'common.retry' | translate" size="small" (onClick)="retry.emit()" />
        @if (backLink(); as link) {
          <p-button
            [label]="backLabel() || ('common.back_to_list' | translate)"
            size="small"
            [text]="true"
            [routerLink]="link"
          />
        }
      </div>
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ErrorStateComponent {
  /** the resource's error() value — HttpErrorResponse, Error or ProgressEvent */
  error = input<unknown>(null);
  /** optional router link shown next to Retry (e.g. the list of the failed detail) */
  backLink = input<readonly unknown[] | null>(null);
  backLabel = input('');
  /** overridden title for non-load errors (i18n key) */
  titleOverride = input('');

  retry = output();

  /**
   * The HttpErrorResponse behind the resource error — `resource()` wraps
   * non-Error rejections (HttpErrorResponse) in an Error with `.cause`, so
   * unwrap one level before checking.
   */
  private readonly response = computed(() => {
    const error = this.error();
    if (error instanceof HttpErrorResponse) {
      return error;
    }
    if (error instanceof Error && (error as { cause?: unknown }).cause instanceof HttpErrorResponse) {
      return (error as { cause: HttpErrorResponse }).cause;
    }
    return null;
  });

  private readonly body = computed<ApiErrorBody | null>(() => this.response()?.error as ApiErrorBody | null);

  titleKey = computed(() => {
    if (this.titleOverride() !== '') {
      return this.titleOverride();
    }
    const status = this.response()?.status ?? 0;
    if (status === 403) {
      return 'errors.403_title';
    }
    if (status === 404) {
      return 'errors.404_title';
    }
    if (status === 0) {
      return 'errors.offline_title';
    }
    return 'errors.load_title';
  });

  /** the server's own message when it sent one; the standard text otherwise */
  detail = computed(() => {
    const message = this.body()?.message;
    if (message && message.trim() !== '') {
      return message;
    }
    const status = this.response()?.status ?? 0;
    if (status === 403) {
      return 'errors.403_text';
    }
    if (status === 404) {
      return 'errors.404_text';
    }
    if (status === 0) {
      return 'errors.load_text';
    }
    return 'errors.load_text';
  });
}
