import { ChangeDetectionStrategy, Component, inject, input } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';

/**
 * Loading placeholders per page shape (T4-20): list / detail / report / form.
 * Rendered by app-async-page while the resource has no value yet.
 */
@Component({
  selector: 'app-page-skeleton',
  standalone: true,
  template: `
    @switch (variant()) {
      @case ('detail') {
        <div class="skeleton skeleton--title" aria-hidden="true"></div>
        <div class="skeleton-card">
          @for (s of [1, 2, 3, 4, 5, 6]; track s) {
            <div class="skeleton skeleton--row"></div>
          }
        </div>
      }
      @case ('form') {
        <div class="skeleton-card">
          <div class="skeleton skeleton--title"></div>
          @for (s of [1, 2, 3, 4, 5, 6]; track s) {
            <div class="skeleton skeleton--row"></div>
          }
        </div>
      }
      @case ('report') {
        <div class="filter-bar" aria-hidden="true">
          <div class="skeleton skeleton--text"></div>
        </div>
        <div class="kpi-grid">
          @for (s of [1, 2, 3, 4]; track s) {
            <div class="skeleton skeleton--tile"></div>
          }
        </div>
        <div class="skeleton-card">
          @for (s of [1, 2, 3, 4, 5]; track s) {
            <div class="skeleton skeleton--row"></div>
          }
        </div>
      }
      @default {
        <div class="skeleton-card">
          @for (s of [1, 2, 3, 4, 5, 6]; track s) {
            <div class="skeleton skeleton--row"></div>
          }
        </div>
      }
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { '[attr.aria-busy]': 'true', '[attr.aria-label]': 'ariaLabel()' },
})
export class PageSkeletonComponent {
  variant = input.required<'list' | 'detail' | 'report' | 'form'>();

  private translate = inject(TranslateService);

  ariaLabel = () => this.translate.instant('common.loading');
}
