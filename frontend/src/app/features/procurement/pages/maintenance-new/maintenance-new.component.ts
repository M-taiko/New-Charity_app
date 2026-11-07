// New maintenance request (plan 20, T3-71: attachment upload added).
// T4-23: app-form-section + app-field; T4-26: back-to-list in the header.
import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { SelectModule } from 'primeng/select';
import { TextareaModule } from 'primeng/textarea';
import { ToastService } from '@core/errors/toast.service';
import { ApiClient } from '@core/http/api-client';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { FieldComponent } from '@shared/ui/field/field.component';
import { serverFieldError } from '@shared/lib/server-field-errors';
import { FormSectionComponent } from '@shared/ui/form-section/form-section.component';
import { AttachmentFieldComponent } from '@shared/ui/attachment-field/attachment-field.component';
import type { ActionResponse } from '@shared/models/api.models';

type MaintenanceForm = Record<string, string | number | boolean | null | undefined | string[]>;

const PRIORITIES = ['low', 'medium', 'high', 'urgent'] as const;

/** New maintenance request (plan 20, T3-71: attachment upload added). */
@Component({
  selector: 'app-maintenance-form',
  standalone: true,
  imports: [ButtonModule, FormsModule, InputTextModule, TextareaModule, AttachmentFieldComponent, PageHeaderComponent, RouterLink, SelectModule, TranslatePipe, FieldComponent, FormSectionComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'procurement.maintenance.form.title' | translate" accent="procurement" iconName="shopping-cart">
      <div actions>
        <p-button [label]="'common.back_to_list' | translate" size="small" [text]="true" routerLink="/procurement/maintenance" />
      </div>
    </app-page-header>
    <form (ngSubmit)="save()" #f="ngForm">
      <app-form-section>
        <app-field label="procurement.maintenance.form.problem_title" [controlId]="fid('title')" [required]="true" [error]="serverFieldError(error(), 'title')">
          <input pInputText [id]="fid('title')" name="title" [(ngModel)]="model.title" required class="u-full" />
        </app-field>
        <app-field label="procurement.maintenance.form.location" [controlId]="fid('location')" [error]="serverFieldError(error(), 'location')">
          <input pInputText [id]="fid('location')" name="location" [(ngModel)]="model.location" class="u-full" />
        </app-field>
        <app-field label="procurement.maintenance.form.priority" [controlId]="fid('priority')" [required]="true" [error]="serverFieldError(error(), 'priority')">
          <p-select appendTo="body" [inputId]="fid('priority')" [options]="priorityOptions" optionLabel="label" optionValue="value" [(ngModel)]="model.priority" name="priority" required styleClass="u-full" [placeholder]="'common.choose' | translate" />
        </app-field>
        <app-field label="procurement.maintenance.form.description" [controlId]="fid('description')" [error]="serverFieldError(error(), 'description')">
          <textarea pTextarea [id]="fid('description')" name="description" rows="3" [(ngModel)]="model.description" class="u-full"></textarea>
        </app-field>
        <app-field label="procurement.maintenance.form.attachment" [error]="serverFieldError(error(), 'attachment')">
          <app-attachment-field [maxSize]="2000000" (fileChange)="attachmentFile = $event" />
        </app-field>
      </app-form-section>
      @if (alert(); as msg) { <div class="dialog__alert" role="alert">{{ msg }}</div> }
      <div class="sticky-actions">
        <p-button type="submit" [label]="'procurement.maintenance.form.submit' | translate" [loading]="sending()" [disabled]="f.invalid || sending()" />
      </div>
    </form>
  `,
})
export class MaintenanceFormPage {
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

  model: MaintenanceForm = {
    title: '',
    location: '',
    priority: 'medium',
    description: '',
  };

  // keys written out literally so check-i18n sees every one of them
  readonly priorityOptions = [
    { label: this.translate.instant('enums.priority.low'), value: PRIORITIES[0] },
    { label: this.translate.instant('enums.priority.medium'), value: PRIORITIES[1] },
    { label: this.translate.instant('enums.priority.high'), value: PRIORITIES[2] },
    { label: this.translate.instant('enums.priority.urgent'), value: PRIORITIES[3] },
  ];

  fid(name: string): string { return this.selectorId() + '-' + name; }
  selectorId(): string { return 'app-maintenance-form'; }

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
        void this.router.navigateByUrl('/procurement/maintenance');
      })
      .catch((err: unknown) => {
        this.sending.set(false);
        this.error.set(err instanceof HttpErrorResponse ? err : null);
      });
  }

  private submit$() {
    const payload: Record<string, unknown> = {
      title: this.model.title,
      location: this.model.location,
      priority: this.model.priority,
      description: this.model.description,
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
      return this.api.post<ActionResponse>('/maintenance-requests', form);
    }
    return this.api.post<ActionResponse>('/maintenance-requests', payload);
  }
}
