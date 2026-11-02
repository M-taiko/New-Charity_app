import { computed, OnDestroy} from '@angular/core';
import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { TextareaModule } from 'primeng/textarea';
import { ToastService } from '@core/errors/toast.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { IconComponent } from '@shared/ui/icon/icon.component';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { ItemResponse, ActionResponse } from '@shared/models/api.models';

interface ChatMsg { id: number; user_id: number; name: string | null; body: string; created_at: string | null; is_mine: boolean; poll: { id: number; question: string; options: string[]; is_closed: boolean; my_vote: number | null; votes_count: number } | null }

/** Group chat with polling (plan 20): message list + send + poll voting. */
@Component({
  selector: 'app-chat-page',
  standalone: true,
  imports: [TranslatePipe, FormsModule, ButtonModule, InputTextModule, TextareaModule, PageHeaderComponent, IconComponent, DatetimePipe],
  template: `
    <app-page-header [title]="'chat.title' | translate" />
    <div class="chat">
      <div class="chat__log" #log>
        @for (m of data.value(); track m.id) {
          <div class="chat__msg" [class.chat__msg--mine]="m.is_mine">
            <div class="chat__msg-head"><strong>{{ m.name }}</strong> <span class="u-bidi">{{ m.created_at | datetime }}</span></div>
            <p class="chat__body">{{ m.body }}</p>
            @if (m.poll; as poll) {
              <div class="chat__poll">
                <strong class="u-small">{{ poll.question }}</strong>
                @for (option of poll.options; track $index) {
                  <button type="button" class="direction-trail__chip u-full" style="margin-block: 2px; text-align: start"
                          [disabled]="poll.is_closed" (click)="vote(poll.id, $index)">
                    {{ (poll.my_vote === $index ? '✓ ' : '') + option }}
                  </button>
                }
                @if (poll.is_closed) { <span class="stamp stamp--neutral">{{ 'chat.poll.closed' | translate }}</span> }
              </div>
            }
          </div>
        } @empty { <p class="u-muted">{{ 'chat.empty' | translate }}</p> }
      </div>
      <div class="chat__composer">
        <textarea pTextarea rows="1" [(ngModel)]="draft" name="draft" [placeholder]="'chat.placeholder' | translate" style="flex: 1" (keydown.enter)="$event.preventDefault(); send()"></textarea>
        <p-button (onClick)="send()" [ariaLabel]="'chat.send' | translate">
          <ng-template pTemplate="icon"><app-icon name="send" [size]="16" /></ng-template>
        </p-button>
      </div>
    </div>
  `,
})
export class ChatPage implements OnDestroy {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  draft = signal('');

  data = resource<ChatMsg[], unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<ChatMsg[]>>('/chat')).then((r) => r.data ?? []),
  });

  private timer = setInterval(() => {
    if (!document.hidden) {
      this.data.reload();
    }
  }, 5000);

  send(): void {
    const text = this.draft().trim();
    if (!text) {
      return;
    }
    void firstValueFrom(this.http.post<ActionResponse>('/chat', { body: text })).then(() => {
      this.draft.set('');
      this.data.reload();
    }).catch((err: HttpErrorResponse) => this.toast.apiError(err.error?.message ?? 'errors.generic'));
  }

  vote(pollId: number, optionIndex: number): void {
    void firstValueFrom(this.http.post<ActionResponse>(`/chat-polls/${pollId}/vote`, { option_index: optionIndex })).then(() => this.data.reload());
  }

  ngOnDestroy(): void {
    clearInterval(this.timer);
  }
}
