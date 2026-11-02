import { ChangeDetectionStrategy, Component, computed, inject, input, resource, signal } from '@angular/core';
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
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { DirectionPickerComponent } from '@shared/ui/direction-picker/direction-picker.component';
import { LineItemsEditorComponent } from '@shared/ui/line-items-editor/line-items-editor.component';
import { AttachmentFieldComponent } from '@shared/ui/attachment-field/attachment-field.component';
import { KeyValueListComponent } from '@shared/ui/key-value-list/key-value-list.component';
import { ActionResponse, DirectionNode, ItemResponse } from '@shared/models/api.models';

/**
 * Request an expense edit (plan 20, T16/T24/T30): shows the current values,
 * submits requested changes; attachment removal applies only on approval.
 */
@Component({
  selector: 'app-edit-request-form',
  standalone: true,
  imports: [TranslatePipe, RouterLink, FormsModule, ButtonModule, InputNumberModule, InputTextModule, TextareaModule, SelectModule, DatePickerModule, PageHeaderComponent, DirectionPickerComponent, LineItemsEditorComponent, AttachmentFieldComponent, KeyValueListComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'expense.form.request_title' | translate" />
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
    <form class="form-section" (ngSubmit)="submit()" #f="ngForm">
      <div class="form-section__grid">
        <div class="field">
          <label class="field__label" for="amount">{{ 'expense.form.amount' | translate }}</label>
          <p-inputnumber inputId="amount" name="amount" [(ngModel)]="amount" mode="decimal" [minFractionDigits]="2" [min]="0.01" styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label" for="date">{{ 'expense.form.date' | translate }}</label>
          <p-datepicker appendTo="body" inputId="date" name="date" [(ngModel)]="expenseDate" dateFormat="yy-mm-dd" [showIcon]="true" styleClass="u-full" />
        </div>
      </div>
      <div class="field">
        <span class="field__label">{{ 'expense.form.direction' | translate }}</span>
        @if (tree().length) { <app-direction-picker [tree]="tree()" (selectionChange)="onDirection($event)" idBase="editreq" /> }
      </div>
      <div class="field">
        <label class="field__label" for="description">{{ 'expense.form.description' | translate }}</label>
        <textarea pTextarea id="description" name="description" rows="2" [(ngModel)]="description" style="width: 100%"></textarea>
      </div>
      <div class="form-section__title">{{ 'expense.form.line_items.title' | translate }}</div>
      <app-line-items-editor #lineItems [amount]="amount() ?? 0" (itemsChange)="items = $event" />
      <div class="form-section__title">{{ 'expense.form.remove_attachment' | translate }}</div>
      <app-attachment-field [current]="expense.value()?.has_attachment ? 'attachment' : null" [canRemove]="true" (remove)="removeAttachment.set(true)" />
      @if (error()) { <div class="dialog__alert" role="alert">{{ error() }}</div> }
      <div class="sticky-actions">
        <p-button [label]="'common.back' | translate" severity="secondary" [text]="true" [routerLink]="['/expenses', id()]" />
        <p-button type="submit" [label]="'expense.form.submit_request' | translate" [loading]="sending()" [disabled]="sending()" />
      </div>
    </form>
  `,
})
export class EditRequestFormPage {
  private http = inject(HttpClient);
  private router = inject(Router);
  private toast = inject(ToastService);

  id = input.required<number>();
  amount = signal<number | null>(null);
  expenseDate = signal<string>(new Date().toISOString().slice(0, 10));
  description = signal('');
  categoryId = signal<number | null>(null);
  itemId = signal<number | null>(null);
  directionComplete = signal(false);
  removeAttachment = signal(false);
  items: { description: string; quantity: number; unit_price: number }[] = [];
  sending = signal(false);
  error = signal('');

  expense = resource<{ amount: string; description: string | null; direction: string | null; has_attachment: boolean }, { id: number }>({
    params: () => ({ id: this.id() }),
    loader: ({ params }) =>
      firstValueFrom(this.http.get<ItemResponse<{ amount: string; description: string | null; direction: string | null; has_attachment: boolean }>>(`/expenses/${params.id}`)).then((r) => r.data),
  });

  lookups = resource<{ direction_tree: DirectionNode[] }, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<{ direction_tree: DirectionNode[] }>>('/lookups')).then((r) => r.data),
  });

  tree(): DirectionNode[] {
    return this.lookups.value()?.direction_tree ?? [];
  }

  onDirection(selection: { category_id: number | null; item_id: number | null; complete: boolean }): void {
    this.categoryId.set(selection.category_id);
    this.itemId.set(selection.item_id);
    this.directionComplete.set(selection.complete);
  }

  submit(): void {
    this.sending.set(true);
    this.error.set('');
    const body: Record<string, unknown> = {};
    if (this.amount() !== null) {
      body.amount = this.amount();
    }
    if (this.expenseDate()) {
      body.expense_date = this.expenseDate();
    }
    if (this.description()) {
      body.description = this.description();
    }
    if (this.directionComplete() && this.categoryId() !== null) {
      body.expense_category_id = this.categoryId();
    }
    if (this.itemId() !== null) {
      body.expense_item_id = this.itemId();
    }
    if (this.items.length) {
      body.line_items = this.items;
    }
    if (this.removeAttachment()) {
      body.remove_attachment = true;
    }
    void firstValueFrom(this.http.post<ActionResponse>(`/expenses/${this.id()}/edit-request`, body))
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        void this.router.navigateByUrl(`/expenses/${this.id()}`);
      })
      .catch((err: HttpErrorResponse) => {
        this.sending.set(false);
        this.error.set(err.error?.message ?? 'errors.generic');
      });
  }
}
