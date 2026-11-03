import { ChangeDetectionStrategy, Component, computed, inject, input, output, signal } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { DialogModule } from 'primeng/dialog';
import { ButtonModule } from 'primeng/button';
import type { HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ApiClient } from '@core/http/api-client';
import { ToastService } from '@core/errors/toast.service';
import type { ActionResponse, ApiErrorBody } from '@shared/models/api.models';

/**
 * The one delete flow in the app (T3-01): confirm dialog → POST {path} →
 * success toast / server business-rule message via toast.apiError. Pages only
 * react to the `deleted` output (reload the list or navigate back).
 */
@Component({
  selector: 'app-confirm-delete',
  standalone: true,
  imports: [TranslatePipe, DialogModule, ButtonModule],
  template: `
    <p-dialog appendTo="body"
      [visible]="visible()"
      (visibleChange)="visible.set($event)"
      [header]="'common.confirm_delete' | translate"
      [modal]="true"
      [draggable]="false"
      [resizable]="false"
      [closable]="!sending()"
      [style]="{ width: 'min(440px, 94vw)' }"
    >
      <p>{{ 'common.delete_message' | translate: { name: entity() } }}</p>
      @if (alert()) {
        <div class="dialog__alert" role="alert">
          <svg width="16" height="16" aria-hidden="true">
            <use href="assets/icons/sprite.svg#tabler-alert-triangle" />
          </svg>
          <span>{{ alert() }}</span>
        </div>
      }
      <p-footer>
        <div class="u-flex" style="justify-content: flex-end">
          <p-button [label]="'common.cancel' | translate" severity="secondary" [text]="true" [disabled]="sending()" (onClick)="visible.set(false)" />
          <p-button [label]="'common.delete' | translate" severity="danger" [loading]="sending()" (onClick)="confirm()" />
        </div>
      </p-footer>
    </p-dialog>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ConfirmDeleteComponent {
  /** full action path, e.g. `/treasury/3/delete` */
  path = input.required<string>();
  /** display name of the record, interpolated into the message */
  entity = input('');

  deleted = output<ActionResponse>();

  visible = signal(false);
  sending = signal(false);
  private failure = signal<HttpErrorResponse | null>(null);

  private api = inject(ApiClient);
  private toast = inject(ToastService);

  /** server business-rule message shown inside the dialog (public: template) */
  alert = computed(() => {
    const err = this.failure();
    if (!err) {
      return null;
    }
    const body = err.error as ApiErrorBody | null;
    return body?.message ?? null;
  });

  open(): void {
    this.failure.set(null);
    this.visible.set(true);
  }

  confirm(): void {
    this.sending.set(true);
    this.failure.set(null);
    void firstValueFrom(this.api.remove(this.path()))
      .then((res) => {
        this.sending.set(false);
        this.visible.set(false);
        this.toast.success(res.message);
        this.deleted.emit(res);
      })
      .catch((err: unknown) => {
        this.sending.set(false);
        this.failure.set((err as HttpErrorResponse));
        this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic', (err as HttpErrorResponse).error?.code);
      });
  }
}
