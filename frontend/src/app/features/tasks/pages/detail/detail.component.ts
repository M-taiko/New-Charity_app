// Task details (plan 20 + T3-80): full status flow, comments thread with the
// author's role label, delegation, delete. Visibility mirrors tasks/show.blade.php
// and TaskController re-enforces it server-side:
//   - manager/accountant: complete + cancel while open, reopen when closed, delete
//   - assignee (non-manager): start (in_progress) only, only while pending
//   - delegate: creator, assignee, manager or accountant, while the task is open
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { computed, ChangeDetectionStrategy, Component, inject, input, resource, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { TextareaModule } from 'primeng/textarea';
import { SelectModule } from 'primeng/select';
import { ToastService } from '@core/errors/toast.service';
import { AuthService } from '@core/auth/auth.service';
import { ApiClient } from '@core/http/api-client';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { KeyValueListComponent } from '@shared/ui/key-value-list/key-value-list.component';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { ConfirmDialogComponent } from '@shared/ui/confirm-dialog/confirm-dialog.component';
import { ConfirmDeleteComponent } from '@shared/ui/confirm-delete/confirm-delete.component';
import { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';
import { UserChipComponent } from '@shared/ui/user-chip/user-chip.component';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { RelativeTimePipe } from '@shared/pipes/relative-time.pipe';
import { EnumLabelPipe } from '@shared/pipes/enum-label.pipe';

interface TaskComment {
  id: number;
  user: string | null;
  body: string;
  created_at: string | null;
}

interface TaskDetail {
  id: number;
  title: string;
  description: string | null;
  created_by: string | null;
  assigned_to: string | null;
  assigned_to_id: number;
  status: string;
  priority: string;
  due_date: string | null;
  completed_at: string | null;
  created_at: string | null;
  comments: TaskComment[];
}

@Component({
  selector: 'app-task-detail',
  standalone: true,
  imports: [EmptyStateComponent, TranslatePipe, RouterLink, FormsModule, ButtonModule, TextareaModule, SelectModule, PageHeaderComponent, KeyValueListComponent, StatusStampComponent, PrintSheetComponent, ConfirmDialogComponent, ConfirmDeleteComponent, FormDialogComponent, UserChipComponent, RelativeTimePipe, EnumLabelPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (safeValue(); as item) {
      <app-print-sheet [title]="'task.detail.title' | translate">
        <app-page-header [title]="item.title">
          <div actions class="u-flex u-gap-2 u-flex-wrap">
            @if (canStart(item)) {
              <p-button [label]="'task.detail.start' | translate" size="small" (onClick)="setStatus('in_progress')" [loading]="sending()" />
            }
            @if (isManager() && isOpen(item)) {
              <p-button [label]="'task.detail.complete' | translate" size="small" severity="success" (onClick)="setStatus('completed')" [loading]="sending()" />
              <p-button [label]="'common.cancel' | translate" size="small" severity="secondary" [outlined]="true" (onClick)="cancelDialog.open()" [loading]="sending()" />
            }
            @if (isManager() && isClosed(item)) {
              <p-button [label]="'task.detail.reopen' | translate" size="small" severity="warn" (onClick)="setStatus('pending')" [loading]="sending()" />
            }
            @if (canDelegate(item)) {
              <p-button [label]="'task.detail.delegate' | translate" size="small" severity="warn" [outlined]="true" (onClick)="openDelegate()" />
            }
            @if (isManager()) {
              <p-button [label]="'common.delete' | translate" size="small" severity="danger" [text]="true" (onClick)="deleteDialog.open()" />
            }
            <p-button [label]="'common.back_to_list' | translate" size="small" [text]="true" routerLink="/tasks" />
          </div>
        </app-page-header>

        <div class="u-flex u-gap-2 u-flex-wrap u-mb-4">
          <app-status-stamp [status]="item.status" namespace="task" [large]="true" />
          <span class="stamp">{{ item.priority | enumLabel: 'priority' }}</span>
        </div>

        <div class="u-flex u-gap-4 u-flex-wrap u-mb-4">
          <span class="u-flex u-gap-1">
            <span class="u-muted u-small">{{ 'task.detail.creator' | translate }}:</span>
            <app-user-chip [name]="item.created_by ?? '—'" />
          </span>
          <span class="u-flex u-gap-1">
            <span class="u-muted u-small">{{ 'task.detail.assignee' | translate }}:</span>
            <app-user-chip [name]="item.assigned_to ?? '—'" />
          </span>
        </div>

        <app-key-value-list [items]="keyValues(item)" />

        <section class="form-section u-mt-4">
          <h3 class="form-section__title">{{ 'task.detail.comments' | translate }} ({{ item.comments.length }})</h3>
          @if (item.comments.length === 0) {
            <p class="u-muted">{{ 'task.detail.empty_comments' | translate }}</p>
          } @else {
            <ul class="u-column u-gap-3" style="list-style: none; padding: 0; margin: 0">
              @for (comment of item.comments; track comment.id) {
                <li class="u-column u-gap-1">
                  <div class="u-flex u-gap-2 u-flex-wrap">
                    <app-user-chip [name]="comment.user ?? '—'" />
                    @if (commentRole(comment.user); as role) {
                      <span class="stamp stamp--neutral u-small">{{ role }}</span>
                    }
                    <span class="u-muted u-small u-bidi">{{ comment.created_at | relativeTime }}</span>
                  </div>
                  <p style="white-space: pre-line; margin: 0">{{ comment.body }}</p>
                </li>
              }
            </ul>
          }

          @if (isOpen(item)) {
            <form class="u-column u-gap-2 u-mt-4" (ngSubmit)="addComment()">
              <label class="field__label u-visually-hidden" for="task-comment">{{ 'task.detail.comment_placeholder' | translate }}</label>
              <textarea
                id="task-comment"
                pInputTextarea
                rows="3"
                name="body"
                [(ngModel)]="commentBody"
                [maxlength]="2000"
                [placeholder]="'task.detail.comment_placeholder' | translate"
                style="width: 100%"
              ></textarea>
              <div class="u-flex" style="justify-content: flex-end">
                <p-button type="submit" [label]="'task.detail.send' | translate" size="small" [loading]="sending()" [disabled]="!commentBody().trim() || sending()" />
              </div>
            </form>
          } @else {
            <p class="u-muted u-small">{{ 'task.detail.comments_locked' | translate }}</p>
          }
        </section>

        <app-confirm-dialog
          #cancelDialog
          [header]="'common.confirm'"
          [message]="'task.detail.cancel_message'"
          [context]="{ name: item.title }"
          (confirmed)="setStatus('cancelled')"
        />
        <app-confirm-delete #deleteDialog [path]="'/tasks/' + item.id + '/delete'" [entity]="item.title" (deleted)="onDeleted()" />
        <app-form-dialog
          [header]="'task.detail.delegate' | translate"
          [submitLabel]="'task.detail.delegate_confirm' | translate"
          [sending]="delegateSending()"
          [error]="delegateError()"
          [(visible)]="delegateOpen"
          (submit)="delegate(item)"
        >
          @if (delegateInvalid()) {
            <p class="field__error" role="alert">{{ 'common.required_field' | translate }}</p>
          }
          <div class="field">
            <label class="field__label" for="delegate-user">{{ 'task.form.assignee' | translate }} *</label>
            <p-select
              inputId="delegate-user"
              appendTo="body"
              [options]="delegateUsers()"
              optionLabel="name"
              optionValue="id"
              [(ngModel)]="delegateUserId"
              name="assigned_to"
              styleClass="u-full"
            />
          </div>
        </app-form-dialog>
      </app-print-sheet>
    } @else if (data.isLoading()) {
      <div class="skeleton skeleton--title u-mb-4"></div>
      <div class="skeleton skeleton--tile"></div>
    } @else {
      <app-empty-state [title]="'common.not_found' | translate" [text]="'common.not_found_text' | translate" />
    }
  `,
})
export class TaskDetailPage {
  private api = inject(ApiClient);
  private toast = inject(ToastService);
  private auth = inject(AuthService);
  private router = inject(Router);

  private readonly dt = new DatetimePipe();
  private readonly enumPipe = inject(EnumLabelPipe);

  id = input.required<number>();
  sending = signal(false);

  /** delegate dialog state */
  delegateOpen = signal(false);
  delegateSending = signal(false);
  delegateError = signal<HttpErrorResponse | null>(null);
  delegateInvalid = signal(false);
  delegateUserId = signal<number | null>(null);
  private lookupUsers = signal<{ id: number; name: string; roles: string[] }[]>([]);

  commentBody = signal('');

  data = resource<TaskDetail, { id: number }>({
    params: () => ({ id: this.id() }),
    loader: ({ params }) => firstValueFrom(this.api.get<TaskDetail>('/tasks/' + params.id)),
  });

  /** data.value() throws on an errored resource (e.g. 404) — null keeps the template alive */
  readonly safeValue = computed(() => {
    try {
      return this.data.value() ?? null;
    } catch {
      return null;
    }
  });

  constructor() {
    void firstValueFrom(this.api.loadLookups()).then((lookups) => {
      this.lookupUsers.set(lookups.users);
    });
  }

  /** manager/accountant = the Blade @php $isManager branch */
  isManager(): boolean {
    return this.auth.can('is_manager') || this.auth.can('is_accountant');
  }

  isOpen(item: TaskDetail): boolean {
    return item.status !== 'completed' && item.status !== 'cancelled';
  }

  isClosed(item: TaskDetail): boolean {
    return !this.isOpen(item);
  }

  /** assignee (non-manager) may start a pending task — TaskController enforces the rest */
  canStart(item: TaskDetail): boolean {
    return !this.isManager() && this.auth.me()?.id === item.assigned_to_id && item.status === 'pending';
  }

  /**
   * Delegate: creator, current assignee, manager or accountant, while open.
   * The read API exposes the creator as a NAME only, so the creator check is a
   * name comparison against the signed-in account (documented limitation).
   */
  canDelegate(item: TaskDetail): boolean {
    if (!this.isOpen(item)) {
      return false;
    }
    return (
      this.isManager() ||
      this.auth.me()?.id === item.assigned_to_id ||
      (item.created_by !== null && this.auth.me()?.name === item.created_by)
    );
  }

  /** the author's role label — the API returns names only, matched against /lookups */
  commentRole(name: string | null): string | null {
    if (!name) {
      return null;
    }
    return this.lookupUsers().find((u) => u.name === name)?.roles[0] ?? null;
  }

  /** delegate targets: every visible user except the current assignee (server: different) */
  delegateUsers(): { id: number; name: string }[] {
    const current = this.safeValue()?.assigned_to_id;
    return this.lookupUsers().filter((u) => u.id !== current).map((u) => ({ id: u.id, name: u.name }));
  }

  keyValues(item: TaskDetail): { label: string; value: string }[] {
    const values: { label: string; value: string }[] = [
      { label: 'task.form.priority', value: this.enumPipe.transform(item.priority, 'priority') },
      { label: 'task.list.col_due', value: this.dt.transform(item.due_date, false) },
      { label: 'common.date', value: this.dt.transform(item.created_at, false) },
    ];
    if (item.completed_at) {
      values.push({ label: 'task.detail.completed_at', value: this.dt.transform(item.completed_at, false) });
    }
    if (item.description) {
      values.push({ label: 'task.form.description', value: item.description });
    }
    return values;
  }

  setStatus(status: string): void {
    this.act('status', { status });
  }

  addComment(): void {
    const body = this.commentBody().trim();
    if (!body) {
      return;
    }
    this.act('comment', { body }, () => {
      this.commentBody.set('');
    });
  }

  openDelegate(): void {
    this.delegateError.set(null);
    this.delegateInvalid.set(false);
    this.delegateUserId.set(null);
    this.delegateOpen.set(true);
  }

  delegate(task: TaskDetail): void {
    if (this.delegateUserId() === null || this.delegateUserId() === task.assigned_to_id) {
      this.delegateInvalid.set(true);
      return;
    }
    this.delegateInvalid.set(false);
    this.delegateSending.set(true);
    void firstValueFrom(this.api.action(`/tasks/${task.id}/delegate`, { assigned_to: this.delegateUserId() }))
      .then((res) => {
        this.delegateSending.set(false);
        this.delegateOpen.set(false);
        this.toast.success(res.message);
        this.data.reload();
      })
      .catch((err: unknown) => {
        this.delegateSending.set(false);
        this.delegateError.set((err as HttpErrorResponse));
      });
  }

  onDeleted(): void {
    void this.router.navigate(['/tasks']);
  }

  private act(path: string, body: Record<string, unknown>, onDone?: () => void): void {
    this.sending.set(true);
    void firstValueFrom(this.api.action(`/tasks/${this.id()}/${path}`, body)).then((res) => {
      this.sending.set(false);
      this.toast.success(res.message);
      if (onDone) {
        onDone();
      }
      this.data.reload();
    }).catch((err: unknown) => {
      this.sending.set(false);
      this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic', (err as HttpErrorResponse).error?.code);
    });
  }
}
