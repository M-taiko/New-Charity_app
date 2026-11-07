// User details (plan 20): info + role assignment (several roles allowed).
// T3-60: manage_users block — edit link + delete (ConfirmDelete); the current
// user can never delete themselves (client guard; server would allow it).
import { computed, ChangeDetectionStrategy, Component, inject, input, resource, signal, viewChild } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { SelectModule } from 'primeng/select';
import { ToastService } from '@core/errors/toast.service';
import { AuthService } from '@core/auth/auth.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent, type LoadableResource } from '@shared/ui/async-page/async-page.component';
import { KeyValueListComponent } from '@shared/ui/key-value-list/key-value-list.component';
import { ConfirmDeleteComponent } from '@shared/ui/confirm-delete/confirm-delete.component';
import type { ActionResponse, ItemResponse } from '@shared/models/api.models';
import { ActionButtonComponent } from '@shared/ui/actions/action-button.component';

interface UserDetail {
  id: number; name: string; email: string; phone: string | null;
  roles: string[]; is_active: boolean;
}

@Component({
  selector: 'app-user-detail',
  standalone: true,
  imports: [TranslatePipe, RouterLink, FormsModule, ButtonModule, SelectModule, PageHeaderComponent, AsyncPageComponent, KeyValueListComponent, ConfirmDeleteComponent, ActionButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="headerTitle()" [subtitle]="'user.detail.title' | translate">
      <div actions class="u-flex u-gap-2">
        @if (safeValue(); as u) {
          @if (canManage) {
            <p-button [label]="'common.edit' | translate" size="small" [routerLink]="['/users', u.id, 'edit']" />
            @if (isSelf(u.id)) {
              <span class="u-muted u-small" [title]="'user.detail.delete_self_hint' | translate">{{ 'user.detail.delete_self_hint' | translate }}</span>
            } @else {
              <app-action-button kind="delete" label="common.delete" size="small" (action)="deleteDialog()?.open()" />
            }
          }
        }
        <p-button [label]="'common.back_to_list' | translate" size="small" [text]="true" routerLink="/users" />
      </div>
    </app-page-header>

    <app-async-page [resource]="pageResource" variant="detail" [backLink]="['/users']">
      @if (safeValue(); as u) {
        <div class="u-mb-4">
          <span class="stamp" [class.stamp--success]="u.is_active" [class.stamp--neutral]="!u.is_active">
            {{ (u.is_active ? 'user.list.active' : 'user.list.inactive') | translate }}
          </span>
        </div>
        <app-key-value-list [items]="[
          { label: 'user.detail.name', value: u.name },
          { label: 'user.detail.email', value: u.email },
          { label: 'user.detail.phone', value: u.phone || '—' },
          { label: 'user.detail.roles', value: u.roles.join(this.translate.instant('common.list_separator')) }
        ]" />
        <section class="form-section u-mt-4">
          <h3 class="form-section__title">{{ 'user.form.roles' | translate }}</h3>
          <p class="field__hint">{{ 'user.form.roles_hint' | translate }}</p>
          <p-select appendTo="body" [options]="roleOptions" optionLabel="label" optionValue="value" [(ngModel)]="selectedRoles" name="selectedRoles" multiple styleClass="u-full" />
          <div class="u-flex u-justify-end">
            <p-button [label]="'common.save' | translate" (onClick)="saveRoles(u.id)" [loading]="sending()" />
          </div>
        </section>
        <app-confirm-delete #deleteDialog [path]="'/users/' + u.id + '/delete'" [entity]="u.name" (deleted)="onDeleted()" />
      }
    </app-async-page>
  `,
})
export class UserDetailPage {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  private auth = inject(AuthService);
  private router = inject(Router);
  translate = inject(TranslateService);
  id = input.required<number>();
  sending = signal(false);
  selectedRoles: string[] = [];

  /** the delete dialog lives inside the loaded branch — reach it via viewChild */
  deleteDialog = viewChild(ConfirmDeleteComponent);

  /** manage_users block (T3-60) — same permission as the Blade view */
  readonly canManage = this.auth.can('may_manage_users');

  /** deleting the signed-in account is blocked client-side (server would allow it) */
  isSelf(userId: number): boolean {
    return this.auth.me()?.id === userId;
  }

  /** the role ids the assign-roles endpoint expects, labeled via i18n (names come from the API) */
  readonly roleOptions = [
    { label: this.translate.instant('user.role.manager'), value: 1 },
    { label: this.translate.instant('user.role.accountant'), value: 2 },
    { label: this.translate.instant('user.role.agent'), value: 3 },
    { label: this.translate.instant('user.role.social_worker'), value: 4 },
    { label: this.translate.instant('user.role.supervisor'), value: 5 },
  ];

  data = resource<UserDetail, { id: number }>({
    params: () => ({ id: this.id() }),
    loader: ({ params }) =>
      firstValueFrom(this.http.get<ItemResponse<UserDetail>>('/users/' + params.id)).then((r) => {
        this.selectedRoles = r.data.roles;
        return r.data;
      }),
  });

  /** data.value() throws on an errored resource (e.g. 404) — null keeps the template alive */
  readonly safeValue = computed(() => {
    try {
      return this.data.value() ?? null;
    } catch {
      return null;
    }
  });

  /** T4-20: a non-throwing resource view — raw ResourceRef.value() throws on a failed load */
  readonly pageResource: LoadableResource = {
    value: () => this.safeValue() ?? undefined,
    error: () => {
      try {
        return this.data.error();
      } catch {
        return undefined;
      }
    },
    isLoading: () => this.data.isLoading(),
    reload: () => {
      this.data.reload();
    },
  };

  /** header title outside the async states: the user's name once loaded, the generic key otherwise */
  headerTitle(): string {
    return this.safeValue()?.name ?? 'user.detail.title';
  }

  onDeleted(): void {
    void this.router.navigate(['/users']);
  }

  saveRoles(userId: number): void {
    this.sending.set(true);
    void firstValueFrom(this.http.post<ActionResponse>('/users/' + userId + '/assign-roles', { roles: this.selectedRoles }))
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        this.data.reload();
      })
      .catch((err: unknown) => {
        this.sending.set(false);
        this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic');
      });
  }
}
