// Edit a salary calculation (plan 20 + T3-90): the full SalaryController@update
// field set — base_salary, attendance/absence/late/leave days,
// calculation_method and notes (amount/description belong to the allowance
// form, not here). Blocked for paid salaries by the server.
// T4-23: app-form-section + app-field; T4-26: back-to-list in the header.
import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import type { OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputNumberModule } from 'primeng/inputnumber';
import { TextareaModule } from 'primeng/textarea';
import { SelectModule } from 'primeng/select';
import { ToastService } from '@core/errors/toast.service';
import { ApiClient } from '@core/http/api-client';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import type { LoadableResource } from '@shared/ui/async-page/async-page.component';
import { FieldComponent } from '@shared/ui/field/field.component';
import { serverFieldError } from '@shared/lib/server-field-errors';
import { FormSectionComponent } from '@shared/ui/form-section/form-section.component';

@Component({
  selector: 'app-salary-edit',
  standalone: true,
  imports: [TranslatePipe, RouterLink, FormsModule, ButtonModule, InputNumberModule, TextareaModule, SelectModule, PageHeaderComponent, FieldComponent, FormSectionComponent, AsyncPageComponent],
  template: `
    <app-page-header [title]="'hr.salaries.edit_title' | translate" accent="hr" iconName="briefcase">
      <div actions>
        <p-button [label]="'common.back_to_list' | translate" size="small" [text]="true" routerLink="/hr/salaries" />
      </div>
    </app-page-header>
    <!-- T5-07: a failed load shows the error state with retry instead of a blank
         form that could be saved over the real salary -->
    <app-async-page [resource]="pageState" variant="form" [backLink]="['/hr/salaries']">
      <form (ngSubmit)="save()" #f="ngForm">
        <app-form-section>
          <app-field label="hr.salaries.col_base" controlId="s-base" [required]="true" [error]="serverFieldError(error(), 'base_salary')">
            <p-inputnumber inputId="s-base" name="base" [(ngModel)]="baseSalary" required [min]="0" mode="decimal" [minFractionDigits]="2" [useGrouping]="false" styleClass="u-full" />
          </app-field>
          <app-field label="hr.salaries.method" controlId="s-method" [required]="true" [error]="serverFieldError(error(), 'calculation_method')">
            <p-select inputId="s-method" appendTo="body" [options]="methodOptions" optionLabel="label" optionValue="value" [(ngModel)]="calculationMethod" name="calculation_method" required styleClass="u-full" />
          </app-field>
          <app-field label="hr.salaries.col_attendance" controlId="s-att" [required]="true" [error]="serverFieldError(error(), 'attendance_days')">
            <p-inputnumber inputId="s-att" name="att" [(ngModel)]="attendanceDays" required [min]="0" [max]="31" styleClass="u-full" />
          </app-field>
          <app-field label="hr.salaries.col_absence" controlId="s-abs" [required]="true" [error]="serverFieldError(error(), 'absence_days')">
            <p-inputnumber inputId="s-abs" name="abs" [(ngModel)]="absenceDays" required [min]="0" [max]="31" styleClass="u-full" />
          </app-field>
          <app-field label="hr.salaries.detail.late_days" controlId="s-late" [required]="true" [error]="serverFieldError(error(), 'late_days')">
            <p-inputnumber inputId="s-late" name="late" [(ngModel)]="lateDays" required [min]="0" [max]="31" styleClass="u-full" />
          </app-field>
          <app-field label="hr.salaries.detail.leave_days" controlId="s-leave" [required]="true" [error]="serverFieldError(error(), 'leave_days')">
            <p-inputnumber inputId="s-leave" name="leave" [(ngModel)]="leaveDays" required [min]="0" [max]="31" styleClass="u-full" />
          </app-field>
        </app-form-section>
        <app-form-section>
          <app-field label="hr.salaries.notes" controlId="s-notes" [error]="serverFieldError(error(), 'notes')">
            <textarea pInputTextarea id="s-notes" rows="3" [(ngModel)]="notes" name="notes" [maxlength]="1000" class="u-full"></textarea>
          </app-field>
        </app-form-section>
        @if (alert(); as msg) { <div class="dialog__alert" role="alert">{{ msg }}</div> }
        <div class="sticky-actions">
          <p-button type="submit" [label]="'common.save' | translate" [loading]="sending()" [disabled]="f.invalid || sending()" />
        </div>
      </form>
    </app-async-page>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SalaryEditPage implements OnInit {
  private api = inject(ApiClient);
  private toast = inject(ToastService);
  private translate = inject(TranslateService);
  id = input.required<number>();
  baseSalary = signal<number | null>(null);
  calculationMethod = signal<string | null>(null);
  attendanceDays = signal<number | null>(null);
  absenceDays = signal<number | null>(null);
  lateDays = signal<number | null>(null);
  leaveDays = signal<number | null>(null);
  notes = signal('');
  sending = signal(false);
  error = signal<HttpErrorResponse | null>(null);
  /** exposed to the template — templates see class members, not module imports */
  readonly serverFieldError = serverFieldError;

  methodOptions = [
    { label: this.translate.instant('hr.salaries.method_daily'), value: 'daily_rate' },
    { label: this.translate.instant('hr.salaries.method_deduction'), value: 'deduction_method' },
  ];

  private loading = signal(true);
  private loadError = signal<unknown>(undefined);
  private loaded = signal(false);

  /** adapter so app-async-page drives this page's load/error/skeleton states (T5-07) */
  readonly pageState: LoadableResource = {
    value: () => this.loaded(),
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
    void firstValueFrom(this.api.get<Record<string, unknown>>('/hr/salaries/' + this.id())).then((salary) => {
      this.baseSalary.set(Number(salary.base_salary ?? 0));
      this.calculationMethod.set(String(salary.calculation_method ?? 'daily_rate'));
      this.attendanceDays.set(Number(salary.attendance_days ?? 0));
      this.absenceDays.set(Number(salary.absence_days ?? 0));
      this.lateDays.set(Number(salary.late_days ?? 0));
      this.leaveDays.set(Number(salary.leave_days ?? 0));
      this.notes.set(String(salary.notes ?? ''));
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
    void firstValueFrom(
      this.api.action('/hr/salaries/' + this.id() + '/update', {
        base_salary: this.baseSalary(),
        calculation_method: this.calculationMethod(),
        attendance_days: this.attendanceDays(),
        absence_days: this.absenceDays(),
        late_days: this.lateDays(),
        leave_days: this.leaveDays(),
        notes: this.notes(),
      }),
    )
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        history.back();
      })
      .catch((err: unknown) => {
        this.sending.set(false);
        this.error.set(err instanceof HttpErrorResponse ? err : null);
      });
  }
}
