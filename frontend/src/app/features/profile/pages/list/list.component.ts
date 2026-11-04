// My profile (plan 20, T3-103): name, password, picture upload, animation
// setting. T4-23: app-form-section + app-field; T4-03: the avatar sizing
// moved from inline styles into colocated component styles.
import { Component, computed, inject, signal } from '@angular/core';
import type { OnDestroy } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { SelectModule } from 'primeng/select';
import { ToastService } from '@core/errors/toast.service';
import { TranslateService } from '@ngx-translate/core';
import { AuthService } from '@core/auth/auth.service';
import { MotionService } from '@core/motion/motion.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { KeyValueListComponent } from '@shared/ui/key-value-list/key-value-list.component';
import { FieldComponent } from '@shared/ui/field/field.component';
import { FormSectionComponent } from '@shared/ui/form-section/form-section.component';
import type { ActionResponse } from '@shared/models/api.models';

/** My profile (plan 20, T3-103): name, password, picture upload, animation setting. */
@Component({
  selector: 'app-profile-page',
  standalone: true,
  imports: [TranslatePipe, FormsModule, ButtonModule, InputTextModule, SelectModule, PageHeaderComponent, KeyValueListComponent, FieldComponent, FormSectionComponent],
  styles: `
    .profile-avatar {
      width: 64px;
      height: 64px;
      font-size: var(--text-lg);
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .profile-avatar img {
      width: 100%;
      height: 100%;
      border-radius: var(--radius-full);
      object-fit: cover;
    }

    .profile-picture-controls {
      flex: 1;
      min-width: 220px;
    }

    .profile-file-name {
      max-width: 220px;
    }
  `,
  template: `
    <app-page-header [title]="'profile.title' | translate" />
    <section class="form-section">
      <h3 class="form-section__title">{{ 'profile.title' | translate }}</h3>
      <app-key-value-list [items]="[
        { label: 'profile.email', value: email() },
        { label: 'profile.phone', value: phone() || '—' },
        { label: 'profile.role', value: roles() }
      ]" />
    </section>
    <form (ngSubmit)="saveName()">
      <app-form-section>
        <app-field label="profile.name" controlId="p-name" [required]="true">
          <input pInputText id="p-name" [(ngModel)]="name" name="name" required class="u-full" />
        </app-field>
      </app-form-section>
      <p-button type="submit" [label]="'profile.save_name' | translate" [loading]="sending()" />
    </form>
    <form (ngSubmit)="savePicture()">
      <section class="form-section">
        <h3 class="form-section__title">{{ 'profile.picture' | translate }}</h3>
        <div class="u-flex u-gap-3 u-flex-wrap">
          <span class="user-chip__avatar profile-avatar">
            @if (avatarSrc(); as avatar) {
              <img [src]="avatar" [alt]="name()" />
            } @else {
              {{ initials() }}
            }
          </span>
          <div class="u-column u-gap-2 profile-picture-controls">
            <input
              #picInput
              type="file"
              class="u-visually-hidden"
              id="p-picture"
              accept="image/jpeg,image/png,image/gif"
              (change)="onPictureSelected($event)"
              [attr.aria-label]="'profile.picture_choose' | translate"
            />
            <div class="u-flex u-gap-2 u-flex-wrap">
              <p-button
                type="button"
                size="small"
                [text]="true"
                (onClick)="picInput.click()"
                [label]="currentPicture() ? ('common.replace' | translate) : ('common.choose_file' | translate)"
              />
              @if (selectedFile(); as file) {
                <p-button
                  type="submit"
                  [label]="'profile.save_picture' | translate"
                  [loading]="pictureSending()"
                  [disabled]="pictureSending()"
                />
                <span class="u-small u-truncate profile-file-name">{{ file.name }}</span>
              }
            </div>
            @if (pictureError(); as error) {
              <p class="field__error">{{ error | translate }}</p>
            }
            <p class="field__hint">{{ 'profile.picture_hint' | translate }}</p>
          </div>
        </div>
      </section>
    </form>
    <form (ngSubmit)="savePassword()" #pf="ngForm">
      <app-form-section>
        <app-field label="profile.current_password" controlId="p-current" [required]="true">
          <input pInputText id="p-current" type="password" [(ngModel)]="currentPassword" name="current" required class="u-full" />
        </app-field>
        <app-field label="profile.new_password" controlId="p-new" [required]="true">
          <input pInputText id="p-new" type="password" [(ngModel)]="newPassword" name="new" required minlength="8" class="u-full" />
        </app-field>
        <app-field label="profile.confirm_password" controlId="p-confirm" [required]="true">
          <input pInputText id="p-confirm" type="password" [(ngModel)]="confirmPassword" name="confirm" required class="u-full" />
        </app-field>
      </app-form-section>
      <p-button type="submit" [label]="'profile.update_password' | translate" [loading]="sending()" [disabled]="pf.invalid || sending()" />
    </form>
    <app-form-section>
      <app-field label="profile.motion.full" controlId="p-motion">
        <p-select appendTo="body" inputId="p-motion" [options]="motionOptions" optionLabel="label" optionValue="value" [ngModel]="motion.level()" name="level" (ngModelChange)="setMotion($event)" styleClass="u-full" />
      </app-field>
    </app-form-section>
  `,
})
export class ProfilePage implements OnDestroy {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  private auth = inject(AuthService);
  private motionSvc = inject(MotionService);
  private translate = inject(TranslateService);

  name = signal('');
  email = signal('');
  phone = signal('');
  currentPassword = signal('');
  newPassword = signal('');
  confirmPassword = signal('');
  sending = signal(false);

  // ── profile picture (T3-103) ─────────────────────────────
  /** the picked file, validated like ProfileController@updateProfilePicture */
  readonly selectedFile = signal<File | null>(null);
  /** object URL of the picked file (local preview) */
  readonly previewUrl = signal<string | null>(null);
  /** i18n key of the client-side validation error */
  readonly pictureError = signal<string | null>(null);
  readonly pictureSending = signal(false);

  /** the stored picture from /me — /storage/app/public/<path> as in the Blade profile */
  readonly currentPicture = computed(() => {
    const path = this.auth.me()?.profile_picture;
    return path ? `/storage/app/public/${path}` : null;
  });

  readonly initials = computed(() =>
    (this.auth.me()?.name ?? '?')
      .split(/\s+/)
      .slice(0, 2)
      .map((part) => part.charAt(0))
      .join(''),
  );

  /** preview shows the freshly picked file, else the stored picture */
  readonly avatarSrc = computed(() => this.previewUrl() ?? this.currentPicture());

  ngOnDestroy(): void {
    this.clearPreview();
  }

  readonly motionOptions = [
    { label: this.translate.instant('profile.motion.full'), value: 'full' },
    { label: this.translate.instant('profile.motion.lite'), value: 'lite' },
    { label: this.translate.instant('profile.motion.off'), value: 'off' },
  ];

  roles = computed(() => (this.auth.me()?.roles ?? []).join(', '));

  constructor() {
    const me = this.auth.me();
    this.name.set(me?.name ?? '');
    this.email.set(me?.email ?? '');
    this.phone.set(me?.phone ?? '');
  }

  setMotion(level: string): void {
    this.motionSvc.set(level as 'full' | 'lite' | 'off');
  }

  /** client-side mirror of ProfileController: image, jpeg/png/gif, max 2048 KB */
  onPictureSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    if (!file) {
      return;
    }
    if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
      this.pictureError.set('profile.picture_invalid_type');
      this.clearPreview();
      input.value = '';
      return;
    }
    if (file.size > 2_048 * 1024) {
      this.pictureError.set('profile.picture_too_large');
      this.clearPreview();
      input.value = '';
      return;
    }
    this.pictureError.set(null);
    this.clearPreview();
    this.previewUrl.set(URL.createObjectURL(file));
    this.selectedFile.set(file);
  }

  savePicture(): void {
    const file = this.selectedFile();
    if (!file) {
      return;
    }
    const fd = new FormData();
    fd.append('profile_picture', file);
    this.pictureSending.set(true);
    void firstValueFrom(this.http.post<ActionResponse>('/profile/picture', fd))
      .then((res) => {
        this.pictureSending.set(false);
        this.toast.success(res.message);
        this.clearPreview();
        this.selectedFile.set(null);
        // refresh /me so the topbar/user-chip avatars pick the new picture
        this.auth.loadMe().subscribe({ error: () => undefined });
      })
      .catch((err: unknown) => {
        this.pictureSending.set(false);
        this.toast.apiError(this.errMsg(err));
      });
  }

  private clearPreview(): void {
    const url = this.previewUrl();
    if (url) {
      URL.revokeObjectURL(url);
    }
    this.previewUrl.set(null);
  }

  get motion(): MotionService {
    return this.motionSvc;
  }

  saveName(): void {
    this.sending.set(true);
    void firstValueFrom(this.http.post<ActionResponse>('/profile/name', { name: this.name() }))
      .then((res) => { this.sending.set(false); this.toast.success(res.message); this.auth.loadMe().subscribe(); })
      .catch((err: unknown) => { this.sending.set(false); this.toast.apiError(this.errMsg(err)); });
  }

  savePassword(): void {
    if (this.newPassword() !== this.confirmPassword()) {
      this.toast.error('validation.password_match');
      return;
    }
    this.sending.set(true);
    void firstValueFrom(this.http.post<ActionResponse>('/profile/password', {
      current_password: this.currentPassword(),
      password: this.newPassword(),
      password_confirmation: this.confirmPassword(),
    }))
      .then((res) => { this.sending.set(false); this.toast.success(res.message); })
      .catch((err: unknown) => { this.sending.set(false); this.toast.apiError(this.errMsg(err)); });
  }

  private errMsg(err: unknown): string {
    const body = (err as HttpErrorResponse).error as { message?: string; error?: string } | null;
    return body?.message ?? body?.error ?? 'errors.generic';
  }
}
