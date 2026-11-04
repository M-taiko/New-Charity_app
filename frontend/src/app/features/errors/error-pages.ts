import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';
import { ButtonModule } from 'primeng/button';

/** Forbidden (403) — SPA route with the same design (plan 8.2). */
@Component({
  selector: 'app-forbidden',
  standalone: true,
  imports: [TranslatePipe, RouterLink, ButtonModule],
  template: `
    <div class="empty-state" class="empty-state empty-state--page">
      <svg class="empty-state__pattern" aria-hidden="true">
        <use href="assets/patterns/tile.svg#tile" />
      </svg>
      <h1 class="empty-state__title">{{ 'errors.403_title' | translate }}</h1>
      <p class="empty-state__text">{{ 'errors.403_text' | translate }}</p>
      <p-button [label]="'nav.dashboard' | translate" routerLink="/dashboard" styleClass="u-mt-2" />
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForbiddenComponent {}

/** Not found (404). */
@Component({
  selector: 'app-not-found',
  standalone: true,
  imports: [TranslatePipe, RouterLink, ButtonModule],
  template: `
    <div class="empty-state" class="empty-state empty-state--page">
      <svg class="empty-state__pattern" aria-hidden="true">
        <use href="assets/patterns/tile.svg#tile" />
      </svg>
      <h1 class="empty-state__title">{{ 'errors.404_title' | translate }}</h1>
      <p class="empty-state__text">{{ 'errors.404_text' | translate }}</p>
      <p-button [label]="'nav.dashboard' | translate" routerLink="/dashboard" styleClass="u-mt-2" />
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NotFoundComponent {}
