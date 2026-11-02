import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';

/**
 * Direction trail (plan 11.6): the accounting direction as a chip chain
 * "زكاة ‹ الفقراء ‹ بند" — from the API path string or explicit segments.
 */
@Component({
  selector: 'app-direction-trail',
  standalone: true,
  template: `
    @if (segments().length) {
      <span class="direction-trail" [class.direction-trail--select]="selectable()">
        @for (segment of segments(); track $index; let last = $last) {
          @if (!$first) {
            <span class="direction-trail__sep" aria-hidden="true">‹</span>
          }
          <span
            class="direction-trail__chip"
            [class.direction-trail__chip--last]="last && !selectable()"
            [class.direction-trail__chip--selected]="selectable() && selected() === $index"
            [attr.role]="selectable() ? 'button' : null"
            [attr.tabindex]="selectable() ? 0 : null"
            (click)="selectable() && segmentClick.emit($index)"
            (keydown.enter)="selectable() && segmentClick.emit($index)"
          >
            {{ segment }}
          </span>
        }
      </span>
    } @else {
      <span class="u-muted">—</span>
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DirectionTrailComponent {
  /** Raw path from the API, e.g. "الزكاة ‹ الفقراء ‹ طبية" */
  path = input<string | null>(null);
  segmentsInput = input<string[] | null>(null);
  selectable = input(false);
  selected = input(-1);

  segmentClick = output<number>();

  segments = computed<string[]>(() => {
    const explicit = this.segmentsInput();
    if (explicit) {
      return explicit;
    }
    const raw = this.path();
    if (!raw) {
      return [];
    }
    return raw.split('‹').map((part) => part.trim()).filter((part) => part.length > 0);
  });
}
