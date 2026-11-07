// New/edit employee (plan 20): same form on /new and /:id/edit.
// T4-23: app-form-section + app-field wrappers; T4-26: back-to-list in the
// header actions. Status labels come from i18n (no Arabic literals).
import type { OnInit } from '@angular/core';
import { ChangeDetectionStrategy, Component, computed, inject, input, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { InputNumberModule } from 'primeng/inputnumber';
import { SelectModule } from 'primeng/select';
import { DatePickerModule } from 'primeng/datepicker';
import { ToastService } from '@core/errors/toast.service';
import { ApiClient } from '@core/http/api-client';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import type { LoadableResource } from '@shared/ui/async-page/async-page.component';
import { FieldComponent } from '@shared/ui/field/field.component';
import { serverFieldError } from '@shared/lib/server-field-errors';
import { FormSectionComponent } from '@shared/ui/form-section/form-section.component';
import type { ActionResponse, ItemResponse } from '@shared/models/api.models';

type EmployeeForm = Record<string, string | number | boolean | null | undefined | string[]>;

/** New/edit employee (plan 20): same form on /new and /:id/edit. */
@Component({
  selector: 'app-employee-form',
  standalone: true,
  imports: [ButtonModule, DatePickerModule, FormsModule, InputNumberModule, InputTextModule, PageHeaderComponent, RouterLink, SelectModule, TranslatePipe, FieldComponent, FormSectionComponent, AsyncPageComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="isEdit() ? ('common.edit' | translate) : ('hr.employees.add' | translate)" accent="hr" iconName="briefcase">
      <div actions>
        <p-button [label]="'common.back_to_list' | translate" size="small" [text]="true" routerLink="/hr/employees" />
      </div>
    </app-page-header>
    <!-- T5-07: a failed edit-load shows the error state with retry instead of a
         blank form that could be saved over the real employee -->
    <app-async-page [resource]="pageState" variant="form" [backLink]="['/hr/employees']">
        <form (ngSubmit)="save()" #f="ngForm">
          <app-form-section>
          <app-field label="hr.employees.user" [controlId]="fid('user_id')" [required]="true" [error]="serverFieldError(error(), 'user_id')">
            <p-select appendTo="body" [inputId]="fid('user_id')" [options]="userOptions()" optionLabel="name" optionValue="id" [filter]="true" [(ngModel)]="model.user_id" name="user_id" required styleClass="u-full" [placeholder]="'common.choose' | translate" />
          </app-field>
          <app-field label="hr.employees.department" [controlId]="fid('department')" [required]="true" [error]="serverFieldError(error(), 'department')">
            <input pInputText [id]="fid('department')" name="department" [(ngModel)]="model.department" required class="u-full" />
          </app-field>
          <app-field label="hr.employees.job_title" [controlId]="fid('job_title')" [required]="true" [error]="serverFieldError(error(), 'job_title')">
            <input pInputText [id]="fid('job_title')" name="job_title" [(ngModel)]="model.job_title" required class="u-full" />
          </app-field>
          <app-field label="hr.employees.hire_date" [controlId]="fid('hire_date')" [required]="true" [error]="serverFieldError(error(), 'hire_date')">
            <p-datepicker appendTo="body" [inputId]="fid('hire_date')" name="hire_date" [(ngModel)]="model.hire_date" view="date" dateFormat="yy-mm-dd" [showIcon]="true" styleClass="u-full" />
          </app-field>
          <app-field label="hr.employees.salary" [controlId]="fid('salary')" [required]="true" [error]="serverFieldError(error(), 'salary')">
            <p-inputnumber [inputId]="fid('salary')" [ngModel]="model.salary" name="salary" (ngModelChange)="set('salary', $event)" mode="decimal" [minFractionDigits]="2" [min]="0" required styleClass="u-full" />
          </app-field>
          <app-field label="common.status" [controlId]="fid('status')" [error]="serverFieldError(error(), 'status')">
            <p-select appendTo="body" [inputId]="fid('status')" [options]="statusOptions" optionLabel="label" optionValue="value" [(ngModel)]="model.status" name="status" styleClass="u-full" [placeholder]="'common.choose' | translate" />
          </app-field>
        </app-form-section>
          @if (alert(); as msg) { <div class="dialog__alert" role="alert">{{ msg }}</div> }
          <div class="sticky-actions">
            <p-button type="submit" [label]="'common.save' | translate" [loading]="sending()" [disabled]="f.invalid || sending()" />
          </div>
        </form>
      </app-async-page>
  `,
})
export class EmployeeFormPage implements OnInit {
  private http = inject(HttpClient);
  private router = inject(Router);
  private toast = inject(ToastService);
  private translate = inject(TranslateService);
  private api = inject(ApiClient);

  id = input<number | undefined>(undefined);
  isEdit = computed(() => this.id() !== undefined);
  sending = signal(false);
  error = signal<HttpErrorResponse | null>(null);
  /** exposed to the template — templates see class members, not module imports */
  readonly serverFieldError = serverFieldError;

  model: EmployeeForm = {
    user_id: null,
    department: '',
    job_title: '',
    hire_date: null,
    salary: null,
    status: "active",
  };

  /** employee status choices — i18n labels instead of hard-coded text */
  readonly statusOptions = [
    { label: this.translate.instant('hr.employees.status_active'), value: 'active' },
    { label: this.translate.instant('hr.employees.status_inactive'), value: 'inactive' },
  ];

  /** users from the shared lookups — user_id must exist in users (API rule) */
  readonly userOptions = signal<{ id: number; name: string }[]>([]);

  private loading = signal(true);
  private loadError = signal<unknown>(undefined);
  private loaded = signal(false);

  /** adapter so app-async-page drives this page's load/error/skeleton states (T5-07) */
  readonly pageState: LoadableResource = {
    value: () => (this.isEdit() ? this.loaded() : true),
    error: () => this.loadError(),
    isLoading: () => this.loading(),
    reload: () => { this.load(); },
  };

  fid(name: string): string { return this.selectorId() + '-' + name; }
  selectorId(): string { return 'app-employee-form'; }
  set(key: string, value: unknown): void { (this.model as Record<string, unknown>)[key] = value; }

  ngOnInit(): void {
    void firstValueFrom(this.api.loadLookups()).then((lookups) => {
      this.userOptions.set(lookups.users);
    });
    if (this.isEdit()) {
      this.load();
    } else {
      this.loading.set(false);
    }
  }

  load(): void {
    const id = this.id();
    if (id === undefined) {
      return;
    }
    this.loading.set(true);
    this.loadError.set(undefined);
    void firstValueFrom(this.http.get<ItemResponse<EmployeeForm>>('/hr/employees/' + id)).then((res) => {
      this.model = { ...this.model, ...res.data };
      this.loading.set(false);
      this.loaded.set(true);
    }).catch((err: unknown) => {
      this.loading.set(false);
      this.loadError.set(err);
    });
  }

  /** the general alert text — the server message or the generic fallback key */
  alert(): string | null {
    const err = this.error();
    if (err === null) {
      return null;
    }
    return err.error?.message ?? 'errors.generic';
  }

  save(): void {
    this.sending.set(true);
    this.error.set(null);
    const id = this.id();
    const request$ = id
      ? this.http.post<ActionResponse>('/hr/employees/' + id + '/update', this.model)
      : this.http.post<ActionResponse>('/hr/employees', this.model);
    void firstValueFrom(request$).then((res) => {
      this.sending.set(false);
      this.toast.success(res.message);
      void this.router.navigateByUrl('/hr/employees');
    }).catch((err: unknown) => {
      this.sending.set(false);
      this.error.set(err instanceof HttpErrorResponse ? err : null);
    });
  }
}
