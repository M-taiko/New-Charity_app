import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

/**
 * Spending-ratio table cell (RD-3.3): slim progress bar (h-2, radius-full,
 * surface-container track, primary fill) + label-sm tabular-nums percentage.
 * Drop inside any ledger-table cell; purely presentational.
 */
@Component({
  selector: 'app-progress-cell',
  standalone: true,
  template: `
    <div class="progress-cell">
      <div
        class="progress-cell__bar"
        role="progressbar"
        [attr.aria-valuemin]="0"
        [attr.aria-valuemax]="100"
        [attr.aria-valuenow]="clamped()"
        [attr.aria-label]="label()"
      >
        <div class="progress-cell__fill" [style.width.%]="clamped()"></div>
      </div>
      @if (showLabel()) {
        <span class="progress-cell__label u-bidi">{{ clamped() }}%</span>
      }
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProgressCellComponent {
  /** ratio in the 0..1 range (or any positive number; values >1 pin to 100) */
  ratio = input.required<number>();
  label = input('');
  showLabel = input(true);

  clamped = computed(() => Math.max(0, Math.min(100, Math.round(this.ratio() * 100))));
}
