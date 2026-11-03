import { ChangeDetectionStrategy, Component, computed, inject, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { SelectModule } from 'primeng/select';
import { InputNumberModule } from 'primeng/inputnumber';
import { TextareaModule } from 'primeng/textarea';
import { ToastService } from '@core/errors/toast.service';
import { ApiClient } from '@core/http/api-client';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import type { ActionResponse} from '@shared/models/api.models';

interface TreasuryOption {
  id: number;
  name: string;
  balance: string;
}

/** Transfer between treasuries (plan 20): wording واردة/صادرة (T32). */
@Component({
  selector: 'app-treasury-transfer',
  standalone: true,
  imports: [
    TranslatePipe, RouterLink, FormsModule, ButtonModule, SelectModule, InputNumberModule,
    TextareaModule, PageHeaderComponent, MoneyPipe,
  ],
  template: `
    <app-page-header [title]="'treasury.transfer.title' | translate" />
    <form class="form-section" (ngSubmit)="submit()" #f="ngForm">
      <div class="form-section__grid">
        <div class="field">
          <label class="field__label" for="from">
            {{ 'treasury.transfer.from' | translate }} <span class="field__required" aria-hidden="true">*</span>
          </label>
          <p-select appendTo="body"
            inputId="from"
            [options]="treasuryOptions()"
            optionLabel="name"
            optionValue="id"
            [(ngModel)]="fromId" name="fromId"
            name="from"
            required
            styleClass="u-full"
          />
          @if (fromBalance() !== null) {
            <span class="field__hint u-bidi">{{ fromBalance() | money }}</span>
          }
        </div>
        <div class="field">
          <label class="field__label" for="to">
            {{ 'treasury.transfer.to' | translate }} <span class="field__required" aria-hidden="true">*</span>
          </label>
          <p-select appendTo="body"
            inputId="to"
            [options]="treasuryOptions()"
            optionLabel="name"
            optionValue="id"
            [(ngModel)]="toId" name="toId"
            name="to"
            required
            styleClass="u-full"
          />
        </div>
        <div class="field">
          <label class="field__label" for="amount">
            {{ 'treasury.transfer.amount' | translate }} <span class="field__required" aria-hidden="true">*</span>
          </label>
          <p-inputnumber inputId="amount" name="amount" [(ngModel)]="amount" mode="decimal" [minFractionDigits]="2" [min]="0.01" required styleClass="u-full" />
          @if (overBalance()) {
            <span class="field__error">{{ 'treasury.donation.amount' | translate }}</span>
          }
        </div>
        <div class="field">
          <label class="field__label" for="desc">
            {{ 'treasury.transfer.notes' | translate }} <span class="field__required" aria-hidden="true">*</span>
          </label>
          <textarea pTextarea id="desc" name="desc" rows="2" [(ngModel)]="description" required style="width: 100%"></textarea>
        </div>
      </div>
      @if (error()) {
        <div class="dialog__alert" role="alert">{{ error() }}</div>
      }
      <div class="sticky-actions">
        <p-button [label]="'common.back' | translate" severity="secondary" [text]="true" routerLink="/treasury" />
        <p-button
          type="submit"
          [label]="'treasury.transfer.submit' | translate"
          [loading]="sending()"
          [disabled]="f.invalid || sending() || overBalance()"
        />
      </div>
    </form>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TreasuryTransferPage {
  private http = inject(HttpClient);
  private api = inject(ApiClient);
  private toast = inject(ToastService);

  fromId = signal<number | null>(null);
  toId = signal<number | null>(null);
  amount = signal<number | null>(null);
  description = signal('');
  sending = signal(false);
  error = signal('');

  treasuries = resource<TreasuryOption[], unknown>({
    loader: () => firstValueFrom(this.api.loadLookups()).then((lookups) => lookups.treasuries),
  });

  treasuryOptions = computed(() => this.treasuries.value() ?? []);

  fromBalance = computed(() => {
    const found = this.treasuryOptions().find((t) => t.id === this.fromId());
    return found ? found.balance : null;
  });

  overBalance = computed(() => {
    const balance = parseFloat(this.fromBalance() ?? '0') || 0;
    return this.amount() !== null && this.amount()! > balance;
  });

  submit(): void {
    if (this.fromId() === null || this.toId() === null || this.amount() === null) {
      return;
    }
    this.sending.set(true);
    this.error.set('');
    void firstValueFrom(
      this.http.post<ActionResponse>('/treasury/perform-transfer', {
        from_treasury_id: this.fromId(),
        to_treasury_id: this.toId(),
        amount: this.amount(),
        description: this.description(),
      }),
    )
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
      })
      .catch((err: unknown) => {
        this.sending.set(false);
        this.error.set((err as HttpErrorResponse).error?.message ?? 'errors.generic');
      });
  }
}
