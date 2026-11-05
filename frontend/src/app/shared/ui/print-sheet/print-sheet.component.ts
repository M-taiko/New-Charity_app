import type { OnDestroy } from '@angular/core';
import { ChangeDetectionStrategy, Component, computed, inject, input, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { TranslatePipe } from '@ngx-translate/core';
import { AuthService } from '@core/auth/auth.service';

/**
 * Print wrapper (plan 16.2 + Task6 T6-70): the organization header appears
 * only in print (logo, name, page title, date, user) plus the applied
 * filters/period summary, a repeated footer, and per-page orientation via CSS
 * named pages — `orientation="landscape"` for tables of 7+ columns
 * (custody movements, governorates, items, researchers, salaries).
 */
@Component({
  selector: 'app-print-sheet',
  standalone: true,
  imports: [TranslatePipe, DatePipe],
  template: `
    <div class="print-sheet" [class.print-landscape]="orientation() === 'landscape'">
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
      @if (filterSummary() !== '') {
        <div class="print-sheet__filters" style="display: none">{{ filterSummary() }}</div>
      }
      <ng-content />
      <div class="print-sheet__footer" style="display: none">
        <span>{{ org()?.name }} — {{ title() | translate }}</span>
        <span>{{ now | date: 'yyyy/MM/dd HH:mm' }} — {{ user()?.name }}</span>
      </div>
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PrintSheetComponent {
  title = input.required<string>();
  /** portrait (default) or landscape for wide tables (T6-70.1) */
  orientation = input<'portrait' | 'landscape'>('portrait');
  /** the applied filters/period as a human line — printed under the org header */
  filterSummary = input('');

  private auth = inject(AuthService);
  readonly now = new Date();

  org() {
    return this.auth.me()?.organization ?? null;
  }

  user() {
    return this.auth.me();
  }
}

/**
 * Converts a Chart.js canvas to a static <img> for printing (T6-70.5):
 * charts re-render at print dimensions and can clip; the flattened image
 * prints exactly as shown. Registered per chart-card instance.
 */
@Component({
  selector: 'app-chart-print-helper',
  standalone: true,
  template: '',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ChartPrintHelperComponent implements OnDestroy {
  canvas = input<HTMLCanvasElement | null>(null);
  private swapped = signal<{ canvas: HTMLCanvasElement; img: HTMLImageElement } | null>(null);

  constructor() {
    window.addEventListener('beforeprint', this.onBeforePrint);
    window.addEventListener('afterprint', this.onAfterPrint);
  }

  private onBeforePrint = (): void => {
    const canvas = this.canvas();
    if (!canvas || this.swapped() !== null) {
      return;
    }
    const img = document.createElement('img');
    img.className = 'chart-canvas-print-img';
    img.src = canvas.toDataURL('image/png');
    img.alt = '';
    canvas.style.display = 'none';
    canvas.after(img);
    this.swapped.set({ canvas, img });
  };

  private onAfterPrint = (): void => {
    const swap = this.swapped();
    if (!swap) {
      return;
    }
    swap.img.remove();
    swap.canvas.style.display = '';
    this.swapped.set(null);
  };

  ngOnDestroy(): void {
    window.removeEventListener('beforeprint', this.onBeforePrint);
    window.removeEventListener('afterprint', this.onAfterPrint);
  }
}
