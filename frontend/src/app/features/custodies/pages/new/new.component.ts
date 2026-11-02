import { computed, OnInit } from '@angular/core';
import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
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
import { DatePickerModule } from 'primeng/datepicker';
import { ToastService } from '@core/errors/toast.service';
import { AuthService } from '@core/auth/auth.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { ActionResponse, ItemResponse } from '@shared/models/api.models';

/**
 * Custody creation — three variants in one form (plan 20): agent request
 * (for self), create-for-agent, personal request. Roles drive the variant.
 */
@Component({
  selector: 'app-custody-form',
  standalone: true,
  imports: [TranslatePipe, RouterLink, FormsModule, ButtonModule, InputNumberModule, InputTextModule, TextareaModule, SelectModule, DatePickerModule, PageHeaderComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="title() | translate" />
    <form class="form-section" (ngSubmit)="submit()" #f="ngForm">
      <div class="form-section__grid">
        @if (variant() === 'for-agent') {
          <div class="field">
            <label class="field__label" for="agent">{{ 'custody.create.agent' | translate }} *</label>
            <p-select appendTo="body" inputId="agent" [options]="users() ?? []" optionLabel="name" optionValue="id" [(ngModel)]="agentId" name="agent" required styleClass="u-full" />
          </div>
          <div class="field">
            <label class="field__label" for="treasury">{{ 'custody.create.treasury' | translate }} *</label>
            <p-select appendTo="body" inputId="treasury" [options]="treasuries() ?? []" optionLabel="name" optionValue="id" [(ngModel)]="treasuryId" name="treasury" required styleClass="u-full" />
          </div>
        } @else if (variant() === 'agent') {
          <div class="field">
            <label class="field__label" for="treasury">{{ 'custody.create.treasury' | translate }}</label>
            <p-select appendTo="body" inputId="treasury" [options]="treasuries() ?? []" optionLabel="name" optionValue="id" [(ngModel)]="treasuryId" name="treasury" styleClass="u-full" />
            <span class="field__hint">{{ 'custody.create.request_date' | translate }}: {{ today }}</span>
          </div>
        }
        <div class="field">
          <label class="field__label" for="amount">{{ 'custody.create.amount' | translate }} *</label>
          <p-inputnumber inputId="amount" name="amount" [(ngModel)]="amount" mode="decimal" [minFractionDigits]="2" [min]="0.01" required styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label" for="issued">{{ 'custody.create.issued_date' | translate }} *</label>
          <p-datepicker appendTo="body" inputId="issued" name="issued" [(ngModel)]="issuedDate" dateFormat="yy-mm-dd" [showIcon]="true" required styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label" for="notes">{{ 'common.notes' | translate }}</label>
          <textarea pTextarea id="notes" name="notes" rows="2" [(ngModel)]="notes" style="width: 100%"></textarea>
        </div>
      </div>
      @if (error()) { <div class="dialog__alert" role="alert">{{ error() }}</div> }
      <div class="sticky-actions">
        <p-button [label]="'common.back' | translate" severity="secondary" [text]="true" routerLink="/custodies" />
        <p-button type="submit" [label]="submitLabel() | translate" [loading]="sending()" [disabled]="f.invalid || sending()" />
      </div>
    </form>
  `,
})
export class CustodyFormPage implements OnInit {
  private http = inject(HttpClient);
  private router = inject(Router);
  private toast = inject(ToastService);
  private auth = inject(AuthService);

  variant = signal<'agent' | 'for-agent' | 'personal'>('personal');
  agentId = signal<number | null>(null);
  treasuryId = signal<number | null>(null);
  amount = signal<number | null>(null);
  issuedDate = signal<string>(new Date().toISOString().slice(0, 10));
  notes = signal('');
  sending = signal(false);
  error = signal('');
  readonly today = new Date().toISOString().slice(0, 10);

  lookups = resource<{ treasuries: { id: number; name: string }[]; users: { id: number; name: string }[] }, unknown>({
    loader: () =>
      firstValueFrom(this.http.get<ItemResponse<{ treasuries: { id: number; name: string }[]; users: { id: number; name: string }[] }>>('/lookups')).then((r) => r.data),
  });

  treasuries(): { id: number; name: string }[] {
    return this.lookups.value()?.treasuries ?? [];
  }

  users(): { id: number; name: string }[] {
    return this.lookups.value()?.users ?? [];
  }

  ngOnInit(): void {
    const url = this.router.url;
    if (url.includes('for=agent')) {
      this.variant.set('for-agent');
    } else if (this.auth.can('is_agent')) {
      this.variant.set('agent');
    } else if (this.auth.can('may_create_custody')) {
      this.variant.set('for-agent');
    } else {
      this.variant.set('personal');
    }
  }

  title(): string {
    return { agent: 'custody.create.agent_title', 'for-agent': 'custody.create.for_agent_title', personal: 'custody.create.personal_title' }[this.variant()];
  }

  submitLabel(): string {
    return this.variant() === 'for-agent' ? 'custody.create.submit_create' : 'custody.create.submit_request';
  }

  submit(): void {
    if (this.amount() === null) {
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
    } else if (this.treasuryId() !== null) {
      body.treasury_id = this.treasuryId();
    }
    void firstValueFrom(this.http.post<ActionResponse>('/custodies', body))
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        void this.router.navigateByUrl(this.variant() === 'for-agent' ? '/custodies' : '/custodies/mine');
      })
      .catch((err: HttpErrorResponse) => {
        this.sending.set(false);
        this.error.set(err.error?.message ?? 'errors.generic');
      });
  }
}
