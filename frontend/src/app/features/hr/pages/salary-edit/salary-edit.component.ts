// Edit a salary calculation (plan 20 + T3-90): the full SalaryController@update
// field set — base_salary, attendance/absence/late/leave days,
// calculation_method and notes (amount/description belong to the allowance
// form, not here). Blocked for paid salaries by the server.
import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import type { OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputNumberModule } from 'primeng/inputnumber';
import { TextareaModule } from 'primeng/textarea';
import { SelectModule } from 'primeng/select';
import { ToastService } from '@core/errors/toast.service';
import { ApiClient } from '@core/http/api-client';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';

@Component({
  selector: 'app-salary-edit',
  standalone: true,
  imports: [TranslatePipe, RouterLink, FormsModule, ButtonModule, InputNumberModule, TextareaModule, SelectModule, PageHeaderComponent],
  template: `
    <app-page-header [title]="'hr.salaries.edit_title' | translate" />
    <form class="form-section" (ngSubmit)="save()" #f="ngForm">
      <div class="form-section__grid">
        <div class="field">
          <label class="field__label" for="s-base">{{ 'hr.salaries.col_base' | translate }} *</label>
          <p-inputnumber inputId="s-base" name="base" [(ngModel)]="baseSalary" required [min]="0" mode="decimal" [minFractionDigits]="2" [useGrouping]="false" styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label" for="s-method">{{ 'hr.salaries.method' | translate }} *</label>
          <p-select inputId="s-method" appendTo="body" [options]="methodOptions" optionLabel="label" optionValue="value" [(ngModel)]="calculationMethod" name="calculation_method" required styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label" for="s-att">{{ 'hr.salaries.col_attendance' | translate }} *</label>
          <p-inputnumber inputId="s-att" name="att" [(ngModel)]="attendanceDays" required [min]="0" [max]="31" styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label" for="s-abs">{{ 'hr.salaries.col_absence' | translate }} *</label>
          <p-inputnumber inputId="s-abs" name="abs" [(ngModel)]="absenceDays" required [min]="0" [max]="31" styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label" for="s-late">{{ 'hr.salaries.detail.late_days' | translate }} *</label>
          <p-inputnumber inputId="s-late" name="late" [(ngModel)]="lateDays" required [min]="0" [max]="31" styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label" for="s-leave">{{ 'hr.salaries.detail.leave_days' | translate }} *</label>
          <p-inputnumber inputId="s-leave" name="leave" [(ngModel)]="leaveDays" required [min]="0" [max]="31" styleClass="u-full" />
        </div>
      </div>
      <div class="field">
        <label class="field__label" for="s-notes">{{ 'hr.salaries.notes' | translate }}</label>
        <textarea pInputTextarea id="s-notes" rows="3" [(ngModel)]="notes" name="notes" [maxlength]="1000" style="width: 100%"></textarea>
      </div>
      @if (error()) { <div class="dialog__alert" role="alert">{{ error() }}</div> }
      <div class="sticky-actions">
        <p-button [label]="'common.back' | translate" severity="secondary" [text]="true" routerLink="/hr/salaries" />
        <p-button type="submit" [label]="'common.save' | translate" [loading]="sending()" [disabled]="f.invalid || sending()" />
      </div>
    </form>
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
  error = signal('');

  methodOptions = [
    { label: this.translate.instant('hr.salaries.method_daily'), value: 'daily_rate' },
    { label: this.translate.instant('hr.salaries.method_deduction'), value: 'deduction_method' },
  ];

  ngOnInit(): void {
    void firstValueFrom(this.api.get<Record<string, unknown>>('/hr/salaries/' + this.id())).then((salary) => {
      this.baseSalary.set(Number(salary.base_salary ?? 0));
      this.calculationMethod.set(String(salary.calculation_method ?? 'daily_rate'));
      this.attendanceDays.set(Number(salary.attendance_days ?? 0));
      this.absenceDays.set(Number(salary.absence_days ?? 0));
      this.lateDays.set(Number(salary.late_days ?? 0));
      this.leaveDays.set(Number(salary.leave_days ?? 0));
      this.notes.set(String(salary.notes ?? ''));
    });
  }

  save(): void {
    this.sending.set(true);
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
        this.error.set((err as HttpErrorResponse).error?.message ?? 'errors.generic');
      });
  }
}
