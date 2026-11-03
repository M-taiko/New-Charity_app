import type { ElementRef, OnDestroy} from '@angular/core';
import { ChangeDetectionStrategy, Component, effect, inject, signal, viewChild } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';
import { ButtonModule } from 'primeng/button';
import { HttpClient } from '@angular/common/http';
import { PollingService } from '@core/polling/polling.service';
import { LocalStorageService } from '@core/storage/local-storage.service';
import { RelativeTimePipe } from '@shared/pipes/relative-time.pipe';

/**
 * Notification bell (plan 11.15): unread count, dropdown, mark-all-read, and
 * the audible alert (T22) with a mute option remembered per user. The chime
 * is a tiny WebAudio oscillator — no audio file, no external asset. The
 * dropdown is a plain positioned panel closed by outside click (Chrome 109
 * safe, no overlay API dependency).
 */
@Component({
  selector: 'app-notification-bell',
  standalone: true,
  imports: [TranslatePipe, ButtonModule, RelativeTimePipe, RouterLink],
  template: `
    <div class="notification-bell" #root>
      <button
        type="button"
        class="topbar__icon-btn"
        (click)="openSignal.set(!openSignal())"
        [attr.aria-expanded]="openSignal()"
        [attr.aria-label]="'nav.notifications' | translate"
      >
        <svg width="16" height="16" aria-hidden="true">
          <use href="assets/icons/sprite.svg#tabler-bell" />
        </svg>
        @if (unread() > 0) {
          <span class="notification-bell__badge u-bidi">{{ unread() }}</span>
        }
      </button>

      @if (openSignal()) {
        <div class="notification-bell__panel" role="dialog" [attr.aria-label]="'nav.notifications' | translate">
          <div class="u-flex-between" style="padding: var(--space-2) var(--space-3)">
            <strong style="font-size: var(--text-sm)">{{ 'nav.notifications' | translate }}</strong>
            <p-button [label]="'notifications.mark_all' | translate" size="small" [text]="true" (onClick)="markAll()" />
          </div>
          <div class="notification-bell__list">
            @for (item of latest(); track item.id) {
              <a
                class="notification-bell__item"
                [class.notification-bell__item--unread]="!item.is_read"
                [routerLink]="item.link || '/notifications'"
                (click)="openItem(item)"
                style="text-decoration: none; color: inherit"
              >
                <span class="notification-bell__item-title">{{ item.title }}</span>
                <span class="notification-bell__item-text">{{ item.message }}</span>
                <span class="notification-bell__item-text">{{ item.created_at | relativeTime }}</span>
              </a>
            } @empty {
              <p class="u-muted" style="padding: var(--space-4)">{{ 'notifications.empty' | translate }}</p>
            }
          </div>
          <div
            class="u-flex-between"
            style="padding: var(--space-2) var(--space-3); border-block-start: 1px solid var(--color-hairline)"
          >
            <a routerLink="/notifications" style="font-size: var(--text-xs)" (click)="openSignal.set(false)">
              {{ 'notifications.view_all' | translate }}
            </a>
            <button
              type="button"
              class="topbar__icon-btn"
              style="width: 28px; height: 28px"
              (click)="toggleMute()"
              [attr.aria-label]="(muted() ? 'notifications.unmute' : 'notifications.mute') | translate"
            >
              <svg width="14" height="14" aria-hidden="true">
                <use [attr.href]="'assets/icons/sprite.svg#tabler-' + (muted() ? 'closed' : 'bell')" />
              </svg>
            </button>
          </div>
        </div>
      }
    </div>
  `,
  styles: `
    .notification-bell__panel {
      position: absolute;
      top: calc(var(--topbar-h) + 2px);
      inset-inline-end: var(--space-3);
      width: min(380px, 92vw);
      z-index: 1100;
      border: 1px solid var(--color-margin-rule);
      border-radius: var(--radius-l);
      background: var(--color-surface);
      box-shadow: 2px 2px 0 var(--color-hairline);
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NotificationBellComponent implements OnDestroy {
  private polling = inject(PollingService);
  private http = inject(HttpClient);
  private storage = inject(LocalStorageService);
  private root = viewChild<ElementRef<HTMLElement>>('root');

  readonly openSignal = signal(false);
  readonly unread = this.polling.unreadCount;
  readonly latest = this.polling.latest;
  readonly muted = signal(this.storage.get('notifications.muted', false));

  private lastChimeCount = this.storage.get('notifications.lastCount', 0);
  private readonly outsideClick = (event: MouseEvent): void => {
    const el = this.root()?.nativeElement;
    if (el && !el.contains(event.target as Node)) {
      this.openSignal.set(false);
    }
  };

  constructor() {
    document.addEventListener('click', this.outsideClick);

    // T22: loud chime on a NEW unread notification, unless muted
    effect(() => {
      const count = this.unread();
      if (count > this.lastChimeCount && !this.muted()) {
        this.chime();
      }
      this.lastChimeCount = count;
      this.storage.set('notifications.lastCount', count);
    });
  }

  ngOnDestroy(): void {
    document.removeEventListener('click', this.outsideClick);
  }

  toggleMute(): void {
    this.muted.update((v) => !v);
    this.storage.set('notifications.muted', this.muted());
  }

  markAll(): void {
    this.http.post('/notifications/mark-all-read', {}).subscribe(() => this.polling.refreshNow());
  }

  /** close the dropdown, mark the item read, and let the anchor navigate */
  openItem(item: { id: number; is_read: boolean }): void {
    this.openSignal.set(false);
    if (!item.is_read) {
      this.http.post(`/notifications/${item.id}/read`, {}).subscribe({
        complete: () => this.polling.refreshNow(),
      });
    }
  }

  private chime(): void {
    try {
      const Ctx =
        window.AudioContext ??
        (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
      if (!Ctx) {
        return;
      }
      const ctx = new Ctx();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.value = 880;
      gain.gain.setValueAtTime(0.0001, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.6, ctx.currentTime + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.6);
      osc.connect(gain).connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.65);
    } catch {
      // audio blocked until user gesture — the badge still shows the count
    }
  }
}
