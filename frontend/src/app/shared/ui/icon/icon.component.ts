import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { iconHref } from './icon-symbol';

/**
 * Icon from the generated Tabler sprite (plan 11.6). Domain concepts map to
 * icons once — see assets/icons/icon-map.ts and Appendix C of the plan.
 *
 * `chip` (T6-40) turns the icon into a domain accent chip — a tinted rounded
 * square in the module accent color — for stat tiles, page headers, section
 * nav and empty states: chip="custodies|expenses|treasury|cases|hr|tasks|procurement|reports".
 */

/** the eight module accent keys (T6-40) — shared by every accent input (T7-09) */
export type ModuleAccent = 'custodies' | 'expenses' | 'treasury' | 'cases' | 'hr' | 'tasks' | 'procurement' | 'reports';
@Component({
  selector: 'app-icon',
  standalone: true,
  template: `<svg [attr.width]="size()" [attr.height]="size()" aria-hidden="true" focusable="false">
    <use [attr.href]="href()" />
  </svg>`,
  host: { '[class]': 'hostClass()' },
  styles: `
    :host {
      display: inline-flex;
      flex-shrink: 0;
    }
    svg {
      fill: none;
      stroke: currentColor;
      stroke-width: 1.8;
      stroke-linecap: round;
      stroke-linejoin: round;
    }
    /* chip = the module accent as a PLAIN colored glyph — no box, no border,
       no tinted square behind the icon (the icon itself is the visual) */
    :host(.chip-icon) {
      color: var(--chip-fg, var(--color-primary));
    }
    :host(.chip-icon--custodies) {
      --chip-fg: var(--accent-custodies);
    }
    :host(.chip-icon--expenses) {
      --chip-fg: var(--accent-expenses);
    }
    :host(.chip-icon--treasury) {
      --chip-fg: var(--accent-treasury);
    }
    :host(.chip-icon--cases) {
      --chip-fg: var(--accent-cases);
    }
    :host(.chip-icon--hr) {
      --chip-fg: var(--accent-hr);
    }
    :host(.chip-icon--tasks) {
      --chip-fg: var(--accent-tasks);
    }
    :host(.chip-icon--procurement) {
      --chip-fg: var(--accent-procurement);
    }
    :host(.chip-icon--reports) {
      --chip-fg: var(--accent-reports);
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class IconComponent {
  name = input.required<string>();
  size = input(18);
  /** domain accent chip: custodies|expenses|treasury|cases|hr|tasks|procurement|reports */
  chip = input<ModuleAccent | ''>('');

  href = computed(() => iconHref(this.name()));

  hostClass = computed(() => {
    const chip = this.chip();
    if (!chip) {
      return '';
    }
    return this.size() <= 16 ? `chip-icon chip-icon--sm chip-icon--${chip}` : `chip-icon chip-icon--${chip}`;
  });
}
