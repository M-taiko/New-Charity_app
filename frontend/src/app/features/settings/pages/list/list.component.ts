import { computed, ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { FileUploadModule } from 'primeng/fileupload';
import { ToastService } from '@core/errors/toast.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { ActionResponse } from '@shared/models/api.models';

/** Settings (plan 20): organization name, email, logo. */
@Component({
  selector: 'app-settings-page',
  standalone: true,
  imports: [TranslatePipe, FormsModule, ButtonModule, InputTextModule, FileUploadModule, PageHeaderComponent],
  template: `
    <app-page-header [title]="'settings.title' | translate" />
    <form class="form-section" (ngSubmit)="saveInfo()">
      <div class="form-section__grid">
        <div class="field">
          <label class="field__label" for="org-name">{{ 'settings.org_name' | translate }}</label>
          <input pInputText id="org-name" [(ngModel)]="orgName" name="orgName" style="width: 100%" />
        </div>
        <div class="field">
          <label class="field__label" for="org-email">{{ 'settings.org_email' | translate }}</label>
          <input pInputText id="org-email" type="email" [(ngModel)]="orgEmail" name="orgEmail" style="width: 100%" />
        </div>
      </div>
      <p-button type="submit" [label]="'settings.save_info' | translate" [loading]="sending()" />
    </form>
    <form class="form-section" (ngSubmit)="saveLogo()">
      <div class="field">
        <span class="field__label">{{ 'settings.logo' | translate }}</span>
        <p-fileupload mode="basic" [customUpload]="true" (onSelect)="onLogoSelect($event)" [auto]="true" chooseLabel="{{ 'common.choose_file' | translate }}" />
        <p class="field__hint">{{ 'settings.logo_hint' | translate }}</p>
      </div>
      <p-button type="submit" [label]="'settings.save_logo' | translate" [loading]="sending()" [disabled]="!logoFile" />
    </form>
  `,
})
export class SettingsPage {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  orgName = signal('');
  orgEmail = signal('');
  logoFile: File | null = null;
  sending = signal(false);

  settings = resource<Record<string, string>, unknown>({
    loader: () => firstValueFrom(this.http.get<Record<string, string>>('/settings')),
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

  onLogoSelect(event: { files: File[] }): void {
    this.logoFile = event.files?.[0] ?? null;
  }

  saveInfo(): void {
    this.sending.set(true);
    void firstValueFrom(this.http.post<ActionResponse>('/settings', { organization_name: this.orgName(), organization_email: this.orgEmail() }))
      .then((res) => { this.sending.set(false); this.toast.success(res.message); })
      .catch((err: HttpErrorResponse) => { this.sending.set(false); this.toast.apiError(err.error?.message ?? 'errors.generic'); });
  }

  saveLogo(): void {
    if (!this.logoFile) {
      return;
    }
    this.sending.set(true);
    const body = new FormData();
    body.append('logo', this.logoFile);
    void firstValueFrom(this.http.post<ActionResponse>('/settings', body))
      .then((res) => { this.sending.set(false); this.toast.success(res.message); })
      .catch((err: HttpErrorResponse) => { this.sending.set(false); this.toast.apiError(err.error?.message ?? 'errors.generic'); });
  }
}
