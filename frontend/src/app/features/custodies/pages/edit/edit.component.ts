// /custodies/:id/edit — edit custody (amount + notes) for management.
import { computed, OnInit } from '@angular/core';
import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputNumberModule } from 'primeng/inputnumber';
import { TextareaModule } from 'primeng/textarea';
import { ToastService } from '@core/errors/toast.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { ActionResponse, ItemResponse } from '@shared/models/api.models';

@Component({
  selector: 'app-custody-edit',
  standalone: true,
  imports: [TranslatePipe, RouterLink, FormsModule, ButtonModule, InputNumberModule, TextareaModule, PageHeaderComponent],
  template: `
    <app-page-header [title]="'custody.edit.title' | translate" />
    <form class="form-section" (ngSubmit)="save()" #f="ngForm">
      <div class="form-section__grid">
        <div class="field">
          <label class="field__label" for="cu-amount">{{ 'custody.create.amount' | translate }} *</label>
          <p-inputnumber inputId="cu-amount" name="amount" [(ngModel)]="amount" mode="decimal" [minFractionDigits]="2" [min]="0.01" required styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label" for="cu-notes">{{ 'common.notes' | translate }}</label>
          <textarea pTextarea id="cu-notes" name="notes" rows="2" [(ngModel)]="notes" style="width: 100%"></textarea>
        </div>
      </div>
      @if (error()) { <div class="dialog__alert" role="alert">{{ error() }}</div> }
      <div class="sticky-actions">
        <p-button [label]="'common.back' | translate" severity="secondary" [text]="true" routerLink="/custodies" />
        <p-button type="submit" [label]="'common.save' | translate" [loading]="sending()" [disabled]="f.invalid || sending()" />
      </div>
    </form>
  `,
})
export class CustodyEditPage implements OnInit {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  id = input.required<number>();
  amount = signal<number | null>(null);
  notes = signal('');
  sending = signal(false);
  error = signal('');

  ngOnInit(): void {
    void firstValueFrom(this.http.get<ItemResponse<{ amount: string; notes: string | null }>>(`/custodies/${this.id()}`)).then((r) => {
      this.amount.set(parseFloat(r.data.amount) || 0);
      this.notes.set(r.data.notes ?? '');
    });
  }

  save(): void {
    this.sending.set(true);
    void firstValueFrom(this.http.post<ActionResponse>(`/custodies/${this.id()}/update`, { amount: this.amount(), notes: this.notes() }))
      .then((res) => { this.sending.set(false); this.toast.success(res.message); history.back(); })
      .catch((err: HttpErrorResponse) => { this.sending.set(false); this.error.set(err.error?.message ?? 'errors.generic'); });
  }
}
