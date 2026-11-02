import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { ChangeDetectionStrategy, Component, computed, inject, input, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { firstValueFrom } from 'rxjs';
import { HttpErrorResponse, HttpClient } from '@angular/common/http';
import { ButtonModule } from 'primeng/button';
import { ToastService } from '@core/errors/toast.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { KeyValueListComponent } from '@shared/ui/key-value-list/key-value-list.component';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { ActionResponse, ItemResponse } from '@shared/models/api.models';

/** Expense details (plan 20): summary tiles, grouped info cards, review panel. */
@Component({
  selector: 'app-expense-detail',
  standalone: true,
  imports: [EmptyStateComponent, TranslatePipe, RouterLink, ButtonModule, PageHeaderComponent, KeyValueListComponent, StatusStampComponent, PrintSheetComponent, StatTileComponent, MoneyPipe, DatetimePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (safeValue(); as e) {
      <app-print-sheet [title]="'expense.detail.title' | translate">
        <app-page-header [title]="'#' + e.id + ' — ' + (e.amount | money: false) + ' ج.م'" [subtitle]="e.user ?? ''">
          <div actions class="u-flex u-gap-2 u-flex-wrap">
            <p-button [label]="'common.print' | translate" size="small" [outlined]="true" (click)="printPage()" />
            @if (e.has_attachment) {
              <a class="p-button p-button-sm p-button-outlined p-button-text-only" [href]="downloadUrl()" target="_blank" rel="noopener">
                {{ 'expense.detail.view_attachment' | translate }}
              </a>
            }
            @if (e.can_edit) {
              <p-button [label]="'common.edit' | translate" size="small" [routerLink]="['/expenses', e.id, 'edit']" />
            }
            @if (e.can_review) {
              <p-button [label]="'expense.list.review' | translate" size="small" (onClick)="act('mark-reviewed')" [loading]="sending()" />
            }
            @if (e.can_unreview) {
              <p-button [label]="'expense.list.unreview' | translate" size="small" severity="secondary" [outlined]="true" (onClick)="act('unreview')" [loading]="sending()" />
            }
          </div>
        </app-page-header>

        <!-- summary band: amount / type / date / source -->
        <div class="kpi-grid">
          <app-stat-tile [label]="'expense.detail.amount'" [value]="e.amount" [money]="true" />
          <app-stat-tile [label]="'expense.detail.type'" [value]="typeLabel(e)" />
          <app-stat-tile [label]="'expense.detail.date'" [value]="fmtDate(e.expense_date)" />
          <app-stat-tile [label]="'expense.detail.source_tile'" [value]="sourceLabel(e)" />
        </div>

        <div class="detail-grid u-mt-2">
          <!-- main facts -->
          <section class="chart-card">
            <div class="chart-card__head">
              <span class="chart-card__title">{{ 'expense.detail.facts' | translate }}</span>
              @if (e.is_quick_expense) { <span class="stamp stamp--info">{{ 'expense.list.quick' | translate }}</span> }
            </div>
            <app-key-value-list [items]="facts(e)" />
          </section>

          <!-- review panel -->
          <section class="chart-card">
            <div class="chart-card__head"><span class="chart-card__title">{{ 'expense.detail.review_panel' | translate }}</span></div>
            <div class="detail-grid__stamp">
              <app-status-stamp [status]="e.reviewed ? 'reviewed' : 'not_reviewed'" namespace="review_state" [large]="true" />
            </div>
            <app-key-value-list [items]="reviewFacts(e)" />
          </section>
        </div>

        @if (lineItems().length) {
          <section class="chart-card u-mt-4">
            <div class="chart-card__head"><span class="chart-card__title">{{ 'expense.detail.line_items' | translate }}</span></div>
            <table class="ledger-table ledger-table--borderless">
              <thead>
                <tr>
                  <th>{{ 'expense.detail.item_description' | translate }}</th>
                  <th>{{ 'expense.detail.item_quantity' | translate }}</th>
                  <th>{{ 'expense.detail.item_unit_price' | translate }}</th>
                  <th>{{ 'expense.detail.item_total' | translate }}</th>
                </tr>
              </thead>
              <tbody>
                @for (item of lineItems(); track $index) {
                  <tr class="ledger-table__row">
                    <td>{{ item.description }}</td>
                    <td class="ledger-table__cell-num"><span class="u-bidi">{{ item.quantity }}</span></td>
                    <td class="ledger-table__cell-num">{{ item.unit_price | money: false }}</td>
                    <td class="ledger-table__cell-num">{{ lineTotal(item) | money: false }}</td>
                  </tr>
                }
              </tbody>
              <tfoot>
                <tr class="ledger-table__total">
                  <td colspan="3">{{ 'common.total' | translate }}</td>
                  <td class="ledger-table__cell-num">{{ itemsTotal() | money: false }}</td>
                </tr>
              </tfoot>
            </table>
          </section>
        }

        @if (e.custody_split.length > 1) {
          <section class="chart-card u-mt-4">
            <div class="chart-card__head"><span class="chart-card__title">{{ 'expense.detail.custody_split' | translate }}</span></div>
            <p class="u-small u-muted">{{ 'expense.detail.custody_split_note' | translate }}</p>
            @for (s of e.custody_split; track s.id) {
              <div class="u-flex-between u-small detail-split__row">
                <a [routerLink]="['/custodies', s.id]">#{{ s.id }}</a>
                <span class="u-bidi">{{ s.amount | money }}</span>
              </div>
            }
          </section>
        }
      </app-print-sheet>
    } @else if (data.isLoading()) {
      <div class="skeleton skeleton--title u-mb-4"></div>
      <div class="kpi-grid">@for (s of [1, 2, 3, 4]; track s) {<div class="skeleton skeleton--tile"></div>}</div>
    } @else {
      <app-empty-state [title]="'common.not_found' | translate" [text]="'common.not_found_text' | translate" />

    }
  `,
})
export class ExpenseDetailPage {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  private translate = inject(TranslateService);
  private dateTime = new DatetimePipe();

  id = input.required<number>();
  sending = signal(false);

  data = resource<ExpenseDetail, { id: number }>({
    params: () => ({ id: this.id() }),
    loader: ({ params }) =>
      firstValueFrom(this.http.get<ItemResponse<ExpenseDetail>>(`/expenses/${params.id}`)).then((r) => r.data),
  });

  /** data.value() throws on an errored resource (e.g. 404) — null keeps the template alive */
  readonly safeValue = computed(() => {
    try {
      return this.data.value() ?? null;
    } catch {
      return null;
    }
  });

  lineItems = computed(() => {
    const items = this.data.value()?.line_items;
    return Array.isArray(items) ? (items as LineItem[]) : [];
  });

  lineTotal(item: LineItem): number {
    return (Number(item.quantity) || 0) * (Number(item.unit_price) || 0);
  }

  itemsTotal(): number {
    return this.lineItems().reduce((sum, i) => sum + this.lineTotal(i), 0);
  }

  downloadUrl(): string {
    return `/api/v1/expenses/${this.id()}/download-attachment`;
  }

  fmtDate(value: string | null): string {
    return this.dateTime.transform(value, false);
  }

  /** enums.<ns>.<value> with the raw-code fallback — never blank */
  enumLabel(value: string | null | undefined, namespace: string): string {
    if (!value) return '—';
    const key = `enums.${namespace}.${value}`;
    const label = this.translate.instant(key);
    return label !== key ? label : value;
  }

  typeLabel(e: ExpenseDetail): string {
    return this.enumLabel(e.type, 'expense_type');
  }

  sourceLabel(e: ExpenseDetail): string {
    return this.translate.instant(e.source === 'treasury' ? 'expense.detail.source_treasury' : 'expense.detail.source_custody');
  }

  facts(e: ExpenseDetail): { label: string; value: string }[] {
    return [
      { label: 'expense.detail.direction', value: e.direction ?? '—' },
      { label: 'expense.detail.description', value: e.description ?? '—' },
      { label: 'expense.detail.location', value: e.location ?? '—' },
      { label: 'expense.detail.case', value: e.case ?? '—' },
      ...(e.notes ? [{ label: 'expense.detail.notes', value: e.notes }] : []),
    ];
  }

  reviewFacts(e: ExpenseDetail): { label: string; value: string }[] {
    return [
      {
        label: 'expense.detail.review_state',
        value: this.enumLabel(e.reviewed ? 'reviewed' : 'not_reviewed', 'review_state'),
      },
      { label: 'expense.detail.reviewed_by', value: e.reviewed_by ?? '—' },
      { label: 'expense.detail.reviewed_at', value: e.reviewed_at ? this.dateTime.transform(e.reviewed_at) : '—' },
      { label: 'expense.detail.created_at', value: e.created_at ? this.dateTime.transform(e.created_at) : '—' },
    ];
  }

  act(path: 'mark-reviewed' | 'unreview'): void {
    this.sending.set(true);
    void firstValueFrom(this.http.post<ActionResponse>(`/expenses/${this.id()}/${path}`, {}))
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        this.data.reload();
      })
      .catch((err: HttpErrorResponse) => this.toast.apiError(err.error?.message ?? 'errors.generic'));
  }

  printPage(): void {
    window.print();
  }
}

interface LineItem {
  description: string;
  quantity: number;
  unit_price: number;
}

interface ExpenseDetail {
  id: number; user: string | null; type: string; amount: string; description: string | null;
  created_at: string | null; expense_date: string | null; direction: string | null;
  reviewed: boolean; reviewed_by: string | null; reviewed_at: string | null;
  is_quick_expense: boolean; has_attachment: boolean;
  can_review: boolean; can_unreview: boolean; can_edit: boolean;
  case: string | null; location: string | null; notes: string | null;
  source: string | null; treasury: unknown;
  custody_split: { id: number; amount: string }[];
  line_items: unknown;
}
