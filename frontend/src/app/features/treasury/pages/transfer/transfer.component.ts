import { ChangeDetectionStrategy, Component, computed, inject, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { SelectModule } from 'primeng/select';
import { InputNumberModule } from 'primeng/inputnumber';
import { TextareaModule } from 'primeng/textarea';
import { ToastService } from '@core/errors/toast.service';
import { ApiClient } from '@core/http/api-client';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { SectionNavComponent } from '@shared/ui/section-nav/section-nav.component';
import { FieldComponent } from '@shared/ui/field/field.component';
import { serverFieldError } from '@shared/lib/server-field-errors';
import { FormSectionComponent } from '@shared/ui/form-section/form-section.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import type { ActionResponse} from '@shared/models/api.models';
import { ActionButtonComponent } from '@shared/ui/actions/action-button.component';

interface TreasuryOption {
  id: number;
  name: string;
  balance: string;
}

/** Transfer between treasuries (plan 20): wording واردة/صادرة (T32). */
@Component({
  selector: 'app-treasury-transfer',
  standalone: true,
  imports: [SectionNavComponent, 
    TranslatePipe, RouterLink, FormsModule, ButtonModule, SelectModule, InputNumberModule,
    TextareaModule, PageHeaderComponent, FieldComponent, FormSectionComponent, MoneyPipe, ActionButtonComponent,
  ],
  template: `
    <app-page-header [title]="'treasury.transfer.title' | translate" accent="treasury" iconName="building-bank">
      <div actions>
        <p-button [label]="'common.back_to_list' | translate" size="small" [text]="true" routerLink="/treasury" />
      </div>
    </app-page-header>

    <app-section-nav module="treasury" />
    <form (ngSubmit)="submit()" #f="ngForm">
      <app-form-section>
        <app-field label="treasury.transfer.from" controlId="transfer-from" [required]="true" hint="common.hint_treasury_balance" [error]="serverFieldError(error(), 'from_treasury_id')">
          <p-select appendTo="body"
            inputId="transfer-from"
            [options]="treasuryOptions()"
            optionLabel="name"
            optionValue="id"
            [(ngModel)]="fromId"
            name="from"
            required
            styleClass="u-full"          >
            <!-- T7-11: the available balance rides every option's text -->
            <ng-template let-option #item>
              <div class="u-flex-between">
                <span>{{ option.name }}</span>
                <span class="u-bidi u-small">{{ option.balance | money }}</span>
              </div>
            </ng-template>
          </p-select>
          @if (fromBalance() !== null) {
            <span class="field__hint u-bidi">{{ fromBalance() | money }}</span>
          }
        </app-field>
        <app-field label="treasury.transfer.to" controlId="transfer-to" [required]="true" [error]="serverFieldError(error(), 'to_treasury_id')">
          <p-select appendTo="body"
            inputId="transfer-to"
            [options]="treasuryOptions()"
            optionLabel="name"
            optionValue="id"
            [(ngModel)]="toId"
            name="to"
            required
            styleClass="u-full"          >
            <!-- T7-11: the available balance rides every option's text -->
            <ng-template let-option #item>
              <div class="u-flex-between">
                <span>{{ option.name }}</span>
                <span class="u-bidi u-small">{{ option.balance | money }}</span>
              </div>
            </ng-template>
          </p-select>
        </app-field>
        <app-field label="treasury.transfer.amount" controlId="transfer-amount" [required]="true" hint="common.hint_max_amount" [error]="overBalance() ? ('treasury.transfer.over_balance' | translate) : serverFieldError(error(), 'amount')">
          <p-inputnumber inputId="transfer-amount" name="amount" [(ngModel)]="amount" mode="decimal" [minFractionDigits]="2" [min]="0.01" required styleClass="u-full" />
        </app-field>
        <app-field label="treasury.transfer.notes" controlId="transfer-desc" [required]="true" [error]="serverFieldError(error(), 'description')">
          <textarea pTextarea id="transfer-desc" name="desc" rows="2" class="u-full" [(ngModel)]="description" required></textarea>
        </app-field>
      </app-form-section>
      @if (alert(); as msg) {
        <div class="dialog__alert" role="alert">{{ msg }}</div>
      }
      <div class="sticky-actions">
        <app-action-button kind="back" label="common.back" [route]="['/treasury']" />
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
  error = signal<HttpErrorResponse | null>(null);
  /** exposed to the template — templates see class members, not module imports */
  readonly serverFieldError = serverFieldError;

  treasuries = resource<TreasuryOption[], unknown>({
    loader: () => firstValueFrom(this.api.loadLookups()).then((lookups) => lookups.treasuries),
  });

  treasuryOptions = computed(() => {
    try {
      return this.treasuries.value() ?? [];
    } catch {
      return [];
    }
  });

  fromBalance = computed(() => {
    const found = this.treasuryOptions().find((t) => t.id === this.fromId());
    return found ? found.balance : null;
  });

  overBalance = computed(() => {
    const balance = parseFloat(this.fromBalance() ?? '0') || 0;
    return this.amount() !== null && this.amount()! > balance;
  });

  /** the general alert text — the server message or the generic fallback key */
  alert(): string | null {
    const err = this.error();
    if (err === null) {
      return null;
    }
    return err.error?.message ?? 'errors.generic';
  }

  submit(): void {
    if (this.fromId() === null || this.toId() === null || this.amount() === null) {
      return;
    }
    this.sending.set(true);
    this.error.set(null);
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
        this.error.set(err instanceof HttpErrorResponse ? err : null);
      });
  }
}
