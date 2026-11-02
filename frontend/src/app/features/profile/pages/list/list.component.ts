import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { SelectModule } from 'primeng/select';
import { ToastService } from '@core/errors/toast.service';
import { AuthService } from '@core/auth/auth.service';
import { MotionService } from '@core/motion/motion.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { KeyValueListComponent } from '@shared/ui/key-value-list/key-value-list.component';
import { ActionResponse } from '@shared/models/api.models';

/** My profile (plan 20): name, password, picture, animation setting. */
@Component({
  selector: 'app-profile-page',
  standalone: true,
  imports: [TranslatePipe, FormsModule, ButtonModule, InputTextModule, SelectModule, PageHeaderComponent, KeyValueListComponent],
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
    <form class="form-section" (ngSubmit)="saveName()">
      <div class="form-section__grid">
        <div class="field">
          <label class="field__label" for="p-name">{{ 'profile.name' | translate }} *</label>
          <input pInputText id="p-name" [(ngModel)]="name" name="name" required style="width: 100%" />
        </div>
      </div>
      <p-button type="submit" [label]="'profile.save_name' | translate" [loading]="sending()" />
    </form>
    <form class="form-section" (ngSubmit)="savePassword()" #pf="ngForm">
      <div class="form-section__grid">
        <div class="field">
          <label class="field__label" for="p-current">{{ 'profile.current_password' | translate }} *</label>
          <input pInputText id="p-current" type="password" [(ngModel)]="currentPassword" name="current" required style="width: 100%" />
        </div>
        <div class="field">
          <label class="field__label" for="p-new">{{ 'profile.new_password' | translate }} *</label>
          <input pInputText id="p-new" type="password" [(ngModel)]="newPassword" name="new" required minlength="8" style="width: 100%" />
        </div>
        <div class="field">
          <label class="field__label" for="p-confirm">{{ 'profile.confirm_password' | translate }} *</label>
          <input pInputText id="p-confirm" type="password" [(ngModel)]="confirmPassword" name="confirm" required style="width: 100%" />
        </div>
      </div>
      <p-button type="submit" [label]="'profile.update_password' | translate" [loading]="sending()" [disabled]="pf.invalid || sending()" />
    </form>
    <section class="form-section">
      <h3 class="form-section__title">{{ 'profile.motion.full' | translate }}</h3>
      <p-select appendTo="body" [options]="motionOptions ?? []" optionLabel="label" optionValue="value" [ngModel]="motion.level()" name="level()" (ngModelChange)="setMotion($event)" styleClass="u-full" />
    </section>
  `,
})
export class ProfilePage {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  private auth = inject(AuthService);
  private motionSvc = inject(MotionService);

  name = signal('');
  email = signal('');
  phone = signal('');
  currentPassword = signal('');
  newPassword = signal('');
  confirmPassword = signal('');
  sending = signal(false);

  readonly motionOptions = [
    { label: 'الحركات: كاملة', value: 'full' },
    { label: 'الحركات: مبسطة', value: 'lite' },
    { label: 'الحركات: معطلة', value: 'off' },
  ];

  roles = computed(() => (this.auth.me()?.roles ?? []).join('، '));

  constructor() {
    const me = this.auth.me();
    this.name.set(me?.name ?? '');
    this.email.set(me?.email ?? '');
    this.phone.set(me?.phone ?? '');
  }

  setMotion(level: string): void {
    this.motionSvc.set(level as 'full' | 'lite' | 'off');
  }

  get motion(): MotionService {
    return this.motionSvc;
  }

  saveName(): void {
    this.sending.set(true);
    void firstValueFrom(this.http.post<ActionResponse>('/profile/name', { name: this.name() }))
      .then((res) => { this.sending.set(false); this.toast.success(res.message); this.auth.loadMe().subscribe(); })
      .catch((err: HttpErrorResponse) => { this.sending.set(false); this.toast.apiError(err.error?.message ?? 'errors.generic'); });
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
      .catch((err: HttpErrorResponse) => { this.sending.set(false); this.toast.apiError(err.error?.message ?? 'errors.generic'); });
  }
}
