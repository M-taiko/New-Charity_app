import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { HttpErrorResponse } from '@angular/common/http';
import { AuthService } from '@core/auth/auth.service';
import { ApiErrorBody } from '@shared/models/api.models';

/**
 * Login (plan 20). The organization identity on full display: ink-brown side panel
 * with a repeating eight-point star lattice, a large Amiri display title,
 * ledger-rule separators, and a staggered form reveal. Eight colors only,
 * hard edges, no blur — the paper-and-ink aesthetic (plan 9).
 */
@Component({
  selector: 'app-login',
  standalone: true,
  imports: [TranslatePipe, FormsModule, ButtonModule, InputTextModule],
  template: `
    <div class="login">
      <!-- ── Brand panel (ink brown + star lattice) ── -->
      <div class="login__side">
        <svg class="login__lattice" aria-hidden="true">
          <defs>
            <pattern id="star-lattice" width="72" height="72" patternUnits="userSpaceOnUse">
              <g fill="none" stroke="#BEAFA7" stroke-width="0.8" opacity="0.35">
                <path d="M36 4 44.5 27.5 68 36 44.5 44.5 36 68 27.5 44.5 4 36 27.5 27.5Z" />
                <path d="M36 14 40 32 58 36 40 40 36 58 32 40 14 36 32 32Z" />
                <circle cx="36" cy="36" r="3" />
                <path d="M0 0h8v8H0zM64 0h8v8h-8zM0 64h8v8H0zM64 64h8v8h-8z" />
              </g>
            </pattern>
          </defs>
          <rect width="100%" height="100%" fill="url(#star-lattice)" />
        </svg>

        <!-- Ledger margin rule on the reading-start edge -->
        <div class="login__margin-rule" aria-hidden="true"></div>

        <div class="login__side-content">
          <div class="login__monogram-wrap">
            <svg width="96" height="96" viewBox="0 0 64 64" aria-hidden="true" class="login__monogram">
              <rect x="4" y="4" width="56" height="56" fill="none" stroke="#F7F3EF" stroke-width="2" />
              <rect x="8" y="8" width="48" height="48" fill="none" stroke="#BEAFA7" stroke-width="0.8" />
              <text
                x="32"
                y="42"
                text-anchor="middle"
                font-family="Georgia, serif"
                font-size="26"
                font-weight="700"
                fill="#F7F3EF"
                letter-spacing="1.5"
              >
                NS
              </text>
            </svg>
          </div>

          <h1 class="login__side-title">{{ 'app.name' | translate }}</h1>

          <div class="login__rule-sep" aria-hidden="true"><span></span><span></span><span></span></div>

          <p class="login__side-tagline">{{ 'auth.login.org_hint' | translate }}</p>

          <div class="login__side-features">
            <div class="login__feature">
              <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                aria-hidden="true"
              >
                <path
                  d="M21 14h-9v9M3 10h9V1M14 3.5 21 10M10 20.5 3 14"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
              <span>{{ 'auth.login.feat_track' | translate }}</span>
            </div>
            <div class="login__feature">
              <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                aria-hidden="true"
              >
                <path
                  d="M9 12l2 2 4-4M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9c2.39 0 4.68.95 6.36 2.64L21 8v4z"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
              <span>{{ 'auth.login.feat_secure' | translate }}</span>
            </div>
          </div>
        </div>

        <p class="login__side-version u-bidi">v1.0 — {{ 'app.name' | translate }}</p>
      </div>

      <!-- ── Form panel (cream paper) ── -->
      <div class="login__form-side">
        <form class="login__form" (ngSubmit)="submit()" #form="ngForm">
          <!-- Small brand header (mobile: this is the only branding) -->
          <div class="login__form-brand">
            <svg width="36" height="36" viewBox="0 0 64 64" aria-hidden="true">
              <rect x="4" y="4" width="56" height="56" rx="8" fill="#F7F3EF" stroke="#5D3722" stroke-width="3" />
              <text
                x="32"
                y="42"
                text-anchor="middle"
                font-family="Georgia, serif"
                font-size="26"
                font-weight="700"
                fill="#5D3722"
              >
                NS
              </text>
            </svg>
          </div>

          <h2 class="login__form-title">{{ 'auth.login.title' | translate }}</h2>
          <p class="login__form-subtitle">{{ 'auth.login.subtitle' | translate }}</p>

          @if (error()) {
            <div class="login__error" role="alert">
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
              >
                <path
                  d="M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
              {{ error() }}
            </div>
          }

          <div class="login__input-group">
            <label class="login__label" for="email">{{ 'auth.login.email' | translate }}</label>
            <div class="login__input-wrap">
              <svg
                class="login__input-icon"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.6"
                aria-hidden="true"
              >
                <path
                  d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
                <path d="m22 6-10 7L2 6" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              <input
                pInputText
                id="email"
                type="email"
                name="email"
                [(ngModel)]="email"
                name="email"
                required
                email
                autocomplete="username"
                class="login__input"
                [placeholder]="'auth.login.email_placeholder' | translate"
              />
            </div>
          </div>

          <div class="login__input-group">
            <label class="login__label" for="password">{{ 'auth.login.password' | translate }}</label>
            <div class="login__input-wrap">
              <svg
                class="login__input-icon"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.6"
                aria-hidden="true"
              >
                <rect
                  x="3"
                  y="11"
                  width="18"
                  height="11"
                  rx="2"
                  ry="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
                <path d="M7 11V7a5 5 0 0 1 10 0v4" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              <input
                pInputText
                id="password"
                [type]="showPassword() ? 'text' : 'password'"
                name="password"
                [(ngModel)]="password"
                name="password"
                required
                autocomplete="current-password"
                class="login__input"
                [placeholder]="'auth.login.password_placeholder' | translate"
              />
              <button
                type="button"
                class="login__eye"
                (click)="showPassword.set(!showPassword())"
                [attr.aria-label]="
                  (showPassword() ? 'auth.login.hide_password' : 'auth.login.show_password') | translate
                "
                [attr.aria-pressed]="showPassword()"
              >
                @if (showPassword()) {
                  <svg
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.6"
                    aria-hidden="true"
                  >
                    <path
                      d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                    <path d="m1 1 22 22" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                } @else {
                  <svg
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.6"
                    aria-hidden="true"
                  >
                    <path
                      d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                    <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                }
              </button>
            </div>
          </div>

          <label class="login__remember" for="remember">
            <input type="checkbox" id="remember" name="remember" [(ngModel)]="remember" class="login__checkbox" />
            <span class="login__checkbox-box" aria-hidden="true"></span>
            <span>{{ 'auth.login.remember' | translate }}</span>
          </label>

          <button
            type="submit"
            class="login__submit"
            [disabled]="form.invalid || sending()"
            [class.is-sending]="sending()"
          >
            @if (sending()) {
              <span class="login__spinner" aria-hidden="true"></span>
            }
            {{ 'auth.login.submit' | translate }}
          </button>

          <div class="login__ledger-rule" aria-hidden="true"></div>
          <p class="login__footer-note">{{ 'auth.login.footer' | translate }}</p>
        </form>
      </div>
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LoginComponent {
  private auth = inject(AuthService);
  private router = inject(Router);

  email = signal('');
  password = signal('');
  remember = signal(false);
  sending = signal(false);
  error = signal('');
  showPassword = signal(false);

  submit(): void {
    this.error.set('');
    this.sending.set(true);
    this.auth.login(this.email(), this.password(), this.remember()).subscribe({
      next: () => {
        const target = new URLSearchParams(window.location.search).get('returnUrl') ?? '/dashboard';
        window.location.assign('/app' + target);
      },
      error: (err: HttpErrorResponse) => {
        this.sending.set(false);
        const body = err.error as ApiErrorBody | null;
        this.error.set(body?.message ?? 'errors.generic');
      },
    });
  }
}
