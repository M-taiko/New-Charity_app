import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { viewChild } from '@angular/core';
import type { OnDestroy } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { TextareaModule } from 'primeng/textarea';
import { ToastService } from '@core/errors/toast.service';
import { AuthService } from '@core/auth/auth.service';
import { APP_CONFIG } from '@core/config/app-config';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { IconComponent } from '@shared/ui/icon/icon.component';
import { ConfirmDeleteComponent } from '@shared/ui/confirm-delete/confirm-delete.component';
import { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import type { ActionResponse, ItemResponse } from '@shared/models/api.models';

/** One option row of a poll — counts only exist on rows fetched from /chat/poll. */
interface PollOptionView {
  text: string;
  votes?: number;
}

/** Poll rendered inside a message; can_close mirrors ChatPollController@close (creator/manager). */
interface PollView {
  id: number;
  question: string;
  options: PollOptionView[];
  is_closed: boolean;
  my_vote: number | null;
  votes_count: number;
  can_close: boolean;
}

interface ChatMsg {
  id: number;
  user_id: number;
  name: string | null;
  body: string;
  created_at: string | null;
  /** relative time pre-localized by the server (only on incrementally polled rows) */
  time?: string;
  is_mine: boolean;
  poll: PollView | null;
}

/** Row of GET /chat (SystemController@chat) — poll options are plain strings here. */
interface ChatFeedMsg {
  id: number;
  user_id: number;
  name: string | null;
  body: string;
  created_at: string | null;
  is_mine: boolean;
  poll: {
    id: number;
    question: string;
    options: string[];
    is_closed: boolean;
    my_vote: number | null;
    votes_count: number;
  } | null;
}

/** Row of GET /chat/poll (ChatController@poll) — incremental feed since a message id. */
interface PollFeedMsg {
  id: number;
  user_id: number;
  name: string | null;
  body: string;
  time: string;
  is_me: boolean;
  poll: {
    id: number;
    question: string;
    options: { text: string; votes: number; percentage: number }[];
    total_votes: number;
    is_closed: boolean;
    user_vote: number | null;
    created_by: string;
  } | null;
}

/**
 * Group chat (plan 20, T3-100): message list + send + polls (create / vote /
 * close) + message delete. Refresh runs incrementally through GET /chat/poll
 * following the PollingService pattern (visibility-aware timer, backoff after
 * repeated failures) instead of reloading the whole feed.
 */
@Component({
  selector: 'app-chat-page',
  standalone: true,
  imports: [
    TranslatePipe, FormsModule, ButtonModule, InputTextModule, TextareaModule,
    PageHeaderComponent, IconComponent, ConfirmDeleteComponent, FormDialogComponent, DatetimePipe,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'chat.title' | translate">
      <div actions>
        <p-button [label]="'chat.poll.create' | translate" size="small" [text]="true" (onClick)="openPollDialog()">
          <ng-template pTemplate="icon"><app-icon name="list-check" [size]="16" /></ng-template>
        </p-button>
      </div>
    </app-page-header>
    <div class="chat">
      <div class="chat__log">
        @for (m of messages(); track m.id) {
          <div class="chat__msg" [class.chat__msg--mine]="m.is_mine">
            <div class="chat__msg-head">
              <strong>{{ m.name }}</strong>
              <span class="u-bidi">{{ m.time ?? (m.created_at | datetime) }}</span>
              @if (m.is_mine || canModerate()) {
                <button
                  type="button"
                  class="topbar__icon-btn"
                  style="width: 24px; height: 24px; margin-inline-start: auto"
                  (click)="askDelete(m)"
                  [attr.aria-label]="'common.delete' | translate"
                  [title]="'common.delete' | translate"
                >
                  <app-icon name="trash" [size]="14" />
                </button>
              }
            </div>
            <p class="chat__body">{{ m.body }}</p>
            @if (m.poll; as poll) {
              <div class="chat__poll">
                <strong class="u-small">{{ poll.question }}</strong>
                @for (option of poll.options; track $index) {
                  <button
                    type="button"
                    class="direction-trail__chip u-full"
                    style="margin-block: 2px; text-align: start"
                    [disabled]="poll.is_closed"
                    (click)="vote(poll.id, $index)"
                  >
                    {{ (poll.my_vote === $index ? '✓ ' : '') + option.text }}
                    @if (option.votes !== undefined) {
                      <span class="u-bidi u-muted">({{ option.votes }})</span>
                    }
                  </button>
                }
                <div class="u-flex-between">
                  <span class="u-small u-bidi">{{ 'chat.poll.total_votes' | translate: { count: poll.votes_count } }}</span>
                  @if (poll.is_closed) {
                    <span class="stamp stamp--neutral">{{ 'chat.poll.closed' | translate }}</span>
                  } @else if (poll.can_close) {
                    <p-button
                      [label]="'chat.poll.close' | translate"
                      size="small"
                      [text]="true"
                      severity="danger"
                      (onClick)="closePoll(poll.id)"
                    />
                  }
                </div>
              </div>
            }
          </div>
        } @empty {
          @if (loaded()) {
            <p class="u-muted">{{ 'chat.empty' | translate }}</p>
          }
        }
      </div>
      <div class="chat__composer">
        <textarea
          pTextarea
          rows="1"
          [(ngModel)]="draft"
          name="draft"
          [placeholder]="'chat.placeholder' | translate"
          style="flex: 1"
          (keydown.enter)="$event.preventDefault(); send()"
        ></textarea>
        <p-button (onClick)="send()" [ariaLabel]="'chat.send' | translate">
          <ng-template pTemplate="icon"><app-icon name="send" [size]="16" /></ng-template>
        </p-button>
      </div>
    </div>

    <app-confirm-delete #del [path]="deletePath()" [entity]="deleteExcerpt()" (deleted)="onDeleted()" />

    <app-form-dialog
      [header]="'chat.poll.create' | translate"
      [submitLabel]="'chat.poll.create' | translate"
      [(visible)]="pollOpen"
      [sending]="pollSending()"
      (submit)="submitPoll()"
    >
      <div class="u-column u-gap-3">
        <div class="field">
          <label class="field__label" for="poll-question">{{ 'chat.poll.question' | translate }} *</label>
          <textarea
            pTextarea
            id="poll-question"
            rows="2"
            maxlength="500"
            [(ngModel)]="pollQuestion"
            name="question"
            style="width: 100%"
          ></textarea>
        </div>
        <div class="u-column u-gap-2">
          @for (opt of pollOptions(); track $index) {
            <div class="u-flex u-gap-2" style="align-items: center">
              <input
                pInputText
                maxlength="200"
                [ngModel]="pollOptions()[$index]"
                [name]="'option' + $index"
                [placeholder]="'chat.poll.option' | translate: { index: $index + 1 }"
                style="flex: 1"
                (ngModelChange)="setOption($index, $event)"
              />
              @if (pollOptions().length > 2) {
                <p-button
                  size="small"
                  [text]="true"
                  severity="danger"
                  (onClick)="removeOption($index)"
                  [ariaLabel]="'common.remove' | translate"
                >
                  <ng-template pTemplate="icon"><app-icon name="x" [size]="14" /></ng-template>
                </p-button>
              }
            </div>
          }
          @if (pollOptions().length < 10) {
            <p-button [label]="'common.add' | translate" size="small" [text]="true" (onClick)="addOption()" />
          }
        </div>
        @if (pollError(); as error) {
          <p class="field__error">{{ error | translate }}</p>
        }
      </div>
    </app-form-dialog>
  `,
})
export class ChatPage implements OnDestroy {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  private auth = inject(AuthService);

  draft = signal('');
  messages = signal<ChatMsg[]>([]);
  loaded = signal(false);

  /** the chat may be moderated by managers (ChatController@destroy / ChatPollController@close) */
  readonly canModerate = computed(() => this.auth.me()?.roles.includes('مدير') ?? false);

  // ── delete message (T3-100) ──────────────────────────────
  private readonly deleteDialog = viewChild.required(ConfirmDeleteComponent);
  readonly deleteTarget = signal<ChatMsg | null>(null);
  readonly deletePath = computed(() => `/chat/${this.deleteTarget()?.id ?? 0}/delete`);
  readonly deleteExcerpt = computed(() => {
    const body = this.deleteTarget()?.body ?? '';
    return body.length > 40 ? `${body.slice(0, 40)}…` : body;
  });

  // ── create poll dialog (T3-100) ──────────────────────────
  pollOpen = signal(false);
  pollSending = signal(false);
  pollQuestion = signal('');
  pollOptions = signal<string[]>(['', '']);
  /** i18n key of the local validation error (question empty / fewer than 2 options) */
  readonly pollError = signal<string | null>(null);

  // ── incremental refresh (PollingService pattern) ─────────
  private timer: ReturnType<typeof setInterval> | null = null;
  private failures = 0;
  private lastId = 0;
  private readonly visibilityHandler = (): void => {
    if (document.hidden) {
      this.stop();
    } else {
      this.start();
    }
  };

  constructor() {
    document.addEventListener('visibilitychange', this.visibilityHandler);
    void this.reload();
    this.start();
  }

  ngOnDestroy(): void {
    this.stop();
    document.removeEventListener('visibilitychange', this.visibilityHandler);
  }

  send(): void {
    const text = this.draft().trim();
    if (!text) {
      return;
    }
    void firstValueFrom(this.http.post<ActionResponse>('/chat', { body: text }))
      .then(() => {
        this.draft.set('');
        // the new message arrives through the incremental feed — no full reload
        void this.pollNow();
      })
      .catch((err: unknown) => this.toast.apiError(this.errMsg(err)));
  }

  vote(pollId: number, optionIndex: number): void {
    void firstValueFrom(this.http.post<ActionResponse>(`/chat-polls/${pollId}/vote`, { option_index: optionIndex }))
      .then(() => this.reload()) // vote tallies are only visible on the full feed
      .catch((err: unknown) => this.toast.apiError(this.errMsg(err)));
  }

  closePoll(pollId: number): void {
    void firstValueFrom(this.http.post<ActionResponse>(`/chat-polls/${pollId}/close`, {}))
      .then(() => {
        this.toast.success('chat.poll.closed');
        void this.reload();
      })
      .catch((err: unknown) => this.toast.apiError(this.errMsg(err)));
  }

  askDelete(m: ChatMsg): void {
    this.deleteTarget.set(m);
    this.deleteDialog().open();
  }

  onDeleted(): void {
    this.deleteTarget.set(null);
    void this.reload();
  }

  openPollDialog(): void {
    this.pollQuestion.set('');
    this.pollOptions.set(['', '']);
    this.pollError.set(null);
    this.pollOpen.set(true);
  }

  addOption(): void {
    this.pollOptions.update((opts) => (opts.length >= 10 ? opts : [...opts, '']));
  }

  removeOption(index: number): void {
    this.pollOptions.update((opts) => (opts.length <= 2 ? opts : opts.filter((_, i) => i !== index)));
  }

  setOption(index: number, value: string): void {
    this.pollOptions.update((opts) => opts.map((opt, i) => (i === index ? value : opt)));
  }

  /**
   * Polls are attached to a chat message (ChatPoll hasOne on ChatMessage), so
   * creating one posts the question as a message first and then attaches the
   * poll to it — otherwise it would never appear in the feed.
   */
  submitPoll(): void {
    const question = this.pollQuestion().trim();
    const options = this.pollOptions().map((opt) => opt.trim()).filter((opt) => opt !== '');
    if (!question) {
      this.pollError.set('common.required_field');
      return;
    }
    if (options.length < 2) {
      this.pollError.set('chat.poll.min_options');
      return;
    }
    this.pollError.set(null);
    this.pollSending.set(true);
    void (async () => {
      try {
        const sent = await firstValueFrom(this.http.post<{ ok: boolean; id: number }>('/chat', { body: question }));
        await firstValueFrom(
          this.http.post<ActionResponse>('/chat-polls', { question, options, chat_message_id: sent.id }),
        );
        this.pollSending.set(false);
        this.pollOpen.set(false);
        this.toast.success('chat.poll.created');
        void this.pollNow();
      } catch (err) {
        this.pollSending.set(false);
        this.pollError.set(this.errMsg(err));
      }
    })();
  }

  /** Full refresh of the feed — used after vote/close/delete where poll state changed. */
  private async reload(): Promise<void> {
    try {
      const res = await firstValueFrom(this.http.get<ItemResponse<ChatFeedMsg[]>>('/chat'));
      const list = (res.data ?? []).map((row) => this.mapFeedRow(row));
      this.failures = 0;
      this.messages.set(list);
      this.lastId = list.reduce((max, m) => Math.max(max, m.id), 0);
    } catch {
      // keep the previous list; the incremental timer keeps retrying
    } finally {
      this.loaded.set(true);
    }
  }

  private mapFeedRow(row: ChatFeedMsg): ChatMsg {
    return {
      id: row.id,
      user_id: row.user_id,
      name: row.name,
      body: row.body,
      created_at: row.created_at,
      is_mine: row.is_mine,
      poll: row.poll
        ? {
            id: row.poll.id,
            question: row.poll.question,
            options: row.poll.options.map((text) => ({ text })),
            is_closed: row.poll.is_closed,
            my_vote: row.poll.my_vote,
            votes_count: row.poll.votes_count,
            // the message author is the poll creator; managers may close any poll
            can_close: row.is_mine || this.canModerate(),
          }
        : null,
    };
  }

  /** GET /chat/poll?since=lastId — appends only new messages (no full reload). */
  private async pollNow(): Promise<void> {
    if (document.hidden) {
      return;
    }
    try {
      const rows = await firstValueFrom(
        this.http.get<PollFeedMsg[]>('/chat/poll', { params: { since: String(this.lastId) } }),
      );
      this.failures = 0;
      const fresh = rows.map((row) => this.mapPollFeedRow(row)).filter((m) => m.id > this.lastId);
      if (fresh.length === 0) {
        return;
      }
      this.messages.update((list) => {
        const seen = new Set(list.map((m) => m.id));
        return [...list, ...fresh.filter((m) => !seen.has(m.id))];
      });
      this.lastId = fresh.reduce((max, m) => Math.max(max, m.id), this.lastId);
    } catch {
      this.failures++;
    }
  }

  private mapPollFeedRow(row: PollFeedMsg): ChatMsg {
    return {
      id: row.id,
      user_id: row.user_id,
      name: row.name,
      body: row.body,
      created_at: null,
      time: row.time,
      is_mine: row.is_me,
      poll: row.poll
        ? {
            id: row.poll.id,
            question: row.poll.question,
            options: row.poll.options.map((option) => ({ text: option.text, votes: option.votes })),
            is_closed: row.poll.is_closed,
            my_vote: row.poll.user_vote,
            votes_count: row.poll.total_votes,
            // the server re-checks authorization; created_by is a display name here
            can_close: row.poll.created_by === (this.auth.me()?.name ?? '') || this.canModerate(),
          }
        : null,
    };
  }

  private start(): void {
    if (this.timer !== null) {
      return;
    }
    this.timer = setInterval(() => {
      if (this.failures >= 3) {
        // backoff: pause this cycle, resume on next visibility change
        this.stop();
        return;
      }
      void this.pollNow();
    }, APP_CONFIG.chatPollMs);
  }

  private stop(): void {
    if (this.timer !== null) {
      clearInterval(this.timer);
      this.timer = null;
    }
  }

  private errMsg(err: unknown): string {
    const body = (err as HttpErrorResponse).error as { message?: string; error?: string } | null;
    return body?.message ?? body?.error ?? 'errors.generic';
  }
}
