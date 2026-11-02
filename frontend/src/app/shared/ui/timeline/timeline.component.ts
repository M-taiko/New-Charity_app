import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { RelativeTimePipe } from '@shared/pipes/relative-time.pipe';

export interface TimelineEvent {
  title: string;
  at: string | null;
  body?: string;
  muted?: boolean;
}

/** Ledger-style vertical event list (plan 11.11). */
@Component({
  selector: 'app-timeline',
  standalone: true,
  imports: [TranslatePipe, DatetimePipe, RelativeTimePipe],
  template: `
    <ul class="timeline">
      @for (event of events(); track $index) {
        <li class="timeline__item" [class.timeline__item--muted]="event.muted">
          <div class="timeline__head">
            <span class="timeline__title">{{ event.title }}</span>
            @if (event.at) {
              <span class="timeline__time"> {{ event.at | datetime }} · {{ event.at | relativeTime }} </span>
            }
          </div>
          @if (event.body) {
            <p class="timeline__body">{{ event.body }}</p>
          }
        </li>
      }
    </ul>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TimelineComponent {
  events = input.required<TimelineEvent[]>();
}
