import { ChangeDetectionStrategy, Component, inject, input } from '@angular/core';
import { DatePipe } from '@angular/common';
import { TranslatePipe } from '@ngx-translate/core';
import { AuthService } from '@core/auth/auth.service';

/**
 * Print wrapper (plan 16.2): the organization header appears only in print
 * (logo, name, page title, date, user). Every printable page wraps in this.
 */
@Component({
  selector: 'app-print-sheet',
  standalone: true,
  imports: [TranslatePipe, DatePipe],
  template: `
    <div class="print-sheet">
      <div class="print-sheet__org" style="display: none">
        @if (org()?.logo) {
          <img [src]="org()?.logo ?? ''" [alt]="org()?.name ?? ''" style="width: 48px; height: 48px" />
        }
        <div>
          <strong>{{ org()?.name }}</strong><br />
          <span>{{ title() | translate }} — {{ now | date: 'yyyy/MM/dd HH:mm' }}</span>
        </div>
        <div style="margin-inline-start: auto">{{ user()?.name }}</div>
      </div>
      <ng-content />
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PrintSheetComponent {
  title = input.required<string>();

  private auth = inject(AuthService);
  readonly now = new Date();

  org() {
    return this.auth.me()?.organization ?? null;
  }

  user() {
    return this.auth.me();
  }
}
