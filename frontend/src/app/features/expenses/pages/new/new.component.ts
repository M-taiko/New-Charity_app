import { ChangeDetectionStrategy, Component, computed, effect, inject, input, resource, signal, viewChild } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
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
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { FormSectionComponent } from '@shared/ui/form-section/form-section.component';
import { FieldComponent } from '@shared/ui/field/field.component';
import { serverFieldError } from '@shared/lib/server-field-errors';
import { DirectionPickerComponent } from '@shared/ui/direction-picker/direction-picker.component';
import { LineItemsEditorComponent } from '@shared/ui/line-items-editor/line-items-editor.component';
import { AttachmentFieldComponent } from '@shared/ui/attachment-field/attachment-field.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import type { ActionResponse, DirectionNode, ItemResponse} from '@shared/models/api.models';
import type { ListResponse } from '@shared/models/api.models';

interface UserCustody { id: number; reason: string; remaining: string; total_available: string }
interface ApprovedCase { id: number; name: string }

/**
 * Expense form (plan 20, T27/T30, T3-21): direction mandatory to the last
 * level, source switch custody/treasury by permission, spill hints, line items
 * with a non-blocking total warning, attachment, the required expense_type
 * switch (general | social_case) with an approved-case picker, and
 * remove_attachment on edit.
 */
@Component({
  selector: 'app-expense-form',
  standalone: true,
  imports: [TranslatePipe, RouterLink, FormsModule, ButtonModule, InputNumberModule, InputTextModule, TextareaModule, SelectModule, DatePickerModule, PageHeaderComponent, AsyncPageComponent, FormSectionComponent, FieldComponent, DirectionPickerComponent, LineItemsEditorComponent, AttachmentFieldComponent, MoneyPipe, DatetimePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (isEdit() && safeExisting(); as e) {
      <div class="detail-grid__context u-mb-2">
        <span class="u-bidi">#{{ e.id }}</span>
        <span class="u-bidi">{{ e.amount | money }}</span>
        <span class="u-bidi">{{ e.expense_date | datetime: false }}</span>
        @if (e.reviewed) { <span class="stamp stamp--info">{{ 'enums.review_state.reviewed' | translate }}</span> }
      </div>
    }
    <app-page-header
      [title]="isEdit() ? ('expense.form.edit_title' | translate) : ('expense.form.create_title' | translate)"
      [subtitle]="isEdit() && safeExisting() ? '#' + safeExisting()!.id : ''"
    >
      <div actions>
        <p-button [label]="'common.back_to_list' | translate" size="small" [text]="true" routerLink="/expenses" />
      </div>
    </app-page-header>
    <app-async-page [resource]="existing" variant="form" [backLink]="['/expenses']">
      <form (ngSubmit)="submit()" #f="ngForm">
        <app-form-section title="expense.form.section_basics">
          @if (!isEdit()) {
            <app-field label="expense.form.source" controlId="source">
              <p-select appendTo="body" inputId="source" [options]="sourceOptions" optionLabel="label" optionValue="value" [(ngModel)]="source" name="source" styleClass="u-full" (ngModelChange)="onSource()" />
            </app-field>
            @if (source() === 'custody') {
              <app-field label="expense.form.custody" controlId="custody" [required]="true" [error]="serverFieldError(error(), 'custody_id')">
                <p-select appendTo="body" inputId="custody" [options]="custodyOptions()" optionLabel="reason" optionValue="id" [(ngModel)]="custodyId" name="custody" required styleClass="u-full" />
                @if (selectedCustody(); as c) {
                  <span class="field__hint u-bidi">
                    {{ 'expense.form.selected_balance' | translate }}: {{ c.remaining | money }} —
                    {{ 'expense.form.total_available' | translate }}: {{ c.total_available | money }}
                  </span>
                  <span class="field__hint">{{ 'expense.form.spill_hint' | translate }}</span>
                }
              </app-field>
            } @else {
              <app-field label="expense.form.treasury" controlId="treasury" [required]="true" [error]="serverFieldError(error(), 'treasury_id')">
                <p-select appendTo="body" inputId="treasury" [options]="treasuryOptions()" optionLabel="name" optionValue="id" [(ngModel)]="treasuryId" name="treasury" [required]="source() === 'treasury'" styleClass="u-full" />
              </app-field>
            }
          }
          <app-field label="expense.form.type" controlId="type" [required]="true" [error]="serverFieldError(error(), 'expense_type')">
            <p-select appendTo="body" inputId="type" [options]="expenseTypeOptions" optionLabel="label" optionValue="value" [(ngModel)]="expenseType" name="type" required styleClass="u-full" (ngModelChange)="onExpenseType()" />
          </app-field>
          @if (expenseType() === 'social_case') {
            <app-field
              label="expense.form.case" controlId="social-case" [required]="true"
              [error]="caseError() ? ('expense.form.case_required' | translate) : serverFieldError(error(), 'social_case_id')"
            >
              <p-select appendTo="body" inputId="social-case" [options]="caseOptions()" optionLabel="name" optionValue="id" [(ngModel)]="socialCaseId" name="social_case_id" [filter]="true" required styleClass="u-full" [placeholder]="'expense.form.case_placeholder' | translate" />
            </app-field>
          }
          <app-field label="expense.form.date" controlId="date" [required]="true" [error]="serverFieldError(error(), 'expense_date')">
            <p-datepicker appendTo="body" inputId="date" name="date" [(ngModel)]="expenseDate" dateFormat="yy-mm-dd" [showIcon]="true" required styleClass="u-full" />
          </app-field>
          <app-field label="expense.form.amount" controlId="amount" [required]="true" [error]="serverFieldError(error(), 'amount')">
            <p-inputnumber inputId="amount" name="amount" [(ngModel)]="amount" mode="decimal" [minFractionDigits]="2" [min]="0.01" required styleClass="u-full" />
          </app-field>
        </app-form-section>

        <app-form-section title="expense.form.section_direction" class="u-mt-4">
          <app-field
            label="expense.form.direction" [required]="true"
            [error]="directionError() ? ('direction.picker.incomplete' | translate) : null"
          >
            @if (directionTree().length) {
              <app-direction-picker #picker [tree]="directionTree()" (selectionChange)="onDirection($event)" idBase="exp" />
            }
          </app-field>
        </app-form-section>

        <app-form-section title="expense.form.section_details" class="u-mt-4">
          <app-field label="expense.form.description" controlId="description" [required]="true" [error]="serverFieldError(error(), 'description')">
            <textarea pTextarea id="description" name="description" rows="2" [(ngModel)]="description" required class="u-full"></textarea>
          </app-field>
          <app-field label="expense.form.location" controlId="location" [error]="serverFieldError(error(), 'location')">
            <input pInputText id="location" name="location" [(ngModel)]="location" class="u-full" />
          </app-field>
        </app-form-section>

        <app-form-section title="expense.form.section_items" class="u-mt-4">
          <app-line-items-editor #lineItems [amount]="amount() ?? 0" (itemsChange)="items = $event" />
        </app-form-section>

        <app-form-section title="expense.form.section_attachment" class="u-mt-4">
          <app-attachment-field
            #attachmentField
            [current]="currentAttachmentName()"
            [downloadUrl]="isEdit() && safeExisting()?.has_attachment ? attachmentDownloadUrl() : null"
            [canRemove]="isEdit()"
            (fileChange)="file = $event"
          />
        </app-form-section>

        @if (alert(); as msg) { <div class="dialog__alert" role="alert">{{ msg }}</div> }
        <div class="sticky-actions">
          <p-button [label]="'common.back' | translate" severity="secondary" [text]="true" routerLink="/expenses" />
          <p-button type="submit" [label]="'expense.form.submit' | translate" [loading]="sending()" [disabled]="f.invalid || sending()" />
        </div>
      </form>
    </app-async-page>
  `,
})
export class ExpenseFormPage {
  private http = inject(HttpClient);
  private router = inject(Router);
  private toast = inject(ToastService);
  private auth = inject(AuthService);
  private translate = inject(TranslateService);

  id = input<number | undefined>(undefined);
  isEdit = computed(() => this.id() !== undefined);

  source = signal<'custody' | 'treasury'>('custody');
  custodyId = signal<number | null>(null);
  treasuryId = signal<number | null>(null);
  expenseType = signal<'general' | 'social_case'>('general');
  socialCaseId = signal<number | null>(null);
  amount = signal<number | null>(null);
  expenseDate = signal(new Date().toISOString().slice(0, 10));
  description = signal('');
  location = signal('');
  categoryId = signal<number | null>(null);
  itemId = signal<number | null>(null);
  directionComplete = signal(false);
  directionError = signal(false);
  caseError = signal(false);
  file: File | null = null;
  items: { description: string; quantity: number; unit_price: number }[] = [];
  sending = signal(false);
  error = signal<HttpErrorResponse | null>(null);
  /** exposed to the template — templates see class members, not module imports */
  readonly serverFieldError = serverFieldError;

  /** expense_type is required by the controller (general | social_case) — labels from enums */
  readonly expenseTypeOptions = [
    { label: this.translate.instant('enums.expense_type.general'), value: 'general' },
    { label: this.translate.instant('enums.expense_type.social_case'), value: 'social_case' },
  ];

  lookups = resource<{ direction_tree: DirectionNode[]; treasuries: { id: number; name: string }[] }, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<{ direction_tree: DirectionNode[]; treasuries: { id: number; name: string }[] }>>('/lookups')).then((r) => r.data),
  });

  custodies = resource<UserCustody[], unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<UserCustody[]>>('/user-custodies')).then((r) => r.data),
  });

  /** approved social cases only (the same list the Blade form loads) */
  cases = resource<ApprovedCase[], unknown>({
    loader: () =>
      firstValueFrom(
        this.http.get<ListResponse<ApprovedCase>>('/social-cases', { params: { status: 'approved', per_page: 100 } }),
      )
        .then((r) => r.data)
        .catch(() => [] as ApprovedCase[]),
  });

  /** edit mode: the expense being edited — prefills the whole form */
  existing = resource<ExistingExpense | null, { id: number | undefined }>({
    params: () => ({ id: this.id() }),
    loader: ({ params }) =>
      params.id === undefined
        ? Promise.resolve(null)
        : firstValueFrom(this.http.get<ItemResponse<ExistingExpense>>(`/expenses/${params.id}`)).then((r) => r.data),
  });

  /** existing.value() throws on an errored resource (e.g. 404) — null keeps the header alive */
  readonly safeExisting = computed(() => {
    try {
      return this.existing.value() ?? null;
    } catch {
      return null;
    }
  });

  private picker = viewChild(DirectionPickerComponent);
  private attachmentField = viewChild(AttachmentFieldComponent);
  private seeded = false;

  constructor() {
    // prefill once both the expense and the direction tree are available
    effect(() => {
      const e = this.existing.value();
      const tree = this.lookups.value()?.direction_tree;
      if (!e || !tree?.length || this.seeded) {
        return;
      }
      this.seeded = true;
      this.expenseType.set(e.type === 'social_case' ? 'social_case' : 'general');
      this.amount.set(Number(e.amount) || null);
      this.expenseDate.set(e.expense_date ? e.expense_date.slice(0, 10) : new Date().toISOString().slice(0, 10));
      this.description.set(e.description ?? '');
      this.location.set(e.location ?? '');
      this.source.set(e.source === 'treasury' ? 'treasury' : 'custody');
      // the detail payload exposes the case by name only — match it back to an id
      this.socialCaseId.set(this.caseIdByName(e.case));
      this.picker()?.seed(e.direction_ids.category_id ?? null, e.direction_ids.item_id ?? null);
      // sync the picker's internal completion state with the seeded values
      this.onDirection({
        category_id: e.direction_ids.category_id ?? null,
        item_id: e.direction_ids.item_id ?? null,
        complete: e.direction_ids.item_id != null,
      });
    });

    // the case id can only be resolved once the approved-cases list arrives
    // (the detail payload exposes the case by name only)
    effect(() => {
      const e = this.existing.value();
      const cases = this.cases.value();
      if (!e || !cases?.length || this.socialCaseId() !== null || this.expenseType() !== 'social_case') {
        return;
      }
      this.socialCaseId.set(this.caseIdByName(e.case));
    });
  }

  directionTree(): DirectionNode[] {
    return this.lookups.value()?.direction_tree ?? [];
  }

  custodyOptions(): UserCustody[] {
    return this.custodies.value() ?? [];
  }

  treasuryOptions(): { id: number; name: string }[] {
    return this.lookups.value()?.treasuries ?? [];
  }

  caseOptions(): ApprovedCase[] {
    return this.cases.value() ?? [];
  }

  selectedCustody(): UserCustody | null {
    return this.custodyOptions().find((c) => c.id === this.custodyId()) ?? null;
  }

  currentAttachmentName(): string | null {
    const e = this.safeExisting();
    if (!e?.has_attachment || !e.attachment) {
      return null;
    }
    const parts = e.attachment.split('/');
    return parts[parts.length - 1] || null;
  }

  attachmentDownloadUrl(): string {
    return `/api/v1/expenses/${this.id()}/download-attachment`;
  }

  readonly sourceOptions = [
    { label: this.translate.instant('expense.form.from_custody'), value: 'custody' },
    { label: this.translate.instant('expense.form.from_treasury'), value: 'treasury' },
  ];

  onSource(): void {
    if (this.source() === 'treasury' && !this.auth.can('may_direct_spend')) {
      this.source.set('custody');
    }
  }

  onExpenseType(): void {
    // switching away from social_case clears the case (the API nulls it for general expenses)
    if (this.expenseType() === 'general') {
      this.socialCaseId.set(null);
    }
    this.caseError.set(false);
  }

  onDirection(selection: { category_id: number | null; item_id: number | null; complete: boolean }): void {
    this.categoryId.set(selection.category_id);
    this.itemId.set(selection.item_id);
    this.directionComplete.set(selection.complete);
    this.directionError.set(false);
  }

  /** the general alert text — the server message or the generic fallback key */
  alert(): string | null {
    const err = this.error();
    if (err === null) {
      return null;
    }
    return err.error?.message ?? 'errors.generic';
  }

  submit(): void {
    if (this.amount() === null) {
      return;
    }
    if (!this.directionComplete()) {
      this.directionError.set(true);
      return;
    }
    if (this.expenseType() === 'social_case' && this.socialCaseId() === null) {
      this.caseError.set(true);
      return;
    }
    this.sending.set(true);
    this.error.set(null);
    const body = new FormData();
    body.append('amount', String(this.amount()));
    body.append('expense_date', this.expenseDate());
    body.append('description', this.description());
    if (this.location()) {
      body.append('location', this.location());
    }
    body.append('expense_type', this.expenseType());
    if (this.expenseType() === 'social_case' && this.socialCaseId() !== null) {
      body.append('social_case_id', String(this.socialCaseId()));
    }
    if (this.categoryId() !== null) {
      body.append('expense_category_id', String(this.categoryId()));
    }
    if (this.itemId() !== null) {
      body.append('expense_item_id', String(this.itemId()));
    }
    if (this.source() === 'custody' && this.custodyId() !== null) {
      body.append('custody_id', String(this.custodyId()));
    }
    if (this.source() === 'treasury' && this.treasuryId() !== null) {
      body.append('treasury_id', String(this.treasuryId()));
    }
    if (this.items.length) {
      body.append('line_items', JSON.stringify(this.items));
    }
    if (this.file) {
      body.append('attachment', this.file);
    } else if (this.isEdit() && (this.attachmentField()?.removed() ?? false)) {
      body.append('remove_attachment', 'true');
    }
    const id = this.id();
    const request$ = id
      ? this.http.post<ActionResponse>(`/expenses/${id}/update`, body)
      : this.http.post<ActionResponse>('/expenses', body);
    void firstValueFrom(request$)
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        void this.router.navigateByUrl(id ? `/expenses/${id}` : '/expenses/mine');
      })
      .catch((err: unknown) => {
        this.sending.set(false);
        this.error.set(err instanceof HttpErrorResponse ? err : null);
      });
  }

  /** the detail payload exposes only the case display name */
  private caseIdByName(name: string | null | undefined): number | null {
    if (!name) {
      return null;
    }
    return this.cases.value()?.find((c) => c.name === name)?.id ?? null;
  }
}

interface ExistingExpense {
  id: number;
  type: string | null;
  amount: string;
  expense_date: string | null;
  description: string | null;
  location: string | null;
  source: string | null;
  reviewed: boolean;
  case: string | null;
  attachment: string | null;
  has_attachment: boolean;
  direction_ids: { category_id: number | null; item_id: number | null };
  line_items: unknown;
}
