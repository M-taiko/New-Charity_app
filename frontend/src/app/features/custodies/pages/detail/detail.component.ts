import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { computed, ChangeDetectionStrategy, Component, inject, input, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputNumberModule } from 'primeng/inputnumber';
import { InputTextModule } from 'primeng/inputtext';
import { ToastService } from '@core/errors/toast.service';
import { AuthService } from '@core/auth/auth.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { KeyValueListComponent } from '@shared/ui/key-value-list/key-value-list.component';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { RibbonStep } from '@shared/ui/flow-ribbon/flow-ribbon.component';
import { FlowRibbonComponent } from '@shared/ui/flow-ribbon/flow-ribbon.component';
import { RingSegment } from '@shared/ui/balance-ring/balance-ring.component';
import { BalanceRingComponent } from '@shared/ui/balance-ring/balance-ring.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { EnumLabelPipe } from '@shared/pipes/enum-label.pipe';
import { ActionResponse, ItemResponse } from '@shared/models/api.models';

interface CustodyExpense {
  id: number; amount: string; type: string; description: string | null;
  date: string | null; reviewed: boolean; direction: string | null;
  split: { id: number; amount: string }[];
}

interface CustodyDetail {
  id: number; agent: string | null; agent_id: number; treasury: string | null;
  accountant: string | null; amount: string; transferred_in: string; transferred_out: string;
  spent: string; returned: string; pending_return: string; frozen: string; remaining: string;
  status: string; status_detail: string; initiated_by: string; notes: string | null;
  created_at: string | null;
  expenses: CustodyExpense[];
  transactions: { id: number; type: string; amount: string; description: string | null; user: string | null; date: string | null }[];
}

/**
 * Custody details (plan 20): flow ribbon, balance ring breakdown (T26 frozen
 * included), actions by role and status. Balances come from the API only.
 */
@Component({
  selector: 'app-custody-detail',
  standalone: true,
  imports: [EmptyStateComponent, TranslatePipe, RouterLink, FormsModule, ButtonModule, InputNumberModule, InputTextModule, PageHeaderComponent, KeyValueListComponent, StatusStampComponent, PrintSheetComponent, FlowRibbonComponent, BalanceRingComponent, MoneyPipe, DatetimePipe, EnumLabelPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (safeValue(); as c) {
      <app-print-sheet [title]="'custody.detail.title' | translate">
        <app-page-header [title]="'#' + c.id" [subtitle]="c.agent ?? ''">
          <div actions class="u-flex u-gap-2 u-flex-wrap">
            <p-button [label]="'common.print' | translate" size="small" [outlined]="true" (click)="printPage()" />
            @if (auth.can('may_approve_custody') && c.status === 'accepted') {
              <p-button [label]="'custody.detail.receive' | translate" size="small" (onClick)="act('receive')" [loading]="sending()" />
            }
            @if ((c.status === 'active' || c.status === 'partially_returned') && c.agent_id === meId() && parseFloat(c.frozen) <= 0) {
              <p-button [label]="'custody.detail.return' | translate" size="small" (onClick)="returnOpen.set(!returnOpen())" [loading]="sending()" />
            }
          </div>
        </app-page-header>

        <div class="u-flex u-gap-3 u-flex-wrap u-mb-4">
          <app-status-stamp [status]="c.status" namespace="custody_status" [large]="true" />
          <span class="u-small u-muted">{{ c.status_detail }}</span>
        </div>

        <app-flow-ribbon [steps]="flowSteps(c)" />

        <div class="kpi-grid u-mt-4">
          <app-balance-ring [segments]="ringSegments(c)" [total]="c.remaining" />
          <div class="chart-card">
            <div class="chart-card__head"><span class="chart-card__title">{{ 'custody.detail.breakdown.title' | translate }}</span></div>
            <app-key-value-list [items]="breakdown(c)" />
          </div>
        </div>

        <app-key-value-list [items]="keyValues(c)" />

        <section class="chart-card u-mt-4">
          <div class="chart-card__head"><span class="chart-card__title">{{ 'custody.detail.transactions' | translate }}</span></div>
          <div style="overflow-x: auto">
            <table class="ledger-table" style="border: 0">
              <thead><tr>
                <th>{{ 'common.date' | translate }}</th>
                <th>{{ 'common.type' | translate }}</th>
                <th>{{ 'common.description' | translate }}</th>
                <th>{{ 'common.amount' | translate }}</th>
              </tr></thead>
              <tbody>
                @for (t of c.transactions; track t.id) {
                  <tr class="ledger-table__row">
                    <td class="u-bidi">{{ t.date | datetime }}</td>
                    <td>{{ t.type | enumLabel: 'transaction_type' }}</td>
                    <td>{{ t.description }}</td>
                    <td class="ledger-table__cell-num">{{ t.amount | money }}</td>
                  </tr>
                } @empty {
                  <tr><td colspan="4" class="u-muted">{{ 'common.no_data' | translate }}</td></tr>
                }
              </tbody>
            </table>
          </div>
        </section>

        <section class="chart-card u-mt-4">
          <div class="chart-card__head"><span class="chart-card__title">{{ 'custody.detail.expenses' | translate }}</span></div>
          <div style="overflow-x: auto">
            <table class="ledger-table" style="border: 0">
              <thead><tr>
                <th>{{ 'common.date' | translate }}</th><th>{{ 'common.description' | translate }}</th>
                <th>{{ 'expense.list.col_direction' | translate }}</th><th>{{ 'common.amount' | translate }}</th>
              </tr></thead>
              <tbody>
                @for (e of c.expenses; track e.id) {
                  <tr class="ledger-table__row">
                    <td class="u-bidi">{{ e.date | datetime: false }}</td>
                    <td><a [routerLink]="['/expenses', e.id]">{{ e.description || '—' }}</a>
                      @if (e.split.length > 1) { <span class="stamp stamp--info">{{ 'expense.detail.custody_split_note' | translate }}</span> }
                    </td>
                    <td class="u-small">{{ e.direction || ('expense.list.filter.no_direction' | translate) }}</td>
                    <td class="ledger-table__cell-num">{{ e.amount | money }}</td>
                  </tr>
                } @empty {
                  <tr><td colspan="4" class="u-muted">{{ 'common.no_data' | translate }}</td></tr>
                }
              </tbody>
            </table>
          </div>
        </section>

        @if (returnOpen()) {
          <section class="form-section u-mt-4">
            <h3 class="form-section__title">{{ 'custody.mine.return_dialog.title' | translate }}</h3>
            <div class="form-section__grid">
              <div class="field">
                <label class="field__label" for="ret-amount">{{ 'custody.detail.return_amount' | translate }} *</label>
                <p-inputnumber inputId="ret-amount" [(ngModel)]="returnAmount" name="returnAmount" mode="decimal"
                               [minFractionDigits]="2" [min]="0.01" [max]="parseFloat(c.remaining)" styleClass="u-full" />
              </div>
              <div class="field">
                <label class="field__label" for="ret-reason">{{ 'custody.detail.return_reason' | translate }} *</label>
                <input pInputText id="ret-reason" [(ngModel)]="reason" name="reason" style="width: 100%" />
              </div>
            </div>
            <div class="u-flex" style="justify-content: flex-end">
              <p-button [label]="'common.cancel' | translate" [text]="true" (onClick)="returnOpen.set(false)" />
              <p-button [label]="'custody.mine.return_to_treasury' | translate" (onClick)="submitReturn(c.id)" [loading]="sending()" />
            </div>
          </section>
        }
      </app-print-sheet>
    } @else if (data.isLoading()) {
      <div class="skeleton skeleton--title u-mb-4"></div>
      <div class="skeleton skeleton--tile"></div>
    } @else {
      <app-empty-state [title]="'common.not_found' | translate" [text]="'common.not_found_text' | translate" />

    }
  `,
})
export class CustodyDetailPage {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  readonly auth = inject(AuthService);

  id = input.required<number>();
  sending = signal(false);
  returnOpen = signal(false);
  returnAmount = signal<number | null>(null);
  reason = signal('');

  meId(): number {
    return this.auth.me()?.id ?? 0;
  }

  parseFloat(v: string): number {
    return parseFloat(v) || 0;
  }

  data = resource<CustodyDetail, { id: number }>({
    params: () => ({ id: this.id() }),
    loader: ({ params }) =>
      firstValueFrom(this.http.get<ItemResponse<CustodyDetail>>(`/custodies/${params.id}`)).then((r) => r.data),
  });

  /** data.value() throws on an errored resource (e.g. 404) — null keeps the template alive */
  readonly safeValue = computed(() => {
    try {
      return this.data.value() ?? null;
    } catch {
      return null;
    }
  });

  flowSteps(c: CustodyDetail): RibbonStep[] {
    const spent = this.parseFloat(c.spent) > 0;
    const transferred = this.parseFloat(c.transferred_out) > 0;
    const returned = this.parseFloat(c.returned) > 0 || c.status === 'pending_return';
    const done = ['active', 'partially_returned', 'pending_return', 'closed'].includes(c.status);
    return [
      { label: 'custody.detail.flow.treasury', state: c.status === 'pending' ? 'current' : 'done' },
      { label: 'custody.detail.flow.custody', state: c.status === 'accepted' ? 'current' : done ? 'done' : 'todo' },
      { label: 'custody.detail.flow.spend', state: spent ? 'done' : done ? 'current' : 'todo' },
      { label: 'custody.detail.flow.transfer', state: transferred ? 'done' : 'todo' },
      { label: 'custody.detail.flow.return', state: c.status === 'closed' ? 'done' : returned ? 'current' : 'todo' },
    ];
  }

  ringSegments(c: CustodyDetail): RingSegment[] {
    return [
      { label: 'custody.detail.breakdown.spent', amount: c.spent },
      { label: 'custody.detail.breakdown.sent_transfer', amount: c.transferred_out },
      { label: 'custody.detail.breakdown.returned', amount: c.returned },
      { label: 'custody.detail.breakdown.pending_return', amount: c.pending_return },
      { label: 'custody.detail.breakdown.frozen', amount: c.frozen },
      { label: 'custody.detail.breakdown.remaining', amount: c.remaining },
    ];
  }

  breakdown(c: CustodyDetail): { label: string; value: string }[] {
    return [
      { label: 'custody.detail.breakdown.amount', value: c.amount },
      { label: 'custody.detail.breakdown.received_transfer', value: c.transferred_in },
      { label: 'custody.detail.breakdown.spent', value: c.spent },
      { label: 'custody.detail.breakdown.sent_transfer', value: c.transferred_out },
      { label: 'custody.detail.breakdown.returned', value: c.returned },
      { label: 'custody.detail.breakdown.pending_return', value: c.pending_return },
      { label: 'custody.detail.breakdown.frozen', value: c.frozen },
      { label: 'custody.detail.breakdown.remaining', value: c.remaining },
    ];
  }

  keyValues(c: CustodyDetail): { label: string; value: string }[] {
    return [
      { label: 'custody.detail.agent', value: c.agent ?? '—' },
      { label: 'glossary.treasury', value: c.treasury ?? '—' },
      { label: 'glossary.accountant', value: c.accountant ?? '—' },
      { label: 'common.notes', value: c.notes ?? '—' },
      { label: 'common.date', value: c.created_at ?? '—' },
    ];
  }

  submitReturn(custodyId: number): void {
    if (this.returnAmount() === null) {
      return;
    }
    this.sending.set(true);
    void firstValueFrom(
      this.http.post<ActionResponse>('/custodies/request-return', {
        custody_id: custodyId,
        amount: this.returnAmount(),
        description: this.reason(),
      }),
    )
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        this.returnOpen.set(false);
        this.data.reload();
      })
      .catch((err: HttpErrorResponse) => {
        this.sending.set(false);
        this.toast.apiError(err.error?.message ?? 'errors.generic');
      });
  }

  act(path: string, body: Record<string, unknown> = {}): void {
    this.sending.set(true);
    void firstValueFrom(this.http.post<ActionResponse>(`/custodies/${this.id()}/${path}`, body))
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        this.data.reload();
      })
      .catch((err: HttpErrorResponse) => {
        this.sending.set(false);
        this.toast.apiError(err.error?.message ?? 'errors.generic', err.error?.code);
      });
  }

  printPage(): void {
    window.print();
  }
}
