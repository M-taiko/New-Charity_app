import io

def add_form(path, button, dialog_tpl, class_extra):
    s = io.open(path, encoding='utf-8').read()
    s = s.replace("import { ListResponse } from '@shared/models/api.models';",
                  "import { ListResponse, ActionResponse } from '@shared/models/api.models';\nimport { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';\nimport { firstValueFrom } from 'rxjs';\nimport { InputNumberModule } from 'primeng/inputnumber';\nimport { InputTextModule } from 'primeng/inputtext';\nimport { TextareaModule } from 'primeng/textarea';\nimport { SelectModule } from 'primeng/select';\nimport { ToastService } from '@core/errors/toast.service';")
    s = s.replace("    EmptyStateComponent, MoneyPipe, DatetimePipe, EnumLabelPipe, StatusStampComponent,\n  ],",
                  "    EmptyStateComponent, MoneyPipe, DatetimePipe, EnumLabelPipe, StatusStampComponent,\n    FormDialogComponent, InputNumberModule, InputTextModule, TextareaModule, SelectModule,\n  ],")
    s = s.replace("    <app-page-header [title]=", button)
    s = s.replace("    }\n  `,\n})", "    }\n\n" + dialog_tpl + "\n  `,\n})")
    s = s.replace("  onPage(event:", class_extra + "\n  onPage(event:")
    lines = s.split('\n')
    seen = set(); out = []
    for line in lines:
        k = line.strip()
        if k.startswith('import ') and k in seen: continue
        if k.startswith('import '): seen.add(k)
        out.append(line)
    io.open(path, 'w', encoding='utf-8', newline='\n').write('\n'.join(out))
    print('patched', path)

def submit_method(endpoint, form_fields):
    return """
  dialogOpen = signal(false);
  sending = signal(false);
  form = """ + form_fields + """;

  constructor(private http: HttpClient, private toast: ToastService) {}

  submit(): void {
    this.sending.set(true);
    firstValueFrom(this.http.post<ActionResponse>('""" + endpoint + """', this.form)).then((res) => {
      this.sending.set(false);
      this.toast.success(res.message);
      this.dialogOpen.set(false);
      this.data.reload();
    }).catch((err) => {
      this.sending.set(false);
      this.toast.apiError(err.error?.message ?? 'errors.generic');
    });
  }"""

EMP_LOAD = """
  employees: { id: number; name: string }[] = [];
"""

# 1) Attendance
add_form('src/app/features/hr/pages/attendance/attendance.component.ts',
    """    <app-page-header [title]="'hr.attendance.title' | translate">
      <div actions><p-button [label]="'hr.attendance.add' | translate" size="small" (onClick)="dialogOpen.set(true)" /></div>""",
    """    <app-form-dialog [header]="'hr.attendance.add' | translate" [submitLabel]="'common.save' | translate" [(visible)]="dialogOpen" [sending]="sending()" (submit)="submit()">
      <div class="u-column u-gap-3">
        <div class="field"><label class="field__label" for="att-emp">{{ 'hr.employees.user' | translate }} *</label>
          <p-select inputId="att-emp" [options]="employees" optionLabel="name" optionValue="id" [(ngModel)]="form.employee_id" styleClass="u-full" /></div>
        <div class="field"><label class="field__label">{{ 'hr.attendance.status' | translate }} *</label>
          <p-select [options]="statusOptions" optionLabel="label" optionValue="value" [(ngModel)]="form.status" styleClass="u-full" /></div>
      </div>
    </app-form-dialog>""",
    submit_method('/hr/attendance', "{ employee_id: null as number | null, date: new Date().toISOString().slice(0, 10), status: 'present' }").replace(
      "  constructor(private http: HttpClient, private toast: ToastService) {}",
      "  statusOptions = [{ label: 'حاضر', value: 'present' }, { label: 'غائب', value: 'absent' }, { label: 'متأخر', value: 'late' }, { label: 'إجازة', value: 'leave' }];\n  employees: { id: number; name: string }[] = [];\n  constructor(private http: HttpClient, private toast: ToastService) {\n    firstValueFrom(this.http.get<ListResponse<{ id: number; name: string | null }>>('/hr/employees', { params: { per_page: '100' } }))\n      .then((page) => (this.employees = page.data.map((e) => ({ id: e.id, name: e.name ?? '#' + e.id }))));\n  }").replace(
      "  submit(): void {\n    this.sending.set(true);",
      "  submit(): void {\n    if (this.form.employee_id === null) { return; }\n    this.sending.set(true);"))

# 2) KPI
add_form('src/app/features/hr/pages/kpi/kpi.component.ts',
    """    <app-page-header [title]="'hr.kpi.title' | translate">
      <div actions><p-button [label]="'hr.kpi.add' | translate" size="small" (onClick)="dialogOpen.set(true)" /></div>""",
    """    <app-form-dialog [header]="'hr.kpi.add' | translate" [submitLabel]="'common.save' | translate" [(visible)]="dialogOpen" [sending]="sending()" (submit)="submit()">
      <div class="u-column u-gap-3">
        <div class="field"><label class="field__label" for="kpi-emp">{{ 'hr.employees.user' | translate }} *</label>
          <p-select inputId="kpi-emp" [options]="employees" optionLabel="name" optionValue="id" [(ngModel)]="form.employee_id" styleClass="u-full" /></div>
        <div class="field"><label class="field__label" for="kpi-name">{{ 'hr.kpi.name' | translate }} *</label>
          <input pInputText id="kpi-name" [(ngModel)]="form.metric_name" style="width: 100%" /></div>
        <div class="field"><label class="field__label" for="kpi-target">{{ 'hr.kpi.target' | translate }} *</label>
          <p-inputnumber inputId="kpi-target" [(ngModel)]="form.target_value" mode="decimal" styleClass="u-full" /></div>
        <div class="field"><label class="field__label" for="kpi-actual">{{ 'hr.kpi.actual' | translate }} *</label>
          <p-inputnumber inputId="kpi-actual" [(ngModel)]="form.actual_value" mode="decimal" styleClass="u-full" /></div>
      </div>
    </app-form-dialog>""",
    submit_method('/hr/kpi', "{ employee_id: null as number | null, metric_name: '', target_value: null as number | null, actual_value: null as number | null, period_start: new Date().toISOString().slice(0, 10), period_end: new Date().toISOString().slice(0, 10) }").replace(
      "  constructor(private http: HttpClient, private toast: ToastService) {}",
      "  employees: { id: number; name: string }[] = [];\n  constructor(private http: HttpClient, private toast: ToastService) {\n    firstValueFrom(this.http.get<ListResponse<{ id: number; name: string | null }>>('/hr/employees', { params: { per_page: '100' } }))\n      .then((page) => (this.employees = page.data.map((e) => ({ id: e.id, name: e.name ?? '#' + e.id }))));\n  }").replace(
      "  submit(): void {\n    this.sending.set(true);",
      "  submit(): void {\n    if (this.form.employee_id === null || this.form.metric_name === '') { return; }\n    this.sending.set(true);"))

# 3) Salaries calculate
add_form('src/app/features/hr/pages/salaries/salaries.component.ts',
    """    <app-page-header [title]="'hr.salaries.title' | translate">
      <div actions><p-button [label]="'hr.salaries.calculate' | translate" size="small" (onClick)="dialogOpen.set(true)" /></div>""",
    """    <app-form-dialog [header]="'hr.salaries.calculate' | translate" [submitLabel]="'hr.salaries.calculate' | translate" [(visible)]="dialogOpen" [sending]="sending()" (submit)="submit()">
      <div class="u-column u-gap-3">
        <div class="field"><label class="field__label" for="sal-year">{{ 'hr.salaries.year' | translate }} *</label>
          <p-inputnumber inputId="sal-year" [(ngModel)]="form.year" [useGrouping]="false" styleClass="u-full" /></div>
        <div class="field"><label class="field__label" for="sal-month">{{ 'hr.salaries.month' | translate }} *</label>
          <p-inputnumber inputId="sal-month" [(ngModel)]="form.month" [min]="1" [max]="12" [useGrouping]="false" styleClass="u-full" /></div>
        <div class="field"><label class="field__label">{{ 'hr.salaries.method' | translate }} *</label>
          <p-select [options]="methodOptions" optionLabel="label" optionValue="value" [(ngModel)]="form.calculation_method" styleClass="u-full" /></div>
      </div>
    </app-form-dialog>""",
    submit_method('/hr/salaries/calculate', "{ year: new Date().getFullYear(), month: new Date().getMonth() + 1, calculation_method: 'daily_rate' }").replace(
      "  constructor(private http: HttpClient, private toast: ToastService) {}",
      "  methodOptions = [{ label: 'نسبة يومية', value: 'daily_rate' }, { label: 'طريقة الخصم', value: 'deduction_method' }];\n  constructor(private http: HttpClient, private toast: ToastService) {}"))
