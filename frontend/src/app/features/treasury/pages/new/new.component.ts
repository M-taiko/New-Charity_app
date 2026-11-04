import type { OnInit } from '@angular/core';
import { ChangeDetectionStrategy, Component, computed, inject, input, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { TextareaModule } from 'primeng/textarea';
import { ToastService } from '@core/errors/toast.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import type { LoadableResource } from '@shared/ui/async-page/async-page.component';
import { FieldComponent } from '@shared/ui/field/field.component';
import { serverFieldError } from '@shared/lib/server-field-errors';
import { FormSectionComponent } from '@shared/ui/form-section/form-section.component';
import type { ActionResponse, ItemResponse } from '@shared/models/api.models';

/**
 * New + edit treasury form (plan 20): one component serving both routes —
 * /treasury/new and /treasury/:id/edit (the :id arrives as a component input).
 */
@Component({
  selector: 'app-treasury-form',
  standalone: true,
  imports: [
    TranslatePipe, RouterLink, FormsModule, ButtonModule, InputTextModule, TextareaModule,
    PageHeaderComponent, FieldComponent, FormSectionComponent, AsyncPageComponent,
  ],
  template: `
    <app-page-header [title]="isEdit() ? ('treasury.form.edit_title' | translate) : ('treasury.form.new_title' | translate)">
      <div actions>
        <p-button [label]="'common.back_to_list' | translate" size="small" [text]="true" routerLink="/treasury" />
      </div>
    </app-page-header>
    <!-- T5-07: a failed edit-load shows the error state with retry instead of a
         blank form that could be saved over the real treasury -->
    <app-async-page [resource]="pageState" variant="form" [backLink]="['/treasury']">
      <form (ngSubmit)="save()" #f="ngForm">
        <app-form-section>
          <app-field label="treasury.form.name" controlId="treasury-name" [required]="true" [error]="serverFieldError(error(), 'name')">
            <input pInputText id="treasury-name" name="name" class="u-full" [(ngModel)]="name" required />
          </app-field>
          <app-field label="treasury.form.notes" controlId="treasury-notes" [error]="serverFieldError(error(), 'notes')">
            <textarea pTextarea id="treasury-notes" name="notes" rows="3" class="u-full" [(ngModel)]="notes"></textarea>
          </app-field>
        </app-form-section>
        @if (alert(); as msg) {
          <div class="dialog__alert" role="alert">{{ msg }}</div>
        }
        <div class="sticky-actions">
          <p-button [label]="'common.back' | translate" severity="secondary" [text]="true" routerLink="/treasury" />
          <p-button type="submit" [label]="'common.save' | translate" [loading]="sending()" [disabled]="f.invalid || sending()" />
        </div>
      </form>
    </app-async-page>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TreasuryFormPage implements OnInit {
  private http = inject(HttpClient);
  private router = inject(Router);
  private toast = inject(ToastService);

  /** bound from /treasury/:id/edit; absent on /treasury/new */
  id = input<number | undefined>(undefined);

  name = signal('');
  notes = signal('');
  sending = signal(false);
  error = signal<HttpErrorResponse | null>(null);
  /** exposed to the template — templates see class members, not module imports */
  readonly serverFieldError = serverFieldError;
  loadedId: number | null = null;

  isEdit = computed(() => this.id() !== undefined);

  private loading = signal(true);
  private loadError = signal<unknown>(undefined);
  private loaded = signal(false);

  /** adapter so app-async-page drives this page's load/error/skeleton states (T5-07) */
  readonly pageState: LoadableResource = {
    value: () => (this.isEdit() ? this.loaded() : true),
    error: () => this.loadError(),
    isLoading: () => this.loading(),
    reload: () => { this.load(); },
  };

  ngOnInit(): void {
    if (this.isEdit()) {
      this.load();
    } else {
      this.loading.set(false);
    }
  }

  load(): void {
    const id = this.id();
    if (id === undefined) {
      return;
    }
    this.loading.set(true);
    this.loadError.set(undefined);
    void firstValueFrom(this.http.get<ItemResponse<{ name: string; notes: string | null }>>(`/treasury/${id}`)).then(
      (res) => {
        this.name.set(res.data.name);
        this.notes.set(res.data.notes ?? '');
        this.loading.set(false);
        this.loaded.set(true);
      },
    ).catch((err: unknown) => {
      this.loading.set(false);
      this.loadError.set(err);
    });
  }

  /** the general alert text — the server message or the generic fallback key */
  alert(): string | null {
    const err = this.error();
    if (err === null) {
      return null;
    }
    return err.error?.message ?? 'errors.generic';
  }

  save(): void {
    this.sending.set(true);
    this.error.set(null);
    const body = { name: this.name(), notes: this.notes() };
    const id = this.id();
    const request$ = id
      ? this.http.post<ActionResponse>(`/treasury/${id}/update`, body)
      : this.http.post<ActionResponse>('/treasury', body);

    void firstValueFrom(request$)
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        void this.router.navigateByUrl('/treasury');
      })
      .catch((err: unknown) => {
        this.sending.set(false);
        this.error.set(err instanceof HttpErrorResponse ? err : null);
      });
  }
}
