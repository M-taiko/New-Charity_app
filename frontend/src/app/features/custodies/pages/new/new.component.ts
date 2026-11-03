import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import type { OnInit } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputNumberModule } from 'primeng/inputnumber';
import { TextareaModule } from 'primeng/textarea';
import { SelectModule } from 'primeng/select';
import { DatePickerModule } from 'primeng/datepicker';
import { ToastService } from '@core/errors/toast.service';
import { AuthService } from '@core/auth/auth.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import type { ActionResponse, ItemResponse, Lookups } from '@shared/models/api.models';

type Variant = 'agent' | 'for-agent' | 'personal';

/**
 * Custody creation — the three Blade variants in one form (plan 20 + T3-15):
 * accountant creates for an agent (agent + treasury with a live "balance
 * after"), agent request for self (no treasury — the manager decides it on
 * approval), and the manager/accountant personal custody (`for_self`).
 * Visibility mirrors CustodyController@create / the Blade variants.
 */
@Component({
  selector: 'app-custody-form',
  standalone: true,
  imports: [TranslatePipe, RouterLink, FormsModule, ButtonModule, InputNumberModule, TextareaModule, SelectModule, DatePickerModule, PageHeaderComponent, MoneyPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="title() | translate" [subtitle]="subtitle() | translate" />
    <div class="u-flex u-gap-4 u-flex-wrap">
      <form class="form-section" style="flex: 1; min-width: min(100%, 34rem)" (ngSubmit)="submit()" #f="ngForm">
        <div class="form-section__grid">
          @if (variant() === 'for-agent') {
            <div class="field">
              <label class="field__label" for="agent">{{ 'custody.create.agent' | translate }} *</label>
              <p-select appendTo="body" inputId="agent" [options]="users()" optionLabel="name" optionValue="id"
                [filter]="true" [(ngModel)]="agentId" name="agent" required styleClass="u-full" [autofocus]="false" />
            </div>
            <div class="field">
              <label class="field__label" for="treasury">{{ 'custody.create.treasury' | translate }} *</label>
              <p-select appendTo="body" inputId="treasury" [options]="treasuries()" optionLabel="name" optionValue="id"
                [(ngModel)]="treasuryId" name="treasury" required (ngModelChange)="onTreasury()" styleClass="u-full" [autofocus]="false" />
              @if (selectedTreasury(); as t) {
                <span class="field__hint u-bidi">
                  {{ 'custody.create.treasury_balance' | translate }}: {{ t.balance | money }} —
                  {{ 'custody.create.balance_after' | translate }}: {{ balanceAfter() | money }}
                </span>
              }
            </div>
          }
          <div class="field">
            <label class="field__label" for="amount">{{ 'custody.create.amount' | translate }} *</label>
            <p-inputnumber inputId="amount" name="amount" [(ngModel)]="amount" mode="decimal" [minFractionDigits]="2"
              [min]="0.01" [max]="maxAmount()" required styleClass="u-full" />
            @if (maxAmount() !== null) {
              <span class="field__hint u-bidi">{{ 'custody.create.max_amount' | translate }}: {{ maxAmount() | money }}</span>
            }
          </div>
          <div class="field">
            <label class="field__label" for="issued">{{ dateLabel() | translate }} *</label>
            <p-datepicker appendTo="body" inputId="issued" name="issued" [(ngModel)]="issuedDate" dateFormat="yy-mm-dd"
              [showIcon]="true" required styleClass="u-full" />
            @if (variant() === 'agent') {
              <span class="field__hint u-bidi">{{ 'custody.create.request_date' | translate }}: {{ today }}</span>
            }
          </div>
          <div class="field">
            <label class="field__label" for="notes">{{ 'common.notes' | translate }}</label>
            <textarea pTextarea id="notes" name="notes" rows="2" [(ngModel)]="notes" style="width: 100%"></textarea>
          </div>
        </div>
        @if (error()) { <div class="dialog__alert" role="alert">{{ error() }}</div> }
        <div class="sticky-actions">
          <p-button [label]="'common.back' | translate" severity="secondary" [text]="true" routerLink="/custodies" />
          <p-button type="submit" [label]="submitLabel() | translate" [loading]="sending()" [disabled]="f.invalid || sending() || overBalance()" />
        </div>
      </form>

      <aside class="chart-card" style="flex: 1; min-width: min(100%, 20rem); align-self: flex-start">
        <div class="chart-card__head"><span class="chart-card__title">{{ 'custody.create.info_title' | translate }}</span></div>
        <ul class="u-column u-gap-1 u-small">
          @for (hint of hints(); track hint) {
            <li>{{ hint | translate }}</li>
          }
        </ul>
      </aside>
    </div>
  `,
})
export class CustodyFormPage implements OnInit {
  private http = inject(HttpClient);
  private router = inject(Router);
  private toast = inject(ToastService);
  private auth = inject(AuthService);

  variant = signal<Variant>('personal');
  agentId = signal<number | null>(null);
  treasuryId = signal<number | null>(null);
  amount = signal<number | null>(null);
  issuedDate = signal<string>(new Date().toISOString().slice(0, 10));
  notes = signal('');
  sending = signal(false);
  error = signal('');
  readonly today = new Date().toISOString().slice(0, 10);

  lookups = resource<Lookups, unknown>({
    loader: () =>
      firstValueFrom(this.http.get<ItemResponse<Lookups>>('/lookups')).then((r) => r.data),
  });

  treasuries(): { id: number; name: string; balance: string }[] {
    return this.lookups.value()?.treasuries ?? [];
  }

  users(): { id: number; name: string }[] {
    return this.lookups.value()?.users ?? [];
  }

  selectedTreasury(): { id: number; name: string; balance: string } | null {
    const id = this.treasuryId();
    return this.treasuries().find((t) => t.id === id) ?? null;
  }

  /**
   * Same routing as CustodyController@create: an explicit ?for= wins;
   * otherwise agents request for themselves, everyone else personally.
   */
  ngOnInit(): void {
    const url = this.router.url;
    if (url.includes('for=agent')) {
      this.variant.set('for-agent');
    } else if (url.includes('for=self')) {
      this.variant.set('personal');
    } else if (this.isAgent()) {
      this.variant.set('agent');
    } else {
      this.variant.set('personal');
    }
  }

  private isAgent(): boolean {
    return this.auth.can('is_agent') || this.auth.hasRole('مندوب');
  }

  title(): string {
    return {
      agent: 'custody.create.agent_title',
      'for-agent': 'custody.create.for_agent_title',
      personal: 'custody.create.personal_title',
    }[this.variant()];
  }

  subtitle(): string {
    return {
      agent: 'custody.create.subtitle_agent',
      'for-agent': 'custody.create.subtitle_for_agent',
      personal: 'custody.create.subtitle_personal',
    }[this.variant()];
  }

  submitLabel(): string {
    return this.variant() === 'for-agent' ? 'custody.create.submit_create' : 'custody.create.submit_request';
  }

  dateLabel(): string {
    return this.variant() === 'for-agent' ? 'custody.create.issued_date' : 'custody.create.request_date';
  }

  /** the Blade info panels, per variant */
  hints(): string[] {
    const agentHints = [
      'custody.create.hints_agent.h1',
      'custody.create.hints_agent.h2',
      'custody.create.hints_agent.h3',
      'custody.create.hints_agent.h4',
      'custody.create.hints_agent.h5',
    ];
    const forAgentHints = [
      'custody.create.hints_for_agent.h1',
      'custody.create.hints_for_agent.h2',
      'custody.create.hints_for_agent.h3',
      'custody.create.hints_for_agent.h4',
      'custody.create.hints_for_agent.h5',
    ];
    const personalHints = [
      'custody.create.hints_personal.h1',
      'custody.create.hints_personal.h2',
      'custody.create.hints_personal.h3',
      'custody.create.hints_personal.h4',
      'custody.create.hints_personal.h5',
      'custody.create.hints_personal.h6',
    ];
    if (this.variant() === 'agent') {
      return agentHints;
    }
    return this.variant() === 'for-agent' ? forAgentHints : personalHints;
  }

  /** a chosen treasury caps the amount (server re-validates with the same rule) */
  maxAmount(): number | null {
    const t = this.selectedTreasury();
    return this.variant() === 'for-agent' && t ? parseFloat(t.balance) : null;
  }

  overBalance(): boolean {
    const max = this.maxAmount();
    const amount = this.amount();
    return max !== null && amount !== null && amount > max;
  }

  /** for-agent only: the treasury balance after disbursing the amount */
  balanceAfter(): string {
    const t = this.selectedTreasury();
    if (!t) {
      return '0.00';
    }
    return (parseFloat(t.balance) - (this.amount() ?? 0)).toFixed(2);
  }

  onTreasury(): void {
    // dropping back under the cap clears the inline overflow state
    const amount = this.amount();
    const max = this.maxAmount();
    if (amount !== null && max !== null && amount <= max) {
      this.error.set('');
    }
  }

  submit(): void {
    if (this.amount() === null || this.overBalance()) {
      return;
    }
    this.sending.set(true);
    this.error.set('');
    const body: Record<string, unknown> = {
      amount: this.amount(),
      issued_date: this.issuedDate(),
      notes: this.notes(),
    };
    if (this.variant() === 'for-agent' && this.agentId() !== null) {
      body.agent_id = this.agentId();
      if (this.treasuryId() !== null) {
        body.treasury_id = this.treasuryId();
      }
    } else if (this.variant() === 'personal') {
      // personal custody (Blade personal-request hidden field): the manager
      // chooses the treasury during approval, so none is sent here
      body.for_self = true;
    }
    void firstValueFrom(this.http.post<ActionResponse>('/custodies', body))
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        void this.router.navigateByUrl(this.variant() === 'agent' ? '/custodies/mine/movements' : '/custodies');
      })
      .catch((err: unknown) => {
        this.sending.set(false);
        this.error.set(err instanceof HttpErrorResponse ? err.error?.message ?? 'errors.generic' : 'errors.generic');
      });
  }
}
