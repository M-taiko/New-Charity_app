// Settings (plan 20): organization name, email, logo.
// T4-20: async-page form states; T4-23: app-form-section + app-field.
import { Component, inject, resource, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { FileUploadModule } from 'primeng/fileupload';
import { ToastService } from '@core/errors/toast.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { FieldComponent } from '@shared/ui/field/field.component';
import { serverFieldError } from '@shared/lib/server-field-errors';
import { FormSectionComponent } from '@shared/ui/form-section/form-section.component';
import type { ActionResponse } from '@shared/models/api.models';

/** Settings (plan 20): organization name, email, logo. */
@Component({
  selector: 'app-settings-page',
  standalone: true,
  imports: [TranslatePipe, FormsModule, ButtonModule, InputTextModule, FileUploadModule, PageHeaderComponent, AsyncPageComponent, FieldComponent, FormSectionComponent],
  template: `
    <app-page-header [title]="'settings.title' | translate" />

    <app-async-page [resource]="settings" variant="form">
      <form (ngSubmit)="saveInfo()">
        <app-form-section>
          <app-field label="settings.org_name" controlId="org-name" [error]="serverFieldError(error(), 'organization_name')">
            <input pInputText id="org-name" [(ngModel)]="orgName" name="orgName" class="u-full" />
          </app-field>
          <app-field label="settings.org_email" controlId="org-email" [error]="serverFieldError(error(), 'organization_email')">
            <input pInputText id="org-email" type="email" [(ngModel)]="orgEmail" name="orgEmail" class="u-full" />
          </app-field>
        </app-form-section>
        <p-button type="submit" [label]="'settings.save_info' | translate" [loading]="sending()" />
      </form>
      <form (ngSubmit)="saveLogo()">
        <app-form-section>
          <app-field label="settings.logo" hint="settings.logo_hint" [error]="serverFieldError(error(), 'logo')">
            <p-fileupload mode="basic" [customUpload]="true" (onSelect)="onLogoSelect($event)" [auto]="true" [chooseLabel]="'common.choose_file' | translate" />
          </app-field>
        </app-form-section>
        <p-button type="submit" [label]="'settings.save_logo' | translate" [loading]="sending()" [disabled]="!logoFile" />
      </form>
    </app-async-page>
  `,
})
export class SettingsPage {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  orgName = signal('');
  orgEmail = signal('');
  logoFile: File | null = null;
  sending = signal(false);
  error = signal<HttpErrorResponse | null>(null);
  /** exposed to the template — templates see class members, not module imports */
  readonly serverFieldError = serverFieldError;

  settings = resource<Record<string, string | undefined>, unknown>({
    loader: () => firstValueFrom(this.http.get<Record<string, string | undefined>>('/settings')),
  });

  constructor() {
    // prefill once loaded
    setTimeout(() => {
      const values = this.settings.value();
      if (values) {
        this.orgName.set(values.organization_name ?? '');
        this.orgEmail.set(values.organization_email ?? '');
      }
    }, 800);
  }

  onLogoSelect(event: { files?: File[] }): void {
    this.logoFile = event.files?.[0] ?? null;
  }

  saveInfo(): void {
    this.sending.set(true);
    this.error.set(null);
    void firstValueFrom(this.http.post<ActionResponse>('/settings', { organization_name: this.orgName(), organization_email: this.orgEmail() }))
      .then((res) => { this.sending.set(false); this.toast.success(res.message); })
      .catch((err: unknown) => {
        this.sending.set(false);
        this.error.set(err instanceof HttpErrorResponse ? err : null);
        this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic');
      });
  }

  saveLogo(): void {
    if (!this.logoFile) {
      return;
    }
    this.sending.set(true);
    this.error.set(null);
    const body = new FormData();
    body.append('logo', this.logoFile);
    void firstValueFrom(this.http.post<ActionResponse>('/settings', body))
      .then((res) => { this.sending.set(false); this.toast.success(res.message); })
      .catch((err: unknown) => {
        this.sending.set(false);
        this.error.set(err instanceof HttpErrorResponse ? err : null);
        this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic');
      });
  }
}
