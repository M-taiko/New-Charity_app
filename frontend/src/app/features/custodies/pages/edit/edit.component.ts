// /custodies/:id/edit — edit custody (amount + notes) for management.
import type { OnInit } from '@angular/core';
import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputNumberModule } from 'primeng/inputnumber';
import { TextareaModule } from 'primeng/textarea';
import { ToastService } from '@core/errors/toast.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import type { LoadableResource } from '@shared/ui/async-page/async-page.component';
import { FieldComponent } from '@shared/ui/field/field.component';
import { FormSectionComponent } from '@shared/ui/form-section/form-section.component';
import { ActionButtonComponent } from '@shared/ui/actions/action-button.component';
import type { ActionResponse, ItemResponse } from '@shared/models/api.models';

interface CustodyEditDto {
  amount: string;
  notes: string | null;
}

@Component({
  selector: 'app-custody-edit',
  standalone: true,
  imports: [TranslatePipe, RouterLink, FormsModule, ButtonModule, InputNumberModule, TextareaModule, PageHeaderComponent, AsyncPageComponent, FieldComponent, FormSectionComponent, ActionButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'custody.edit.title' | translate" accent="custodies" iconName="wallet">
      <div actions>
        <p-button [label]="'common.back_to_list' | translate" size="small" [text]="true" routerLink="/custodies" />
      </div>
    </app-page-header>

    <app-async-page [resource]="pageState" variant="form" [backLink]="['/custodies']">
      <form (ngSubmit)="save()" #f="ngForm">
        <app-form-section>
          <app-field label="custody.create.amount" controlId="cu-amount" [required]="true">
            <p-inputnumber inputId="cu-amount" name="amount" [(ngModel)]="amount" mode="decimal" [minFractionDigits]="2" [min]="0.01" required styleClass="u-full" />
          </app-field>
          <app-field label="common.notes" controlId="cu-notes">
            <textarea pTextarea id="cu-notes" name="notes" rows="2" [(ngModel)]="notes" class="u-full"></textarea>
          </app-field>
        </app-form-section>
        @if (error()) { <div class="dialog__alert" role="alert">{{ error() }}</div> }
        <div class="sticky-actions">
          <app-action-button kind="back" label="common.back" [route]="['/custodies']" />
          <p-button type="submit" [label]="'common.save' | translate" [loading]="sending()" [disabled]="f.invalid || sending()" />
        </div>
      </form>
    </app-async-page>
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

  private loading = signal(true);
  private loadError = signal<unknown>(undefined);
  private dto = signal<CustodyEditDto | undefined>(undefined);

  /** adapter so app-async-page drives this page's load/error/skeleton states (T4-20) */
  readonly pageState: LoadableResource = {
    value: () => this.dto(),
    error: () => this.loadError(),
    isLoading: () => this.loading(),
    reload: () => { this.load(); },
  };

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.loadError.set(undefined);
    void firstValueFrom(this.http.get<ItemResponse<CustodyEditDto>>(`/custodies/${this.id()}`))
      .then((r) => {
        this.loading.set(false);
        this.dto.set(r.data);
        this.amount.set(parseFloat(r.data.amount) || 0);
        this.notes.set(r.data.notes ?? '');
      })
      .catch((err: unknown) => {
        this.loading.set(false);
        this.loadError.set(err);
      });
  }

  save(): void {
    this.sending.set(true);
    void firstValueFrom(this.http.post<ActionResponse>(`/custodies/${this.id()}/update`, { amount: this.amount(), notes: this.notes() }))
      .then((res) => { this.sending.set(false); this.toast.success(res.message); history.back(); })
      .catch((err: unknown) => { this.sending.set(false); this.error.set((err as HttpErrorResponse).error?.message ?? 'errors.generic'); });
  }
}
