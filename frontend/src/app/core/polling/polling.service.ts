import { OnDestroy} from '@angular/core';
import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APP_CONFIG } from '@core/config/app-config';

interface PollPayload {
  unread_count: number;
  latest: { id: number; title: string; message: string; type: string; created_at: string; is_read: boolean; link: string | null }[];
}

/**
 * One service owns every timer (plan 18): notifications every 15 s while the
 * tab is visible, with backoff on errors. Chat keeps its own rhythm elsewhere
 * through the same visibility rules.
 */
@Injectable({ providedIn: 'root' })
export class PollingService implements OnDestroy {
  readonly unreadCount = signal(0);
  readonly latest = signal<PollPayload['latest']>([]);

  private timer: ReturnType<typeof setInterval> | null = null;
  private failures = 0;

  constructor(private http: HttpClient) {
    document.addEventListener('visibilitychange', this.visibilityHandler);
    this.start();
  }

  private visibilityHandler = (): void => {
    if (document.hidden) {
      this.stop();
    } else {
      this.start();
    }
  };

  refreshNow(): void {
    this.http.get<PollPayload>('/notifications/poll').subscribe({
      next: (payload) => {
        this.failures = 0;
        this.unreadCount.set(payload.unread_count);
        this.latest.set(payload.latest);
      },
      error: () => this.failures++,
    });
  }

  private start(): void {
    if (this.timer !== null) {
      return;
    }
    this.refreshNow();
    this.timer = setInterval(
      () => {
        if (this.failures >= 3) {
          // backoff: pause this cycle, resume on next visibility change
          this.stop();
          return;
        }
        this.refreshNow();
      },
      APP_CONFIG.notificationsPollMs,
    );
  }

  private stop(): void {
    if (this.timer !== null) {
      clearInterval(this.timer);
      this.timer = null;
    }
  }

  ngOnDestroy(): void {
    this.stop();
    document.removeEventListener('visibilitychange', this.visibilityHandler);
  }
}
