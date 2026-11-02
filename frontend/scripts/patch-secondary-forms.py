import io, re

def patch(path, replaces):
    s = io.open(path, encoding='utf-8').read()
    for old, new in replaces:
        if old not in s:
            print(f'MISS in {path}: {old[:60]!r}')
            return False
        s = s.replace(old, new)
    io.open(path, 'w', encoding='utf-8', newline='\n').write(s)
    print('patched', path)
    return True

DIALOG_IMPORT = "import { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';"
DIALOG_STATE = """
  dialogOpen = signal(false);
  sending = signal(false);
  dialogError = signal('');
"""

# ── 1) Quick expense on my-expenses ─────────────────────────────────
p = 'src/app/features/expenses/pages/mine/mine.component.ts'
patch(p, [
    (DIALOG_IMPORT, DIALOG_IMPORT + """
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { InputTextModule } from 'primeng/inputtext';
import { InputNumberModule } from 'primeng/inputnumber';
import { TextareaModule } from 'primeng/textarea';
import { SelectModule } from 'primeng/select';
import { DatePickerModule } from 'primeng/datepicker';
import { ToastService } from '@core/errors/toast.service';
import { ActionResponse } from '@shared/models/api.models';"""),
    ("imports: [\n    TranslatePipe, RouterLink, FormsModule, ButtonModule, InputTextModule, SelectModule,",
     "imports: [\n    TranslatePipe, RouterLink, FormsModule, ButtonModule, InputTextModule, SelectModule,\n    InputNumberModule, TextareaModule, DatePickerModule, FormDialogComponent,"),
    ("'actions': '',", "'actions': 'QUICK'"),
    ("""  onPage(event: { page: number; per_page: number; sort?: string }): void {""",
     DIALOG_STATE + """
  custodies: { id: number; reason: string }[] = [];
  quick = { custody_id: null as number | null, amount: null as number | null, description: '', expense_date: new Date().toISOString().slice(0, 10) };

  ngOnInit(): void {
    firstValueFrom(this.http.get<{ id: number; reason: string }[]>('/user-custodies')).then((list) => (this.custodies = list));
  }

  submitQuick(): void {
    if (this.quick.custody_id === null || this.quick.amount === null) return;
    this.sending.set(true);
    firstValueFrom(this.http.post<ActionResponse>('/expenses/quick-store', {
      ...this.quick, is_quick_expense: true,
    })).then((res) => {
      this.sending.set(false);
      this.toast.success(res.message);
      this.dialogOpen.set(false);
      this.data.reload();
    }).catch((err) => {
      this.sending.set(false);
      this.dialogError.set(err.error?.message ?? 'errors.generic');
    });
  }

  onPage(event: { page: number; per_page: number; sort?: string }): void {"""),
    ("private toast = inject(ToastService);", "private toast = inject(ToastService);\n  private http = inject(HttpClient);"),
])

# insert dialog markup before the final closing of the template
s = io.open(p, encoding='utf-8').read()
s = s.replace("""    }
  `,
})""", """    }

    <app-form-dialog [header]="'expense.list.quick_dialog.title' | translate" [submitLabel]="'common.save' | translate"
                     [(visible)]="dialogOpen" [sending]="sending()" (submit)="submitQuick()">
      <div class="u-column u-gap-3">
        <div class="field">
          <label class="field__label" for="qe-custody">{{ 'expense.form.custody' | translate }} *</label>
          <p-select inputId="qe-custody" [options]="custodies" optionLabel="reason" optionValue="id"
                    [(ngModel)]="quick.custody_id" styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label" for="qe-amount">{{ 'common.amount' | translate }} *</label>
          <p-inputnumber inputId="qe-amount" [(ngModel)]="quick.amount" mode="decimal" [minFractionDigits]="2" [min]="0.01" styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label" for="qe-desc">{{ 'common.description' | translate }} *</label>
          <input pInputText id="qe-desc" [(ngModel)]="quick.description" style="width: 100%" />
        </div>
      </div>
    </app-form-dialog>
  `,
})""")
# header button
s = s.replace("'actions': 'QUICK',", "'actions': \"<p-button [label]=\\\"'expense.list.quick' | translate\\\" size=\\\"small\\\" (onClick)=\\\"dialogOpen.set(true)\\\" />\",")
io.open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('quick-expense done')
