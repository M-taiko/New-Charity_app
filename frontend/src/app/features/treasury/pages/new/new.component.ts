import type { OnInit } from '@angular/core';
import { ChangeDetectionStrategy, Component, computed, inject, input, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { TextareaModule } from 'primeng/textarea';
import { ToastService } from '@core/errors/toast.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import type { ActionResponse, ItemResponse } from '@shared/models/api.models';

/**
 * New + edit treasury form (plan 20): one component serving both routes —
 * /treasury/new and /treasury/:id/edit (the :id arrives as a component input).
 */
@Component({
  selector: 'app-treasury-form',
  standalone: true,
  imports: [TranslatePipe, RouterLink, FormsModule, ButtonModule, InputTextModule, TextareaModule, PageHeaderComponent],
  template: `
    <app-page-header [title]="isEdit() ? ('treasury.form.edit_title' | translate) : ('treasury.form.new_title' | translate)" />
    <form class="form-section" (ngSubmit)="save()" #f="ngForm">
      <div class="form-section__grid">
        <div class="field">
          <label class="field__label" for="name">
            {{ 'treasury.form.name' | translate }} <span class="field__required" aria-hidden="true">*</span>
          </label>
          <input pInputText id="name" name="name" [(ngModel)]="name" required style="width: 100%" />
        </div>
        <div class="field">
          <label class="field__label" for="notes">{{ 'treasury.form.notes' | translate }}</label>
          <textarea pTextarea id="notes" name="notes" rows="3" [(ngModel)]="notes" style="width: 100%"></textarea>
        </div>
      </div>
      @if (error()) {
        <div class="dialog__alert" role="alert">{{ error() }}</div>
      }
      <div class="sticky-actions">
        <p-button [label]="'common.back' | translate" severity="secondary" [text]="true" routerLink="/treasury" />
        <p-button type="submit" [label]="'common.save' | translate" [loading]="sending()" [disabled]="f.invalid || sending()" />
      </div>
    </form>
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
  error = signal('');
  loadedId: number | null = null;

  isEdit = computed(() => this.id() !== undefined);

  ngOnInit(): void {
    const id = this.id();
    if (id !== undefined) {
      void firstValueFrom(this.http.get<ItemResponse<{ name: string; notes: string | null }>>(`/treasury/${id}`)).then(
        (res) => {
          this.name.set(res.data.name);
          this.notes.set(res.data.notes ?? '');
        },
      );
    }
  }

  save(): void {
    this.sending.set(true);
    this.error.set('');
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
        this.error.set((err as HttpErrorResponse).error?.message ?? 'errors.generic');
      });
  }
}
