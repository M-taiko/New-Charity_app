import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

/**
 * Icon from the generated Tabler sprite (plan 11.6). Domain concepts map to
 * icons once — see assets/icons/icon-map.ts and Appendix C of the plan.
 */
@Component({
  selector: 'app-icon',
  standalone: true,
  template: `<svg [attr.width]="size()" [attr.height]="size()" aria-hidden="true" focusable="false">
    <use [attr.href]="href()" />
  </svg>`,
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
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class IconComponent {
  name = input.required<string>();
  size = input(18);

  href = computed(() => `assets/icons/sprite.svg#tabler-${this.name()}`);
}
