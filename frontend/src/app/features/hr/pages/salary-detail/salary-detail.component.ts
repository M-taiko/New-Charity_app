// Salary details (plan 20 + T3-90): status badges draft/calculated/approved/
// paid, approve when calculated, record as expense when approved, add
// allowance while not paid — mirroring hr/salaries/show.blade.php. The exact
// allowance fields come from SalaryController@addAllowance: type, name,
// amount, description. T4-20: async-page states; T4-23: app-field in the
// allowance dialog.
import { computed, ChangeDetectionStrategy, Component, inject, input, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { MenuModule } from 'primeng/menu';
import type { MenuItem } from 'primeng/api';
import { TextareaModule } from 'primeng/textarea';
import { SelectModule } from 'primeng/select';
import { InputNumberModule } from 'primeng/inputnumber';
import { InputTextModule } from 'primeng/inputtext';
import { ToastService } from '@core/errors/toast.service';
import { ApiClient } from '@core/http/api-client';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { KeyValueListComponent } from '@shared/ui/key-value-list/key-value-list.component';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';
import { FieldComponent } from '@shared/ui/field/field.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';

interface SalaryAllowance {
  id: number;
  type: string;
  description: string | null;
  amount: string;
}

interface SalaryDetail {
  id: number;
  employee: string | null;
  employee_id: number;
  year: number;
  month: number;
  base_salary: string;
  attendance_days: number;
  absence_days: number;
  calculated_salary: string;
  deductions: string;
  bonuses: string;
  final_salary: string;
  status: string;
  calculation_method: string | null;
  late_days: number;
  leave_days: number;
  notes: string | null;
  approved_by: string | null;
  approved_at: string | null;
  paid_at: string | null;
  allowances: SalaryAllowance[];
}

/** Salary details (plan 20): approve, record as expense, add allowance (T3-90). */
@Component({
  selector: 'app-salary-detail',
  standalone: true,
  imports: [MenuModule, TranslatePipe, RouterLink, FormsModule, ButtonModule, TextareaModule, SelectModule, InputNumberModule, InputTextModule, PageHeaderComponent, AsyncPageComponent, KeyValueListComponent, StatusStampComponent, PrintSheetComponent, FormDialogComponent, FieldComponent, MoneyPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  styles: `
    .allowance-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }
  `,
  template: `
    <app-async-page [resource]="data" variant="detail" [backLink]="['/hr/salaries']">
      @if (safeValue(); as item) {
          <!-- T6-10: one sticky action bar — primary decision first -->
          <div class="sticky-actions u-flex u-gap-2 u-flex-wrap">
            @if (item.status === 'calculated') {
              <p-button [label]="'hr.salaries.detail.approve' | translate" size="small" severity="success" (onClick)="act('approve')" [loading]="sending()" />
            }
            @if (item.status === 'approved') {
              <p-button [label]="'hr.salaries.detail.record_expense' | translate" size="small" severity="success" (onClick)="act('record-expense')" [loading]="sending()" />
            }
          </div>
        <app-print-sheet [title]="'hr.salaries.detail.title' | translate">
          <app-page-header [title]="itemTitle(item)">
            <div actions class="u-flex u-gap-2 u-flex-wrap">
              <!-- T6-10: max 3 visible buttons; edit/allowance in the More menu -->
              @if (item.status !== 'paid') {
                <p-button [label]="'common.more' | translate" size="small" severity="secondary" [outlined]="true" (onClick)="salaryMoreMenu.toggle($event)" />
                <p-menu #salaryMoreMenu appendTo="body" [model]="moreMenuItems()" popup="true" />
              }
              <p-button [label]="'hr.salaries.detail.payslip' | translate" size="small" severity="secondary" [outlined]="true" (onClick)="downloadPayslip()" />
              <p-button [label]="'common.back_to_list' | translate" size="small" severity="secondary" [outlined]="true" routerLink="/hr/salaries" />
            </div>
          </app-page-header>
          <div class="u-mb-4"><app-status-stamp [status]="item.status" namespace="salary" [large]="true" /></div>
          <app-key-value-list [items]="keyValues(item)" />

          @if (item.allowances.length > 0) {
            <section class="form-section u-mt-4">
              <h3 class="form-section__title">{{ 'hr.salaries.detail.allowances' | translate }}</h3>
              <ul class="allowance-list u-column u-gap-2">
                @for (allowance of item.allowances; track allowance.id) {
                  <li class="u-flex u-gap-2 u-flex-wrap">
                    <span class="stamp" [class.stamp--danger]="allowance.type === 'deduction'" [class.stamp--success]="allowance.type !== 'deduction'">
                      {{ allowance.type === 'deduction' ? ('hr.salaries.allowance.deduction' | translate) : ('hr.salaries.allowance.bonus' | translate) }}
                    </span>
                    <span>{{ allowance.description ?? '—' }}</span>
                    <span class="u-bidi">{{ allowance.amount | money }}</span>
                  </li>
                }
              </ul>
            </section>
          }

          <app-form-dialog
            [header]="'hr.salaries.detail.add_allowance' | translate"
            [submitLabel]="'common.add' | translate"
            [sending]="allowanceSending()"
            [error]="allowanceError()"
            [(visible)]="allowanceOpen"
            (submit)="addAllowance()"
          >
            <div class="u-column u-gap-3">
              @if (allowanceInvalid()) {
                <p class="field__error" role="alert">{{ 'common.required_field' | translate }}</p>
              }
              <app-field label="hr.salaries.detail.type" controlId="allowance-type" [required]="true">
                <p-select inputId="allowance-type" appendTo="body" [options]="allowanceTypeOptions" optionLabel="label" optionValue="value" [(ngModel)]="allowanceForm.type" name="type" styleClass="u-full" />
              </app-field>
              <app-field label="hr.salaries.detail.description" controlId="allowance-name" [required]="true">
                <input pInputText id="allowance-name" type="text" [(ngModel)]="allowanceForm.name" name="name" [maxlength]="255" class="u-full" />
              </app-field>
              <app-field label="hr.salaries.detail.amount" controlId="allowance-amount" [required]="true">
                <p-inputnumber inputId="allowance-amount" [(ngModel)]="allowanceForm.amount" name="amount" [min]="0" mode="decimal" [minFractionDigits]="2" [useGrouping]="false" styleClass="u-full" />
              </app-field>
              <app-field label="common.description" controlId="allowance-desc">
                <textarea pInputTextarea id="allowance-desc" rows="2" [(ngModel)]="allowanceForm.description" name="allowance_description" [maxlength]="500" class="u-full"></textarea>
              </app-field>
            </div>
          </app-form-dialog>
        </app-print-sheet>
      }
    </app-async-page>
  `,
})
export class SalaryDetailPage {
  /** T6-10: edit + add-allowance live in the header More menu */
  readonly moreMenuItems = computed<MenuItem[]>(() => [
    { label: this.translate.instant('common.edit'), routerLink: ['/hr/salaries', this.id(), 'edit'] },
    { label: this.translate.instant('hr.salaries.detail.add_allowance'), command: () => { this.openAllowance(); } },
  ]);

  /** T6-71.6: individual payslip PDF — authenticated blob download */
  downloadPayslip(): void {
    void firstValueFrom(this.api.getBlob(`/api/v1/hr/salaries/${this.id()}/payslip`))
      .then((blob) => {
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.href = url;
        link.download = 'payslip.pdf';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
      })
      .catch(() => { this.toast.apiError('errors.generic'); });
  }

  private api = inject(ApiClient);
  private toast = inject(ToastService);
  private translate = inject(TranslateService);

  id = input.required<number>();
  sending = signal(false);

  /** add-allowance dialog (T3-90) — visible while the salary is not paid */
  allowanceOpen = signal(false);
  allowanceSending = signal(false);
  allowanceError = signal<HttpErrorResponse | null>(null);
  allowanceInvalid = signal(false);
  allowanceForm = { type: 'bonus', name: '', amount: null as number | null, description: '' };

  readonly allowanceTypeOptions = [
    { label: this.translate.instant('hr.salaries.allowance.deduction'), value: 'deduction' },
    { label: this.translate.instant('hr.salaries.allowance.bonus'), value: 'bonus' },
  ];

  data = resource<SalaryDetail, { id: number }>({
    params: () => ({ id: this.id() }),
    loader: ({ params }) => firstValueFrom(this.api.get<SalaryDetail>('/hr/salaries/' + params.id)),
  });

  /** data.value() throws on an errored resource (e.g. 404) — null keeps the template alive */
  readonly safeValue = computed(() => {
    try {
      return this.data.value() ?? null;
    } catch {
      return null;
    }
  });

  itemTitle(item: SalaryDetail): string {
    return `${item.employee ?? '—'} — ${item.month}/${item.year}`;
  }

  keyValues(item: SalaryDetail): { label: string; value: string }[] {
    const values = [
      { label: 'hr.salaries.detail.employee', value: item.employee ?? '—' },
      { label: 'hr.salaries.detail.period', value: `${item.month}/${item.year}` },
      { label: 'hr.salaries.col_base', value: item.base_salary },
      { label: 'hr.salaries.col_attendance', value: String(item.attendance_days) },
      { label: 'hr.salaries.col_absence', value: String(item.absence_days) },
      { label: 'hr.salaries.detail.late_days', value: String(item.late_days) },
      { label: 'hr.salaries.detail.leave_days', value: String(item.leave_days) },
      { label: 'hr.salaries.detail.calculated', value: item.calculated_salary },
      { label: 'hr.salaries.detail.deductions', value: item.deductions },
      { label: 'hr.salaries.detail.bonuses', value: item.bonuses },
      { label: 'hr.salaries.col_final', value: item.final_salary },
    ];
    if (item.approved_at) {
      values.push({ label: 'hr.salaries.detail.approved_at', value: this.formatDate(item.approved_at, item.approved_by) });
    }
    if (item.paid_at) {
      values.push({ label: 'hr.salaries.detail.paid_at', value: this.dt.transform(item.paid_at) });
    }
    if (item.notes) {
      values.push({ label: 'hr.salaries.notes', value: item.notes });
    }
    return values;
  }

  private readonly dt = new DatetimePipe();

  private formatDate(at: string, by: string | null): string {
    return by ? `${this.dt.transform(at)} — ${by}` : this.dt.transform(at);
  }

  openAllowance(): void {
    this.allowanceError.set(null);
    this.allowanceInvalid.set(false);
    this.allowanceForm = { type: 'bonus', name: '', amount: null, description: '' };
    this.allowanceOpen.set(true);
  }

  addAllowance(): void {
    if (!this.allowanceForm.name.trim() || this.allowanceForm.amount === null) {
      this.allowanceInvalid.set(true);
      return;
    }
    this.allowanceInvalid.set(false);
    this.allowanceSending.set(true);
    void firstValueFrom(
      this.api.action(`/hr/salaries/${this.id()}/allowance`, {
        type: this.allowanceForm.type,
        name: this.allowanceForm.name.trim(),
        amount: this.allowanceForm.amount,
        description: this.allowanceForm.description.trim() || null,
      }),
    )
      .then((res) => {
        this.allowanceSending.set(false);
        this.allowanceOpen.set(false);
        this.toast.success(res.message);
        this.data.reload();
      })
      .catch((err: unknown) => {
        this.allowanceSending.set(false);
        this.allowanceError.set((err as HttpErrorResponse));
      });
  }

  act(path: string, body: Record<string, unknown> = {}): void {
    this.sending.set(true);
    void firstValueFrom(this.api.action(`/hr/salaries/${this.id()}/${path}`, body)).then((res) => {
      this.sending.set(false);
      this.toast.success(res.message);
      this.data.reload();
    }).catch((err: unknown) => {
      this.sending.set(false);
      this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic', (err as HttpErrorResponse).error?.code);
    });
  }
}
