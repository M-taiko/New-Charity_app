import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { EnumLabelPipe } from '@shared/pipes/enum-label.pipe';
import { IconComponent } from '@shared/ui/icon/icon.component';

/**
 * The stamp (plan 10.1.3): meaning carried by icon + fill + border style —
 * never by color. Variants come from the semantic mapping below.
 */
const STAMP_SHAPES: Record<string, { variant: string; icon: string }> = {
  // custody statuses
  pending: { variant: 'pending', icon: 'clock' },
  accepted: { variant: 'info', icon: 'info' },
  active: { variant: 'success', icon: 'success' },
  rejected: { variant: 'danger', icon: 'rejected' },
  cancelled: { variant: 'neutral', icon: 'closed' },
  closed: { variant: 'neutral', icon: 'closed' },
  partially_returned: { variant: 'info', icon: 'return' },
  pending_return: { variant: 'pending', icon: 'hourglass' },
  // transfers / requests
  approved: { variant: 'success', icon: 'success' },
  // review
  reviewed: { variant: 'success', icon: 'success' },
  not_reviewed: { variant: 'neutral', icon: 'clock' },
  // cases
  in_progress: { variant: 'pending', icon: 'clock' },
  resolved: { variant: 'success', icon: 'success' },
  completed: { variant: 'success', icon: 'success' },
  // purchase
  purchased: { variant: 'info', icon: 'purchase' },
  // salary
  draft: { variant: 'neutral', icon: 'edit' },
  recorded: { variant: 'info', icon: 'success' },
  // broadcasts
  active_broadcast: { variant: 'success', icon: 'broadcast' },
  inactive: { variant: 'neutral', icon: 'closed' },
};

@Component({
  selector: 'app-status-stamp',
  standalone: true,
  imports: [TranslatePipe, EnumLabelPipe, IconComponent],
  template: `
    <span class="stamp stamp--{{ shape().variant }}" [class.stamp--lg]="large()" [class.stamp--stamp-in]="stampIn()">
      <svg class="stamp__icon" aria-hidden="true">
        <use [attr.href]="'assets/icons/sprite.svg#tabler-' + shape().icon" />
      </svg>
      <span>{{ status() | enumLabel: namespace() }}</span>
    </span>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class StatusStampComponent {
  status = input.required<string>();
  namespace = input.required<string>();
  large = input(false);
  stampIn = input(false);

  shape = computed(() => STAMP_SHAPES[this.status()] ?? { variant: 'neutral', icon: 'info' });
}
