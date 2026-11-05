import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

/**
 * Icon from the generated Tabler sprite (plan 11.6). Domain concepts map to
 * icons once — see assets/icons/icon-map.ts and Appendix C of the plan.
 *
 * `chip` (T6-40) turns the icon into a domain accent chip — a tinted rounded
 * square in the module accent color — for stat tiles, page headers, section
 * nav and empty states: chip="custodies|expenses|treasury|cases|hr|tasks|procurement|reports".
 */
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
    :host(.chip-icon) {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border-radius: var(--radius-m);
      background: var(--chip-bg, var(--color-primary-soft));
      color: var(--chip-fg, var(--color-primary));
    }
    :host(.chip-icon--sm) {
      width: 26px;
      height: 26px;
    }
    :host(.chip-icon--custodies) {
      --chip-bg: var(--accent-custodies-soft);
      --chip-fg: var(--accent-custodies);
    }
    :host(.chip-icon--expenses) {
      --chip-bg: var(--accent-expenses-soft);
      --chip-fg: var(--accent-expenses);
    }
    :host(.chip-icon--treasury) {
      --chip-bg: var(--accent-treasury-soft);
      --chip-fg: var(--accent-treasury);
    }
    :host(.chip-icon--cases) {
      --chip-bg: var(--accent-cases-soft);
      --chip-fg: var(--accent-cases);
    }
    :host(.chip-icon--hr) {
      --chip-bg: var(--accent-hr-soft);
      --chip-fg: var(--accent-hr);
    }
    :host(.chip-icon--tasks) {
      --chip-bg: var(--accent-tasks-soft);
      --chip-fg: var(--accent-tasks);
    }
    :host(.chip-icon--procurement) {
      --chip-bg: var(--accent-procurement-soft);
      --chip-fg: var(--accent-procurement);
    }
    :host(.chip-icon--reports) {
      --chip-bg: var(--accent-reports-soft);
      --chip-fg: var(--accent-reports);
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class IconComponent {
  name = input.required<string>();
  size = input(18);
  /** domain accent chip: custodies|expenses|treasury|cases|hr|tasks|procurement|reports */
  chip = input<'' | 'custodies' | 'expenses' | 'treasury' | 'cases' | 'hr' | 'tasks' | 'procurement' | 'reports'>('');

  href = computed(() => `assets/icons/sprite.svg#tabler-${this.name()}`);

  hostClass = computed(() => {
    const chip = this.chip();
    if (!chip) {
      return '';
    }
    return this.size() <= 16 ? `chip-icon chip-icon--sm chip-icon--${chip}` : `chip-icon chip-icon--${chip}`;
  });
}
