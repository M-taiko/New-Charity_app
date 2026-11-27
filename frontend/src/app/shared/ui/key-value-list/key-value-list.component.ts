import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';

/** one line: `label` is an i18n key; `value` is what the page shows for it */
export interface KeyValueItem {
  readonly label: string;
  readonly value: string;
}

/** Label/value pairs for detail pages (plan 16.2). */
@Component({
  selector: 'app-key-value-list',
  standalone: true,
  imports: [TranslatePipe],
  template: `
    <dl class="key-value-list">
      @for (item of items(); track $index) {
        <div class="key-value-list__item">
          <dt class="key-value-list__key">{{ item.label | translate }}</dt>
          <dd class="key-value-list__value">{{ item.value }}</dd>
        </div>
      }
    </dl>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class KeyValueListComponent {
  /** value is pre-rendered by the page through the shared pipes (money, dates) */
  items = input.required<readonly KeyValueItem[]>();
}
