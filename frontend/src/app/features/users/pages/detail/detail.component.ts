// User details (plan 20): info + role assignment (several roles allowed).
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { computed, ChangeDetectionStrategy, Component, inject, input, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { SelectModule } from 'primeng/select';
import { ToastService } from '@core/errors/toast.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { KeyValueListComponent } from '@shared/ui/key-value-list/key-value-list.component';
import { ActionResponse, ItemResponse } from '@shared/models/api.models';

interface UserDetail {
  id: number; name: string; email: string; phone: string | null;
  roles: string[]; is_active: boolean;
}

@Component({
  selector: 'app-user-detail',
  standalone: true,
  imports: [TranslatePipe, RouterLink, FormsModule, ButtonModule, SelectModule, EmptyStateComponent, PageHeaderComponent, KeyValueListComponent],
  template: `
    @if (safeValue(); as u) {
      <app-page-header [title]="u.name" [subtitle]="'user.detail.title' | translate">
        <div actions class="u-flex u-gap-2">
          <p-button [label]="'common.edit' | translate" size="small" [routerLink]="['/users', u.id, 'edit']" />
          <p-button [label]="'common.back_to_list' | translate" size="small" [text]="true" routerLink="/users" />
        </div>
      </app-page-header>
      <div class="u-mb-4">
        <span class="stamp" [class.stamp--success]="u.is_active" [class.stamp--neutral]="!u.is_active">
          {{ (u.is_active ? 'user.list.active' : 'user.list.inactive') | translate }}
        </span>
      </div>
      <app-key-value-list [items]="[
        { label: 'user.detail.name', value: u.name },
        { label: 'user.detail.email', value: u.email },
        { label: 'user.detail.phone', value: u.phone || '—' },
        { label: 'user.detail.roles', value: u.roles.join('، ') }
      ]" />
      <section class="form-section u-mt-4">
        <h3 class="form-section__title">{{ 'user.form.roles' | translate }}</h3>
        <p class="field__hint">{{ 'user.form.roles_hint' | translate }}</p>
        <p-select appendTo="body" [options]="roleOptions ?? []" optionLabel="label" optionValue="value" [(ngModel)]="selectedRoles" name="selectedRoles" multiple styleClass="u-full" />
        <div class="u-flex" style="justify-content: flex-end">
          <p-button [label]="'common.save' | translate" (onClick)="saveRoles(u.id)" [loading]="sending()" />
        </div>
      </section>
    } @else if (data.isLoading()) {
      <div class="skeleton skeleton--title u-mb-4"></div>
      <div class="skeleton skeleton--tile"></div>
    } @else {
      <app-empty-state [title]="'common.not_found' | translate" [text]="'common.not_found_text' | translate" />
    }
  `,
})
export class UserDetailPage {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  id = input.required<number>();
  sending = signal(false);
  selectedRoles: string[] = [];

  readonly roleOptions = [
    { label: 'مدير', value: 1 },
    { label: 'محاسب', value: 2 },
    { label: 'مندوب', value: 3 },
    { label: 'باحث اجتماعي', value: 4 },
    { label: 'مشرف', value: 5 },
  ];

  data = resource<UserDetail, { id: number }>({
    params: () => ({ id: this.id() }),
    loader: ({ params }) =>
      firstValueFrom(this.http.get<ItemResponse<UserDetail>>('/users/' + params.id)).then((r) => {
        this.selectedRoles = r.data.roles ?? [];
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

  saveRoles(userId: number): void {
    this.sending.set(true);
    void firstValueFrom(this.http.post<ActionResponse>('/users/' + userId + '/assign-roles', { roles: this.selectedRoles }))
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        this.data.reload();
      })
      .catch((err: HttpErrorResponse) => {
        this.sending.set(false);
        this.toast.apiError(err.error?.message ?? 'errors.generic');
      });
  }
}
