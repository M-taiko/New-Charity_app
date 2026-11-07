import { ChangeDetectionStrategy, Component, effect, inject, input, resource, signal, viewChild } from '@angular/core';
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
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { FormSectionComponent } from '@shared/ui/form-section/form-section.component';
import { FieldComponent } from '@shared/ui/field/field.component';
import { serverFieldError } from '@shared/lib/server-field-errors';
import { DirectionPickerComponent } from '@shared/ui/direction-picker/direction-picker.component';
import { LineItemsEditorComponent } from '@shared/ui/line-items-editor/line-items-editor.component';
import { AttachmentFieldComponent } from '@shared/ui/attachment-field/attachment-field.component';
import { KeyValueListComponent } from '@shared/ui/key-value-list/key-value-list.component';
import { ActionButtonComponent } from '@shared/ui/actions/action-button.component';
import type { ActionResponse, DirectionNode, ItemResponse, ListResponse } from '@shared/models/api.models';

interface ApprovedCase { id: number; name: string }

/**
 * Request an expense edit (plan 20, T16/T24/T30, T3-23): shows the current
 * values, submits requested changes (expense_type + social_case_id, location,
 * reason for the approver); attachment removal applies only on approval.
 */
@Component({
  selector: 'app-edit-request-form',
  standalone: true,
  imports: [TranslatePipe, RouterLink, FormsModule, ButtonModule, InputNumberModule, InputTextModule, TextareaModule, SelectModule, DatePickerModule, PageHeaderComponent, AsyncPageComponent, FormSectionComponent, FieldComponent, DirectionPickerComponent, LineItemsEditorComponent, AttachmentFieldComponent, KeyValueListComponent, ActionButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'expense.form.request_title' | translate" accent="expenses" iconName="receipt-2">
      <div actions>
        <p-button [label]="'common.back_to_list' | translate" size="small" [text]="true" [routerLink]="['/expenses', id()]" />
      </div>
    </app-page-header>
    <app-async-page [resource]="expense" variant="form" [backLink]="['/expenses', id()]">
      @if (expense.value(); as e) {
        <section class="chart-card u-mb-4">
          <div class="chart-card__head"><span class="chart-card__title">{{ 'expense.detail.title' | translate }} #{{ id() }}</span></div>
          <app-key-value-list [items]="[
            { label: 'common.amount', value: e.amount },
            { label: 'expense.detail.description', value: e.description ?? '—' },
            { label: 'expense.list.col_direction', value: e.direction ?? '—' }
          ]" />
        </section>
      }
      <form (ngSubmit)="submit()" #f="ngForm">
        <app-form-section title="expense.form.section_basics">
          <app-field label="expense.form.type" controlId="ertype" [required]="true" [error]="serverFieldError(error(), 'expense_type')">
            <p-select appendTo="body" inputId="ertype" [options]="expenseTypeOptions" optionLabel="label" optionValue="value" [(ngModel)]="expenseType" name="type" required styleClass="u-full" (ngModelChange)="onExpenseType()" />
          </app-field>
          @if (expenseType() === 'social_case') {
            <app-field
              label="expense.form.case" controlId="ercase" [required]="true"
              [error]="caseError() ? ('expense.form.case_required' | translate) : serverFieldError(error(), 'social_case_id')"
            >
              <p-select appendTo="body" inputId="ercase" [options]="caseOptions()" optionLabel="name" optionValue="id" [(ngModel)]="socialCaseId" name="social_case_id" [filter]="true" required styleClass="u-full" [placeholder]="'expense.form.case_placeholder' | translate" />
            </app-field>
          }
          <app-field label="expense.form.amount" controlId="amount" [required]="true" hint="common.hint_max_amount" [error]="serverFieldError(error(), 'amount')">
            <p-inputnumber inputId="amount" name="amount" [(ngModel)]="amount" mode="decimal" [minFractionDigits]="2" [min]="0.01" required styleClass="u-full" />
          </app-field>
          <app-field label="expense.form.date" controlId="date" [error]="serverFieldError(error(), 'expense_date')">
            <p-datepicker appendTo="body" inputId="date" name="date" [(ngModel)]="expenseDate" dateFormat="yy-mm-dd" [showIcon]="true" styleClass="u-full" />
          </app-field>
          <app-field
            label="expense.form.direction" [required]="true" hint="common.hint_direction_full"
            [error]="directionError() ? ('direction.picker.incomplete' | translate) : null"
          >
            @if (tree().length) { <app-direction-picker #picker [tree]="tree()" (selectionChange)="onDirection($event)" idBase="editreq" /> }
          </app-field>
          <app-field label="expense.form.description" controlId="description" [required]="true" [error]="serverFieldError(error(), 'description')">
            <textarea pTextarea id="description" name="description" rows="2" [(ngModel)]="description" required class="u-full"></textarea>
          </app-field>
          <app-field label="expense.form.location" controlId="location" [error]="serverFieldError(error(), 'location')">
            <input pInputText id="location" name="location" [(ngModel)]="location" class="u-full" />
          </app-field>
          <app-field label="expense.form.request_reason" controlId="reason" [error]="serverFieldError(error(), 'reason')">
            <textarea pTextarea id="reason" name="reason" rows="2" [(ngModel)]="reason" [maxlength]="500" [placeholder]="'expense.form.request_reason_placeholder' | translate" class="u-full"></textarea>
          </app-field>
        </app-form-section>
        <app-form-section title="expense.form.line_items.title">
          <app-line-items-editor #lineItems [amount]="amount() ?? 0" (itemsChange)="items = $event" />
        </app-form-section>
        <app-form-section title="expense.form.section_attachment">
          <app-attachment-field
            #attachmentField
            [current]="currentAttachmentName()"
            [downloadUrl]="expense.value()?.has_attachment ? attachmentDownloadUrl() : null"
            [canRemove]="true"
            (fileChange)="file = $event"
          />
        </app-form-section>
        @if (alert(); as msg) { <div class="dialog__alert" role="alert">{{ msg }}</div> }
        <div class="sticky-actions">
          <app-action-button kind="back" label="common.back" [route]="['/expenses', id()]" />
          <p-button type="submit" [label]="'expense.form.submit_request' | translate" [loading]="sending()" [disabled]="f.invalid || sending()" />
        </div>
      </form>
    </app-async-page>
  `,
})
export class EditRequestFormPage {
  private http = inject(HttpClient);
  private router = inject(Router);
  private toast = inject(ToastService);
  private translate = inject(TranslateService);

  id = input.required<number>();
  amount = signal<number | null>(null);
  expenseDate = signal<string>(new Date().toISOString().slice(0, 10));
  description = signal('');
  location = signal('');
  reason = signal('');
  expenseType = signal<'general' | 'social_case'>('general');
  socialCaseId = signal<number | null>(null);
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

  readonly expenseTypeOptions = [
    { label: this.translate.instant('enums.expense_type.general'), value: 'general' },
    { label: this.translate.instant('enums.expense_type.social_case'), value: 'social_case' },
  ];

  expense = resource<EditRequestExpense, { id: number }>({
    params: () => ({ id: this.id() }),
    loader: ({ params }) =>
      firstValueFrom(this.http.get<ItemResponse<EditRequestExpense>>(`/expenses/${params.id}`)).then((r) => r.data),
  });

  lookups = resource<{ direction_tree: DirectionNode[] }, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<{ direction_tree: DirectionNode[] }>>('/lookups')).then((r) => r.data),
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

  private picker = viewChild(DirectionPickerComponent);
  private attachmentField = viewChild(AttachmentFieldComponent);
  private seeded = false;

  constructor() {
    // prefill from the current expense once the direction tree arrives
    effect(() => {
      const e = this.expense.value();
      const tree = this.lookups.value()?.direction_tree;
      if (!e || !tree?.length || this.seeded) {
        return;
      }
      this.seeded = true;
      this.amount.set(Number(e.amount) || null);
      this.description.set(e.description ?? '');
      this.location.set(e.location ?? '');
      this.expenseType.set(e.type === 'social_case' ? 'social_case' : 'general');
      this.expenseDate.set(e.expense_date ? e.expense_date.slice(0, 10) : new Date().toISOString().slice(0, 10));
      this.picker()?.seed(e.direction_ids.category_id ?? null, e.direction_ids.item_id ?? null);
      this.onDirection({
        category_id: e.direction_ids.category_id ?? null,
        item_id: e.direction_ids.item_id ?? null,
        complete: e.direction_ids.item_id != null,
      });
    });

    // the case id can only be resolved once the approved-cases list arrives
    effect(() => {
      const e = this.expense.value();
      const cases = this.cases.value();
      if (!e || !cases?.length || this.socialCaseId() !== null || this.expenseType() !== 'social_case') {
        return;
      }
      this.socialCaseId.set(cases.find((c) => c.name === e.case)?.id ?? null);
    });
  }

  tree(): DirectionNode[] {
    return this.lookups.value()?.direction_tree ?? [];
  }

  caseOptions(): ApprovedCase[] {
    return this.cases.value() ?? [];
  }

  currentAttachmentName(): string | null {
    const e = this.expense.value();
    if (!e?.has_attachment || !e.attachment) {
      return null;
    }
    const parts = e.attachment.split('/');
    return parts[parts.length - 1] || null;
  }

  attachmentDownloadUrl(): string {
    return `/api/v1/expenses/${this.id()}/download-attachment`;
  }

  onExpenseType(): void {
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
    if (this.amount() === null || this.description().trim() === '') {
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
    // multipart: the endpoint accepts a new attachment file (nullable|mimes)
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
    if (this.reason().trim() !== '') {
      body.append('reason', this.reason());
    }
    if (this.categoryId() !== null) {
      body.append('expense_category_id', String(this.categoryId()));
    }
    if (this.itemId() !== null) {
      body.append('expense_item_id', String(this.itemId()));
    }
    if (this.items.length) {
      body.append('line_items', JSON.stringify(this.items));
    }
    if (this.file) {
      body.append('attachment', this.file);
    } else if (this.attachmentField()?.removed() ?? false) {
      body.append('remove_attachment', 'true');
    }
    void firstValueFrom(this.http.post<ActionResponse>(`/expenses/${this.id()}/edit-request`, body))
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        void this.router.navigateByUrl(`/expenses/${this.id()}`);
      })
      .catch((err: unknown) => {
        this.sending.set(false);
        this.error.set(err instanceof HttpErrorResponse ? err : null);
      });
  }
}

interface EditRequestExpense {
  amount: string;
  description: string | null;
  location: string | null;
  direction: string | null;
  type: string | null;
  case: string | null;
  attachment: string | null;
  has_attachment: boolean;
  expense_date: string | null;
  direction_ids: { category_id: number | null; item_id: number | null };
}
