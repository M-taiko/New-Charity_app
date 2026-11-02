import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';

export interface RibbonStep {
  label: string; // i18n key
  state: 'done' | 'current' | 'todo';
}

/**
 * Flow ribbon (plan 11.4): the custody lifecycle as a thin horizontal path;
 * pending steps use a dashed line (marching only on the pending node, 12.2).
 */
@Component({
  selector: 'app-flow-ribbon',
  standalone: true,
  imports: [TranslatePipe],
  template: `
    <nav class="flow-ribbon" [attr.aria-label]="'custody.detail.flow.title' | translate">
      @for (step of steps(); track step.label; let i = $index; let last = $last) {
        @if (i > 0) {
          <span
            class="flow-ribbon__link"
            [class.flow-ribbon__link--dashed]="steps()[i - 1].state !== 'done'"
            aria-hidden="true"
          ></span>
        }
        <span
          class="flow-ribbon__node"
          [class.flow-ribbon__node--done]="step.state === 'done'"
          [class.flow-ribbon__node--current]="step.state === 'current'"
          [attr.title]="step.label | translate"
          tabindex="0"
          role="img"
        >
          <svg class="flow-ribbon__node-icon" aria-hidden="true">
            <use
              [attr.href]="
                'assets/icons/sprite.svg#tabler-'
                + (step.state === 'done' ? 'check' : step.state === 'current' ? 'clock' : 'lock')
              "
            />
          </svg>
          {{ step.label | translate }}
        </span>
      }
    </nav>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FlowRibbonComponent {
  steps = input.required<RibbonStep[]>();
}
