import { ChangeDetectionStrategy, Component, computed, effect, inject, input, resource, signal, viewChild } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
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
import { DirectionPickerComponent } from '@shared/ui/direction-picker/direction-picker.component';
import { LineItemsEditorComponent } from '@shared/ui/line-items-editor/line-items-editor.component';
import { AttachmentFieldComponent } from '@shared/ui/attachment-field/attachment-field.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { ActionResponse, DirectionNode, ItemResponse} from '@shared/models/api.models';
import { ListResponse } from '@shared/models/api.models';

interface UserCustody { id: number; reason: string; remaining: string; total_available: string }

/**
 * Expense form (plan 20, T27/T30): direction mandatory to the last level,
 * source switch custody/treasury by permission, spill hints, line items with
 * a non-blocking total warning, attachment.
 */
@Component({
  selector: 'app-expense-form',
  standalone: true,
  imports: [TranslatePipe, RouterLink, FormsModule, ButtonModule, InputNumberModule, InputTextModule, TextareaModule, SelectModule, DatePickerModule, PageHeaderComponent, DirectionPickerComponent, LineItemsEditorComponent, AttachmentFieldComponent, MoneyPipe, DatetimePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (isEdit() && existing.value(); as e) {
      <div class="detail-grid__context u-mb-2">
        <span class="u-bidi">#{{ e.id }}</span>
        <span class="u-bidi">{{ e.amount | money }}</span>
        <span class="u-bidi">{{ e.expense_date | datetime: false }}</span>
        @if (e.reviewed) { <span class="stamp stamp--info">{{ 'enums.review_state.reviewed' | translate }}</span> }
      </div>
    }
    <app-page-header
      [title]="isEdit() ? ('expense.form.edit_title' | translate) : ('expense.form.create_title' | translate)"
      [subtitle]="isEdit() && existing.value() ? '#' + existing.value()!.id : ''"
    />
    <form class="form-section" (ngSubmit)="submit()" #f="ngForm">
      <div class="form-section__title">{{ 'expense.form.section_basics' | translate }}</div>
      <div class="form-section__grid">
        @if (!isEdit()) {
          <div class="field">
            <label class="field__label">{{ 'expense.form.source' | translate }}</label>
            <p-select appendTo="body" inputId="source" [options]="sourceOptions ?? []" optionLabel="label" optionValue="value" [(ngModel)]="source" name="source" styleClass="u-full" (ngModelChange)="onSource()" />
          </div>
          @if (source() === 'custody') {
            <div class="field">
              <label class="field__label" for="custody">{{ 'expense.form.custody' | translate }} *</label>
              <p-select appendTo="body" inputId="custody" [options]="custodyOptions() ?? []" optionLabel="reason" optionValue="id" [(ngModel)]="custodyId" name="custody" required styleClass="u-full" />
              @if (selectedCustody(); as c) {
                <span class="field__hint u-bidi">
                  {{ 'expense.form.selected_balance' | translate }}: {{ c.remaining | money }} —
                  {{ 'expense.form.total_available' | translate }}: {{ c.total_available | money }}
                </span>
                <span class="field__hint">{{ 'expense.form.spill_hint' | translate }}</span>
              }
            </div>
          } @else {
            <div class="field">
              <label class="field__label" for="treasury">{{ 'expense.form.treasury' | translate }} *</label>
              <p-select appendTo="body" inputId="treasury" [options]="treasuryOptions() ?? []" optionLabel="name" optionValue="id" [(ngModel)]="treasuryId" name="treasury" [required]="source() === 'treasury'" styleClass="u-full" />
            </div>
          }
        }
        <div class="field">
          <label class="field__label" for="type">{{ 'expense.form.type' | translate }}</label>
          <p-select appendTo="body" inputId="type" [options]="typeOptions ?? []" optionLabel="label" optionValue="value" [(ngModel)]="type" name="type" styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label" for="date">{{ 'expense.form.date' | translate }} *</label>
          <p-datepicker appendTo="body" inputId="date" name="date" [(ngModel)]="expenseDate" dateFormat="yy-mm-dd" [showIcon]="true" required styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label" for="amount">{{ 'expense.form.amount' | translate }} *</label>
          <p-inputnumber inputId="amount" name="amount" [(ngModel)]="amount" mode="decimal" [minFractionDigits]="2" [min]="0.01" required styleClass="u-full" />
        </div>
      </div>

      <div class="form-section__title u-mt-4">{{ 'expense.form.section_direction' | translate }}</div>
      <div class="field">
        <span class="field__label">{{ 'expense.form.direction' | translate }} *</span>
        @if (directionTree().length) {
          <app-direction-picker #picker [tree]="directionTree()" (selectionChange)="onDirection($event)" idBase="exp" />
        }
        @if (directionError()) {
          <p class="field__error">{{ 'direction.picker.incomplete' | translate }}</p>
        }
      </div>

      <div class="form-section__title u-mt-4">{{ 'expense.form.section_details' | translate }}</div>
      <div class="field">
        <label class="field__label" for="description">{{ 'expense.form.description' | translate }} *</label>
        <textarea pTextarea id="description" name="description" rows="2" [(ngModel)]="description" required style="width: 100%"></textarea>
      </div>

      <div class="form-section__grid">
        <div class="field">
          <label class="field__label" for="location">{{ 'expense.form.location' | translate }}</label>
          <input pInputText id="location" name="location" [(ngModel)]="location" style="width: 100%" />
        </div>
      </div>

      <div class="form-section__title u-mt-4">{{ 'expense.form.section_items' | translate }}</div>
      <app-line-items-editor #lineItems [amount]="amount() ?? 0" (itemsChange)="items = $event" />

      <div class="form-section__title u-mt-4">{{ 'expense.form.section_attachment' | translate }}</div>
      <app-attachment-field (fileChange)="file = $event" />

      @if (error()) { <div class="dialog__alert" role="alert">{{ error() }}</div> }
      <div class="sticky-actions">
        <p-button [label]="'common.back' | translate" severity="secondary" [text]="true" routerLink="/expenses" />
        <p-button type="submit" [label]="'expense.form.submit' | translate" [loading]="sending()" [disabled]="f.invalid || sending()" />
      </div>
    </form>
  `,
})
export class ExpenseFormPage {
  private http = inject(HttpClient);
  private router = inject(Router);
  private toast = inject(ToastService);
  private auth = inject(AuthService);

  id = input<number | undefined>(undefined);
  isEdit = computed(() => this.id() !== undefined);

  source = signal<'custody' | 'treasury'>('custody');
  custodyId = signal<number | null>(null);
  treasuryId = signal<number | null>(null);
  type = signal('general');
  amount = signal<number | null>(null);
  expenseDate = signal(new Date().toISOString().slice(0, 10));
  description = signal('');
  location = signal('');
  categoryId = signal<number | null>(null);
  itemId = signal<number | null>(null);
  directionComplete = signal(false);
  directionError = signal(false);
  file: File | null = null;
  items: { description: string; quantity: number; unit_price: number }[] = [];
  sending = signal(false);
  error = signal('');

  readonly typeOptions = [
    { label: 'مصروف عام', value: 'general' },
    { label: 'حالة اجتماعية', value: 'social_case' },
  ];

  lookups = resource<{ direction_tree: DirectionNode[]; treasuries: { id: number; name: string }[] }, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<{ direction_tree: DirectionNode[]; treasuries: { id: number; name: string }[] }>>('/lookups')).then((r) => r.data),
  });

  custodies = resource<UserCustody[], unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<UserCustody[]>>('/user-custodies')).then((r) => r.data),
  });

  /** edit mode: the expense being edited — prefills the whole form */
  existing = resource<ExistingExpense | null, { id: number | undefined }>({
    params: () => ({ id: this.id() }),
    loader: ({ params }) =>
      params.id === undefined
        ? Promise.resolve(null)
        : firstValueFrom(this.http.get<ItemResponse<ExistingExpense>>(`/expenses/${params.id}`)).then((r) => r.data),
  });

  private picker = viewChild(DirectionPickerComponent);
  private lineItemsRef = viewChild(LineItemsEditorComponent);
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
      this.type.set(e.type ?? 'general');
      this.amount.set(Number(e.amount) || null);
      this.expenseDate.set(e.expense_date ? e.expense_date.slice(0, 10) : new Date().toISOString().slice(0, 10));
      this.description.set(e.description ?? '');
      this.location.set(e.location ?? '');
      this.source.set(e.source === 'treasury' ? 'treasury' : 'custody');
      this.picker()?.seed(e.direction_ids?.category_id ?? null, e.direction_ids?.item_id ?? null);
      // sync the picker's internal completion state with the seeded values
      this.onDirection({
        category_id: e.direction_ids?.category_id ?? null,
        item_id: e.direction_ids?.item_id ?? null,
        complete: e.direction_ids?.item_id != null,
      });
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

  selectedCustody(): UserCustody | null {
    return this.custodyOptions().find((c) => c.id === this.custodyId()) ?? null;
  }

  readonly sourceOptions = [
    { label: 'من العهدة', value: 'custody' },
    { label: 'من الخزينة', value: 'treasury' },
  ];

  onSource(): void {
    if (this.source() === 'treasury' && !this.auth.can('may_direct_spend')) {
      this.source.set('custody');
    }
  }

  onDirection(selection: { category_id: number | null; item_id: number | null; complete: boolean }): void {
    this.categoryId.set(selection.category_id);
    this.itemId.set(selection.item_id);
    this.directionComplete.set(selection.complete);
    this.directionError.set(false);
  }

  submit(): void {
    if (this.amount() === null) {
      return;
    }
    if (!this.directionComplete()) {
      this.directionError.set(true);
      return;
    }
    this.sending.set(true);
    this.error.set('');
    const body = new FormData();
    body.append('amount', String(this.amount()));
    body.append('expense_date', String(this.expenseDate()));
    body.append('description', this.description());
    if (this.location()) {
      body.append('location', this.location());
    }
    body.append('type', this.type());
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
      .catch((err: HttpErrorResponse) => {
        this.sending.set(false);
        this.error.set(err.error?.message ?? 'errors.generic');
      });
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
  direction_ids: { category_id: number | null; item_id: number | null };
  line_items: unknown;
}
