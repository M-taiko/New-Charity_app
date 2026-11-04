// New purchase request (plan 20, T3-70): supplier + attachment added.
// T4-23: app-form-section + app-field; T4-26: back-to-list in the header.
import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputNumberModule } from 'primeng/inputnumber';
import { SelectModule } from 'primeng/select';
import { DatePickerModule } from 'primeng/datepicker';
import { ToastService } from '@core/errors/toast.service';
import { ApiClient } from '@core/http/api-client';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { FieldComponent } from '@shared/ui/field/field.component';
import { serverFieldError } from '@shared/lib/server-field-errors';
import { FormSectionComponent } from '@shared/ui/form-section/form-section.component';
import { AttachmentFieldComponent } from '@shared/ui/attachment-field/attachment-field.component';
import { InputTextModule } from 'primeng/inputtext';
import { TextareaModule } from 'primeng/textarea';
import type { ActionResponse, ListResponse } from '@shared/models/api.models';

type PurchaseForm = Record<string, string | number | boolean | null | undefined | string[]>;

interface SupplierOption {
  id: number;
  name: string;
  is_active: boolean;
}

/** The server's fixed purchase categories (PurchaseRequestController@store). */
const CATEGORIES = ['office_supplies', 'equipment', 'services', 'maintenance', 'other'] as const;
const PRIORITIES = ['low', 'medium', 'high', 'urgent'] as const;

/** New purchase request (plan 20, T3-70): supplier + attachment added. */
@Component({
  selector: 'app-purchase-form',
  standalone: true,
  imports: [ButtonModule, DatePickerModule, FormsModule, InputNumberModule, InputTextModule, TextareaModule, AttachmentFieldComponent, PageHeaderComponent, RouterLink, SelectModule, TranslatePipe, FieldComponent, FormSectionComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'procurement.purchase.form.title' | translate">
      <div actions>
        <p-button [label]="'common.back_to_list' | translate" size="small" [text]="true" routerLink="/procurement/purchase" />
      </div>
    </app-page-header>
    <form (ngSubmit)="save()" #f="ngForm">
      <app-form-section>
        <app-field label="procurement.purchase.form.request_title" [controlId]="fid('title')" [required]="true" [error]="serverFieldError(error(), 'title')">
          <input pInputText [id]="fid('title')" name="title" [(ngModel)]="model.title" required class="u-full" />
        </app-field>
        <app-field label="procurement.purchase.form.category" [controlId]="fid('category')" [required]="true" [error]="serverFieldError(error(), 'category')">
          <p-select appendTo="body" [inputId]="fid('category')" [options]="categoryOptions" optionLabel="label" optionValue="value" [(ngModel)]="model.category" name="category" required styleClass="u-full" [placeholder]="'common.choose' | translate" />
        </app-field>
        <app-field label="procurement.purchase.form.priority" [controlId]="fid('priority')" [required]="true" [error]="serverFieldError(error(), 'priority')">
          <p-select appendTo="body" [inputId]="fid('priority')" [options]="priorityOptions" optionLabel="label" optionValue="value" [(ngModel)]="model.priority" name="priority" required styleClass="u-full" [placeholder]="'common.choose' | translate" />
        </app-field>
        <app-field label="procurement.purchase.form.supplier" [controlId]="fid('supplier')" [error]="serverFieldError(error(), 'supplier_id')">
          <p-select appendTo="body" [inputId]="fid('supplier')" [options]="suppliers()" optionLabel="name" optionValue="id" [ngModel]="model.supplier_id" (ngModelChange)="set('supplier_id', $event)" name="supplier_id" styleClass="u-full" [placeholder]="'common.choose' | translate" />
        </app-field>
        <app-field label="procurement.purchase.form.estimated_cost" [controlId]="fid('estimated_cost')" [error]="serverFieldError(error(), 'estimated_cost')">
          <p-inputnumber [inputId]="fid('estimated_cost')" [ngModel]="model.estimated_cost" name="estimated_cost" (ngModelChange)="set('estimated_cost', $event)" mode="decimal" [minFractionDigits]="2" [min]="0" styleClass="u-full" />
        </app-field>
        <app-field label="procurement.purchase.form.needed_by" [controlId]="fid('needed_by')" [error]="serverFieldError(error(), 'needed_by')">
          <p-datepicker appendTo="body" [inputId]="fid('needed_by')" name="needed_by" [(ngModel)]="model.needed_by" view="date" dateFormat="yy-mm-dd" [showIcon]="true" styleClass="u-full" />
        </app-field>
        <app-field label="procurement.purchase.form.details" [controlId]="fid('description')" [error]="serverFieldError(error(), 'description')">
          <textarea pTextarea [id]="fid('description')" name="description" rows="3" [(ngModel)]="model.description" class="u-full"></textarea>
        </app-field>
        <app-field label="procurement.purchase.form.attachment" [error]="serverFieldError(error(), 'attachment')">
          <app-attachment-field [maxSize]="2000000" (fileChange)="attachmentFile = $event" />
        </app-field>
      </app-form-section>
      @if (alert(); as msg) { <div class="dialog__alert" role="alert">{{ msg }}</div> }
      <div class="sticky-actions">
        <p-button type="submit" [label]="'procurement.purchase.form.submit' | translate" [loading]="sending()" [disabled]="f.invalid || sending()" />
      </div>
    </form>
  `,
})
export class PurchaseFormPage {
  private api = inject(ApiClient);
  private router = inject(Router);
  private toast = inject(ToastService);
  private translate = inject(TranslateService);

  id = input<number | undefined>(undefined);
  sending = signal(false);
  error = signal<HttpErrorResponse | null>(null);
  /** exposed to the template — templates see class members, not module imports */
  readonly serverFieldError = serverFieldError;

  attachmentFile: File | null = null;

  suppliers = signal<SupplierOption[]>([]);

  model: PurchaseForm = {
    title: '',
    category: 'office_supplies',
    priority: 'medium',
    estimated_cost: null,
    needed_by: null,
    description: '',
    supplier_id: null,
  };

  // keys written out literally so check-i18n sees every one of them
  readonly categoryOptions = [
    { label: this.translate.instant('procurement.purchase.form.cat_office_supplies'), value: CATEGORIES[0] },
    { label: this.translate.instant('procurement.purchase.form.cat_equipment'), value: CATEGORIES[1] },
    { label: this.translate.instant('procurement.purchase.form.cat_services'), value: CATEGORIES[2] },
    { label: this.translate.instant('procurement.purchase.form.cat_maintenance'), value: CATEGORIES[3] },
    { label: this.translate.instant('procurement.purchase.form.cat_other'), value: CATEGORIES[4] },
  ];
  readonly priorityOptions = [
    { label: this.translate.instant('enums.priority.low'), value: PRIORITIES[0] },
    { label: this.translate.instant('enums.priority.medium'), value: PRIORITIES[1] },
    { label: this.translate.instant('enums.priority.high'), value: PRIORITIES[2] },
    { label: this.translate.instant('enums.priority.urgent'), value: PRIORITIES[3] },
  ];

  fid(name: string): string { return this.selectorId() + '-' + name; }
  selectorId(): string { return 'app-purchase-form'; }
  set(key: string, value: unknown): void { (this.model as Record<string, unknown>)[key] = value; }

  constructor() {
    // Active suppliers for the optional supplier picker (T3-70).
    void firstValueFrom(this.api.list<SupplierOption>('/suppliers', { per_page: 200, status: 'active' }))
      .then((res: ListResponse<SupplierOption>) => { this.suppliers.set(res.data.filter((s) => s.is_active)); })
      .catch(() => { this.suppliers.set([]); });
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
    void firstValueFrom(this.submit$())
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        void this.router.navigateByUrl('/procurement/purchase');
      })
      .catch((err: unknown) => {
        this.sending.set(false);
        this.error.set(err instanceof HttpErrorResponse ? err : null);
      });
  }

  private submit$() {
    const neededBy = this.model.needed_by instanceof Date
      ? this.model.needed_by.toISOString().slice(0, 10)
      : this.model.needed_by ?? null;
    const payload: Record<string, unknown> = {
      title: this.model.title,
      category: this.model.category,
      priority: this.model.priority,
      description: this.model.description,
      estimated_cost: this.model.estimated_cost,
      needed_by: neededBy,
      supplier_id: this.model.supplier_id ?? null,
    };
    // multipart when an attachment is present (controller: nullable file, pdf/jpg/png, max 2MB)
    if (this.attachmentFile) {
      const form = new FormData();
      for (const [key, value] of Object.entries(payload)) {
        if (typeof value === 'string' || typeof value === 'number') {
          form.append(key, String(value));
        }
      }
      form.append('attachment', this.attachmentFile, this.attachmentFile.name);
      return this.api.post<ActionResponse>('/purchase-requests', form);
    }
    return this.api.post<ActionResponse>('/purchase-requests', payload);
  }
}
