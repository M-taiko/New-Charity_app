import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ToastService, type ToastOptions } from '@core/errors/toast.service';
import { PollingService } from '@core/polling/polling.service';
import { apiFailure } from '@shared/lib/server-field-errors';
import { markedAt, unreadBody, type UnreadSelection } from '@shared/lib/notification-undo';
import type { ActionResponse } from '@shared/models/api.models';

export interface ReadOptions {
  /** called after an undo was asked for, whether or not the server took it — the caller shows the truth */
  readonly onUndone?: () => void;
}

const UNREAD_ROUTE = '/notifications/unread';

/**
 * Marking notifications read, and undoing it (N-06) — the one place that
 * knows the three endpoints. The bell and the notifications page both go
 * through here, so each read offers its undo in the toast.
 */
@Injectable({ providedIn: 'root' })
export class NotificationReadService {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  private polling = inject(PollingService);

  /** one notification read; resolves to whether the server took it */
  async read(id: number, options: ReadOptions = {}): Promise<boolean> {
    const ok = await firstValueFrom(this.http.post<ActionResponse>(`/notifications/${id}/read`, {})).then(
      () => true,
      () => false,
    );
    if (!ok) {
      return false;
    }
    this.polling.refreshNow();
    const toastOptions: ToastOptions = { undo: () => { void this.unread({ ids: [id] }, options); } };
    this.toast.info('notification.marked_read', toastOptions);
    return true;
  }

  /** every unread notification read; the toast can put back exactly that batch */
  async readAll(options: ReadOptions = {}): Promise<boolean> {
    const answer = await firstValueFrom(this.http.post<ActionResponse>('/notifications/mark-all-read', {})).then(
      // markedAt reads the whole answer (its `data.marked_at`)
      (res) => ({ message: res.message, since: markedAt(res) }),
      () => null,
    );
    if (answer === null) {
      return false;
    }
    this.polling.refreshNow();
    // without the instant the server named there is nothing exact to put back — no undo is offered
    const since = answer.since;
    const toastOptions: ToastOptions = since === null ? {} : { undo: () => { void this.unread({ since }, options); } };
    this.toast.success(answer.message, toastOptions);
    return true;
  }

  /** the inverse: these notifications, or that batch, are unread again */
  private async unread(selection: UnreadSelection, options: ReadOptions): Promise<void> {
    await firstValueFrom(this.http.post<ActionResponse>(UNREAD_ROUTE, unreadBody(selection))).then(
      (res) => {
        this.toast.info(res.message);
        return true;
      },
      (err: unknown) => {
        const failure = apiFailure(err);
        this.toast.apiError(failure.message, failure.code);
        return false;
      },
    );
    this.polling.refreshNow();
    options.onUndone?.();
  }
}
