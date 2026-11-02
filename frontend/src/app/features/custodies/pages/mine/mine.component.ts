import { ChangeDetectionStrategy, Component, computed, inject, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputNumberModule } from 'primeng/inputnumber';
import { InputTextModule } from 'primeng/inputtext';
import { TextareaModule } from 'primeng/textarea';
import { SelectModule } from 'primeng/select';
import { ToastService } from '@core/errors/toast.service';
import { AuthService } from '@core/auth/auth.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { ActionResponse, ItemResponse } from '@shared/models/api.models';

interface MyData {
  stats: Record<string, string | number>;
  custodies: { id: number; agent: string | null; amount: string; spent: string; returned: string; frozen: string; remaining: string; status: string; status_detail: string; treasury: string | null; created_at: string | null }[];
  sent_transfers: { id: number; receiver: string | null; amount: string; status: string; created_at: string | null }[];
  received_transfers: { id: number; sender: string | null; amount: string; status: string; created_at: string | null }[];
}

/**
 * My custodies (plan 20, round 3 + T28 rev2): open to everyone; the external
 * receipt button appears only when the user owns NO custody and is not a
 * supervisor; frozen shows only when > 0 (T26).
 */
@Component({
  selector: 'app-my-custodies',
  standalone: true,
  imports: [TranslatePipe, RouterLink, FormsModule, ButtonModule, InputNumberModule, InputTextModule, TextareaModule, SelectModule, PageHeaderComponent, StatTileComponent, FormDialogComponent, StatusStampComponent, MoneyPipe, DatetimePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (data.value(); as d) {
      <app-page-header [title]="'custody.mine.title' | translate">
        <div actions class="u-flex u-gap-2 u-flex-wrap">
          @if (showExternalReceipt()) {
            <p-button [label]="'custody.mine.external_receipt' | translate" size="small" (onClick)="externalDialog.open()" />
          }
          <p-button [label]="'custody.mine.request_new' | translate" size="small" routerLink="/custodies/new" />
        </div>
      </app-page-header>

      <div class="kpi-grid">
        <app-stat-tile [label]="'custody.mine.remaining'" [value]="stat('total_remaining')" [money]="true" />
        <app-stat-tile [label]="'custody.mine.amount'" [value]="stat('total_amount')" [money]="true" />
        <app-stat-tile [label]="'custody.mine.spent'" [value]="stat('total_spent')" [money]="true" />
        <app-stat-tile [label]="'custody.mine.total'" [value]="stat('total_custodies')" />
        @if (parseFloatStat('frozen') > 0) {
          <app-stat-tile [label]="'dashboard.agent.frozen'" [value]="stat('frozen')" [money]="true" [inset]="true" />
        }
      </div>

      <div class="ledger-table">
        <div class="ledger-table__header"><span class="ledger-table__title">{{ 'custody.mine.title' | translate }}</span></div>
        <table>
          <thead><tr>
            <th>{{ 'common.date' | translate }}</th><th>{{ 'custody.list.col_amount' | translate }}</th>
            <th>{{ 'custody.list.col_transferred_in' | translate }}</th><th>{{ 'custody.list.col_spent' | translate }}</th>
            <th>{{ 'custody.list.col_returned' | translate }}</th><th>{{ 'custody.list.col_remaining' | translate }}</th>
            <th>{{ 'common.status' | translate }}</th><th>{{ 'common.actions' | translate }}</th>
          </tr></thead>
          <tbody>
            @for (c of d.custodies; track c.id) {
              <tr class="ledger-table__row">
                <td class="u-bidi">{{ c.created_at | datetime: false }}</td>
                <td class="ledger-table__cell-num">{{ c.amount | money }}</td>
                <td class="ledger-table__cell-num">—</td>
                <td class="ledger-table__cell-num">{{ c.spent | money }}</td>
                <td class="ledger-table__cell-num">{{ c.returned | money }}</td>
                <td class="ledger-table__cell-num">{{ c.remaining | money }}</td>
                <td><app-status-stamp [status]="c.status" namespace="custody_status" /></td>
                <td><a [routerLink]="['/custodies', c.id]">{{ 'common.view' | translate }}</a></td>
              </tr>
            } @empty {
              <tr><td colspan="8" class="u-muted">{{ 'custody.mine.empty' | translate }}</td></tr>
            }
          </tbody>
        </table>
      </div>

      <div class="kpi-grid u-mt-4">
        <section class="chart-card">
          <div class="chart-card__head"><span class="chart-card__title">{{ 'custody.mine.sent_transfers' | translate }}</span></div>
          @for (t of d.sent_transfers; track t.id) {
            <div class="u-flex-between u-small" style="padding-block: 4px">
              <span>{{ 'glossary.receiver' | translate }}: {{ t.receiver }}</span>
              <span class="u-bidi">{{ t.amount | money }}</span>
              <app-status-stamp [status]="t.status" namespace="transfer_status" />
            </div>
          } @empty { <p class="u-muted u-small">{{ 'common.no_data' | translate }}</p> }
        </section>
        <section class="chart-card">
          <div class="chart-card__head"><span class="chart-card__title">{{ 'custody.mine.received_transfers' | translate }}</span></div>
          @for (t of d.received_transfers; track t.id) {
            <div class="u-flex-between u-small" style="padding-block: 4px">
              <span>{{ 'glossary.sender' | translate }}: {{ t.sender }}</span>
              <span class="u-bidi">{{ t.amount | money }}</span>
              <app-status-stamp [status]="t.status" namespace="transfer_status" />
            </div>
          } @empty { <p class="u-muted u-small">{{ 'common.no_data' | translate }}</p> }
        </section>
      </div>
    }

    <app-form-dialog #externalDialog [header]="'custody.mine.external_receipt_dialog.title' | translate"
                     [submitLabel]="'common.save' | translate" [sending]="sending()" (submit)="submitExternal()">
      <div class="u-column u-gap-3">
        <div class="field">
          <label class="field__label" for="ext-amount">{{ 'custody.mine.external_receipt_dialog.amount' | translate }} *</label>
          <p-inputnumber inputId="ext-amount" [(ngModel)]="extAmount" name="extAmount" mode="decimal" [minFractionDigits]="2" [min]="0.01" styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label" for="ext-source">{{ 'custody.mine.external_receipt_dialog.source' | translate }} *</label>
          <input pInputText id="ext-source" [(ngModel)]="extSource" name="extSource" style="width: 100%" />
        </div>
        <div class="field">
          <label class="field__label" for="ext-desc">{{ 'common.description' | translate }} *</label>
          <textarea pTextarea id="ext-desc" rows="2" [(ngModel)]="extDescription" name="extDescription" style="width: 100%"></textarea>
        </div>
        <p class="field__hint">{{ 'custody.mine.external_receipt_dialog.hint' | translate }}</p>
      </div>
    </app-form-dialog>
  `,
})
export class MyCustodiesPage {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  private auth = inject(AuthService);

  sending = signal(false);
  extAmount = signal<number | null>(null);
  extSource = signal('');
  extDescription = signal('');

  data = resource<MyData, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<MyData>>('/custodies/mine')).then((r) => r.data),
  });

  showExternalReceipt = computed(() => {
    const owns = this.auth.can('owns_custody');
    const supervisor = this.auth.can('is_supervisor');
    return !supervisor && !owns;
  });

  stat(key: string): string {
    return String(this.data.value()?.stats?.[key] ?? '0');
  }

  parseFloatStat(key: string): number {
    return parseFloat(this.stat(key)) || 0;
  }

  submitExternal(): void {
    if (this.extAmount() === null || !this.extSource()) {
      return;
    }
    this.sending.set(true);
    void firstValueFrom(
      this.http.post<ActionResponse>('/my-external-receipt', {
        amount: this.extAmount(),
        external_source: this.extSource(),
        description: this.extDescription(),
      }),
    )
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        this.data.reload();
        this.auth.loadMe().subscribe();
      })
      .catch((err: HttpErrorResponse) => this.toast.apiError(err.error?.message ?? 'errors.generic'));
  }
}
