import { OnInit } from '@angular/core';
import { ChangeDetectionStrategy, Component, computed, inject, input, resource, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { InputNumberModule } from 'primeng/inputnumber';
import { TextareaModule } from 'primeng/textarea';
import { SelectModule } from 'primeng/select';
import { ToastService } from '@core/errors/toast.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { ActionResponse, ItemResponse } from '@shared/models/api.models';

/** New/edit social case (plan 20, T34 governorate select with unknown kept). */
@Component({
  selector: 'app-case-form',
  standalone: true,
  imports: [TranslatePipe, RouterLink, FormsModule, ButtonModule, InputTextModule, InputNumberModule, TextareaModule, SelectModule, PageHeaderComponent],
  template: `
    <app-page-header [title]="isEdit() ? ('case.form.edit_title' | translate) : ('case.form.create_title' | translate)" />
    <form class="form-section" (ngSubmit)="save()" #f="ngForm">
      <div class="form-section__grid">
        <div class="field"><label class="field__label" for="c-name">{{ 'case.form.name' | translate }} *</label>
          <input pInputText id="c-name" name="name" [(ngModel)]="model.name" required style="width: 100%" /></div>
        <div class="field"><label class="field__label" for="c-phone">{{ 'case.form.phone' | translate }} *</label>
          <input pInputText id="c-phone" name="phone" [(ngModel)]="model.phone" required style="width: 100%" /></div>
        <div class="field"><label class="field__label" for="c-gov">{{ 'case.form.governorate' | translate }}</label>
          <p-select appendTo="body" inputId="c-gov" [options]="governorates() ?? []" optionLabel="name" optionValue="name" [(ngModel)]="model.city" name="governorate" styleClass="u-full" [placeholder]="'common.choose' | translate" /></div>
        <div class="field"><label class="field__label" for="c-district">{{ 'case.form.district' | translate }}</label>
          <input pInputText id="c-district" name="district" [(ngModel)]="model.district" style="width: 100%" /></div>
        <div class="field"><label class="field__label" for="c-address">{{ 'case.form.address' | translate }} *</label>
          <input pInputText id="c-address" name="address" [(ngModel)]="model.address" required style="width: 100%" /></div>
        <div class="field"><label class="field__label" for="c-nid">{{ 'case.form.national_id' | translate }} *</label>
          <input pInputText id="c-nid" name="national_id" [(ngModel)]="model.national_id" required style="width: 100%" /></div>
        <div class="field"><label class="field__label" for="c-income">{{ 'case.form.monthly_income' | translate }} *</label>
          <p-inputnumber inputId="c-income" name="income" [(ngModel)]="model.monthly_income" mode="decimal" [minFractionDigits]="2" required styleClass="u-full" /></div>
        <div class="field"><label class="field__label" for="c-expenses">{{ 'case.form.monthly_expenses' | translate }} *</label>
          <p-inputnumber inputId="c-expenses" name="expenses" [(ngModel)]="model.monthly_expenses" mode="decimal" [minFractionDigits]="2" required styleClass="u-full" /></div>
        <div class="field"><label class="field__label" for="c-income-src">{{ 'case.form.income_source' | translate }} *</label>
          <input pInputText id="c-income-src" name="income_source" [(ngModel)]="model.income_source" required style="width: 100%" /></div>
      </div>
      <div class="field">
        <label class="field__label" for="c-description">{{ 'case.form.description' | translate }}</label>
        <textarea pTextarea id="c-description" name="description" rows="3" [(ngModel)]="model.description" style="width: 100%"></textarea>
      </div>
      @if (error()) { <div class="dialog__alert" role="alert">{{ error() }}</div> }
      <div class="sticky-actions">
        <p-button [label]="'common.back' | translate" severity="secondary" [text]="true" routerLink="/cases" />
        <p-button type="submit" [label]="'common.save' | translate" [loading]="sending()" [disabled]="f.invalid || sending()" />
      </div>
    </form>
  `,
})
export class CaseFormPage implements OnInit {
  private http = inject(HttpClient);
  private router = inject(Router);
  private toast = inject(ToastService);

  id = input<number | undefined>(undefined);
  isEdit = computed(() => this.id() !== undefined);
  sending = signal(false);
  error = signal('');
  model: Record<string, unknown> = {
    name: '', phone: '', city: null, district: '', address: '', national_id: '',
    monthly_income: null, monthly_expenses: null, income_source: '', description: '',
  };

  lookups = resource<{ governorates: string[] }, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<{ governorates: string[] }>>('/lookups')).then((r) => r.data),
  });

  governorates(): { name: string }[] {
    return (this.lookups.value()?.governorates ?? []).map((name) => ({ name }));
  }

  ngOnInit(): void {
    const id = this.id();
    if (id !== undefined) {
      void firstValueFrom(this.http.get<ItemResponse<Record<string, unknown>>>(`/social-cases/${id}`)).then((r) => {
        this.model = { ...this.model, ...r.data };
      });
    }
  }

  save(): void {
    this.sending.set(true);
    this.error.set('');
    const id = this.id();
    const request$ = id
      ? this.http.post<ActionResponse>(`/social-cases/${id}/update`, this.model)
      : this.http.post<ActionResponse>('/social-cases', this.model);
    void firstValueFrom(request$)
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        void this.router.navigateByUrl(id ? `/cases/${id}` : '/cases');
      })
      .catch((err: HttpErrorResponse) => {
        this.sending.set(false);
        this.error.set(err.error?.message ?? 'errors.generic');
      });
  }
}
