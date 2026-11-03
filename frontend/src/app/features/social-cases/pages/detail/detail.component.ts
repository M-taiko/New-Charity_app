import { ChangeDetectionStrategy, Component, computed, inject, input, resource, signal, viewChild } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { ToastService } from '@core/errors/toast.service';
import { AuthService } from '@core/auth/auth.service';
import { ApiClient } from '@core/http/api-client';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { KeyValueListComponent } from '@shared/ui/key-value-list/key-value-list.component';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { ReasonDialogComponent } from '@shared/ui/reason-dialog/reason-dialog.component';
import { ConfirmDeleteComponent } from '@shared/ui/confirm-delete/confirm-delete.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import type { ItemResponse } from '@shared/models/api.models';

interface CaseExpense { id: number; user: string | null; amount: string; description: string | null; date: string | null }
interface CaseFamilyMember { id: number; name: string; relation: string | null; gender: string | null; phone: string | null; status: string | null }
interface CaseDocument { id: number; name: string; path: string; type: string | null; created_at: string | null }

/** The Api\V1\SocialCaseController@show payload. */
interface CaseDetail {
  id: number;
  name: string;
  national_id: string | null;
  phone: string | null;
  researcher: string | null;
  assistance_type: string | null;
  status: string;
  is_active: boolean;
  description: string | null;
  internal_notes: string | null;
  address: string | null;
  governorate: string | null;
  district: string | null;
  nationality: string | null;
  marital_status: string | null;
  monthly_income: string;
  monthly_expenses: string;
  income_source: string | null;
  housing_type: string | null;
  housing_status: string | null;
  family_members_count: number;
  affiliated_to: string | null;
  gender: string | null;
  reviewed_by: string | null;
  reviewed_at: string | null;
  created_at: string | null;
  family_members: CaseFamilyMember[];
  documents: CaseDocument[];
  expenses: CaseExpense[];
  total_spent: string;
}

/**
 * Case detail (T3-51): every Blade modern-show section — case data,
 * detailed info, the manager warning, related expenses, family members,
 * attached documents, summary, rejection notes and spending summary — plus
 * approve / reject (required `notes`) / edit / toggle-active / delete.
 */
@Component({
  selector: 'app-case-detail',
  standalone: true,
  imports: [
    EmptyStateComponent, TranslatePipe, RouterLink, ButtonModule, PageHeaderComponent,
    KeyValueListComponent, StatusStampComponent, StatTileComponent, PrintSheetComponent,
    ReasonDialogComponent, ConfirmDeleteComponent, MoneyPipe, DatetimePipe,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (safeValue(); as item) {
      <app-print-sheet [title]="'case.detail.title' | translate">
        <app-page-header [title]="itemTitle(item)">
          <div actions class="u-flex u-gap-2 u-flex-wrap">
            @if (canReview() && item.status === 'pending') {
              <p-button [label]="'case.detail.approve' | translate" size="small" (onClick)="act('approve')" [loading]="sending()" />
              <p-button [label]="'case.detail.reject' | translate" size="small" severity="danger" [outlined]="true" (onClick)="reject.open()" [loading]="sending()" />
            }
            @if (canManage()) {
              <p-button [label]="item.is_active ? ('case.actions.deactivate' | translate) : ('case.actions.activate' | translate)" size="small" severity="secondary" [outlined]="true" (onClick)="act('toggle-active')" [loading]="sending()" />
              <p-button [label]="'common.edit' | translate" size="small" [routerLink]="['/cases', item.id, 'edit']" />
              <p-button [label]="'common.delete' | translate" size="small" severity="danger" [outlined]="true" (onClick)="confirmDelete.open()" />
            }
            <p-button [label]="'common.back_to_list' | translate" size="small" [text]="true" routerLink="/cases" />
          </div>
        </app-page-header>

        <div class="u-mb-4"><app-status-stamp [status]="item.status" namespace="social_case" [large]="true" /></div>

        <div class="kpi-grid">
          <app-stat-tile [label]="'case.form.monthly_income'" [value]="item.monthly_income" [money]="true" />
          <app-stat-tile [label]="'case.form.monthly_expenses'" [value]="item.monthly_expenses" [money]="true" />
          <app-stat-tile [label]="'case.detail.total_spent'" [value]="item.total_spent" [money]="true" />
          <app-stat-tile [label]="'case.form.family_members_count'" [value]="item.family_members_count" />
        </div>

        @if (managerWarning(item); as warning) {
          <div class="dialog__alert" role="alert">
            <svg width="16" height="16" aria-hidden="true"><use href="assets/icons/sprite.svg#tabler-alert-triangle" /></svg>
            <span><strong>{{ 'case.detail.manager_warning' | translate }}</strong> — {{ warning }}</span>
          </div>
        }

        <div class="detail-grid u-mt-4">
          <section class="chart-card">
            <div class="chart-card__head"><span class="chart-card__title">{{ 'case.detail.title' | translate }}</span></div>
            <app-key-value-list [items]="keyValues(item)" />
          </section>

          <section class="chart-card">
            <div class="chart-card__head"><span class="chart-card__title">{{ 'case.detail.summary' | translate }}</span></div>
            <app-key-value-list [items]="summaryValues(item)" />
            @if (item.status === 'rejected' && item.internal_notes) {
              <div class="u-mt-4">
                <div class="chart-card__head"><span class="chart-card__title">{{ 'case.detail.rejection_notes' | translate }}</span></div>
                <p class="u-small">{{ item.internal_notes }}</p>
              </div>
            }
            @if (hasSpending(item)) {
              <div class="u-mt-4">
                <div class="chart-card__head"><span class="chart-card__title">{{ 'case.detail.spending_summary' | translate }}</span></div>
                <div class="u-flex-between u-small">
                  <span>{{ 'case.detail.total_spent' | translate }}</span>
                  <strong class="u-bidi">{{ item.total_spent | money }}</strong>
                </div>
                <div class="u-flex-between u-small u-mt-2">
                  <span>{{ 'case.detail.expenses_count' | translate }}</span>
                  <span class="u-bidi">{{ item.expenses.length }}</span>
                </div>
              </div>
            }
          </section>
        </div>

        @if (item.description) {
          <section class="chart-card u-mt-4">
            <div class="chart-card__head"><span class="chart-card__title">{{ 'case.detail.detailed' | translate }}</span></div>
            <p class="u-small">{{ item.description }}</p>
          </section>
        }

        <section class="chart-card u-mt-4">
          <div class="chart-card__head"><span class="chart-card__title">{{ 'case.detail.expenses' | translate }}</span></div>
          @if (item.expenses.length) {
            <table class="ledger-table ledger-table--borderless">
              <thead>
                <tr>
                  <th>#</th>
                  <th>{{ 'case.detail.col_user' | translate }}</th>
                  <th>{{ 'case.detail.col_amount' | translate }}</th>
                  <th>{{ 'case.detail.col_date' | translate }}</th>
                  <th>{{ 'common.description' | translate }}</th>
                </tr>
              </thead>
              <tbody>
                @for (e of item.expenses; track e.id) {
                  <tr class="ledger-table__row">
                    <td><a [routerLink]="['/expenses', e.id]" class="u-bidi">#{{ e.id }}</a></td>
                    <td>{{ e.user ?? '—' }}</td>
                    <td class="ledger-table__cell-num"><span class="u-bidi">{{ e.amount | money }}</span></td>
                    <td><span class="u-bidi">{{ e.date | datetime: false }}</span></td>
                    <td>{{ e.description ?? '—' }}</td>
                  </tr>
                }
              </tbody>
            </table>
          } @else {
            <app-empty-state [title]="'case.detail.empty_expenses' | translate" [text]="'common.no_data' | translate" />
          }
        </section>

        <section class="chart-card u-mt-4">
          <div class="chart-card__head"><span class="chart-card__title">{{ 'case.detail.family_members' | translate }} ({{ item.family_members.length }})</span></div>
          @if (item.family_members.length) {
            <table class="ledger-table ledger-table--borderless">
              <thead>
                <tr>
                  <th>#</th>
                  <th>{{ 'case.form.member_name' | translate }}</th>
                  <th>{{ 'case.form.relation' | translate }}</th>
                  <th>{{ 'case.form.member_gender' | translate }}</th>
                  <th>{{ 'case.form.member_phone' | translate }}</th>
                </tr>
              </thead>
              <tbody>
                @for (m of item.family_members; track m.id; let i = $index) {
                  <tr class="ledger-table__row">
                    <td class="u-bidi">{{ i + 1 }}</td>
                    <td>{{ m.name }}</td>
                    <td>{{ label('case.enums.relation.' + m.relation) }}</td>
                    <td>{{ label('case.enums.gender.' + m.gender) }}</td>
                    <td><span class="u-bidi">{{ m.phone ?? '—' }}</span></td>
                  </tr>
                }
              </tbody>
            </table>
          } @else {
            <app-empty-state [title]="'case.modal.empty_family' | translate" [text]="'common.no_data' | translate" />
          }
        </section>

        @if (item.documents.length) {
          <section class="chart-card u-mt-4">
            <div class="chart-card__head"><span class="chart-card__title">{{ 'case.detail.documents' | translate }}</span></div>
            <div class="u-column">
              @for (d of item.documents; track d.id) {
                <div class="attachments__file">
                  <svg width="16" height="16" aria-hidden="true"><use href="assets/icons/sprite.svg#tabler-paperclip" /></svg>
                  <span class="attachments__name u-bidi">{{ d.name }}</span>
                  <span class="u-small u-muted">{{ label('case.enums.doc_type.' + d.type) }}</span>
                  <a [href]="storageUrl(d.path)" target="_blank" rel="noopener">{{ 'case.detail.view_document' | translate }}</a>
                </div>
              }
            </div>
          </section>
        }
      </app-print-sheet>

      <app-reason-dialog #reject
        [header]="'case.detail.reject_dialog'"
        [label]="'case.detail.reject_reason'"
        [submitLabel]="'case.detail.reject'"
        [sending]="sending()"
        [error]="actionError()"
        (submit)="submitReject($event)"
      />
      <app-confirm-delete #confirmDelete [path]="'/social-cases/' + item.id + '/delete'" [entity]="item.name" (deleted)="onDeleted()" />
    } @else if (data.isLoading()) {
      <div class="skeleton skeleton--title u-mb-4"></div>
      <div class="skeleton skeleton--tile"></div>
    } @else {
      <app-empty-state [title]="'common.not_found' | translate" [text]="'common.not_found_text' | translate" />
    }
  `,
})
export class CaseDetailPage {
  private http = inject(HttpClient);
  private api = inject(ApiClient);
  private auth = inject(AuthService);
  private router = inject(Router);
  private toast = inject(ToastService);
  private translate = inject(TranslateService);

  id = input.required<number>();
  sending = signal(false);
  actionError = signal<HttpErrorResponse | null>(null);
  private rejectDialog = viewChild(ReasonDialogComponent);
  private datePipe = new DatetimePipe();

  data = resource<CaseDetail, { id: number }>({
    params: () => ({ id: this.id() }),
    loader: ({ params }) =>
      firstValueFrom(this.http.get<ItemResponse<CaseDetail>>(`/social-cases/${params.id}`)).then((res) => res.data),
  });

  /** data.value() throws on an errored resource (e.g. 404) — null keeps the template alive */
  readonly safeValue = computed(() => {
    try {
      return this.data.value() ?? null;
    } catch {
      return null;
    }
  });

  /** Blade @can('review_social_case') / @can('manage_social_cases') */
  canReview(): boolean {
    return this.auth.can('may_review_social_case');
  }

  canManage(): boolean {
    return this.auth.can('may_manage_social_cases');
  }

  itemTitle(item: CaseDetail): string {
    return `${item.name} #${item.id}`;
  }

  hasSpending(item: CaseDetail): boolean {
    return Number(item.total_spent) > 0;
  }

  /** Blade's "important warning for the manager" — expenses above income */
  managerWarning(item: CaseDetail): string | null {
    const income = Number(item.monthly_income);
    const expenses = Number(item.monthly_expenses);
    if (!income || !expenses || expenses <= income) {
      return null;
    }
    const diff = (expenses - income).toFixed(2);
    return this.translate.instant('case.detail.manager_warning_text', { expenses: this.money(expenses), income: this.money(income), diff: this.money(Number(diff)) });
  }

  private money(value: number): string {
    return value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  storageUrl(path: string): string {
    return `/storage/${path}`;
  }

  /** localized enum label with the raw-code fallback (never blank) */
  label(key: string): string {
    const value = key.slice(key.lastIndexOf('.') + 1);
    if (!value || value === 'null') {
      return '—';
    }
    const translated = this.translate.instant(key);
    return translated !== key ? translated : value;
  }

  keyValues(item: CaseDetail): { label: string; value: string }[] {
    return [
      { label: 'case.detail.name', value: item.name },
      { label: 'case.detail.phone', value: item.phone ?? '—' },
      { label: 'case.form.national_id', value: item.national_id ?? '—' },
      { label: 'case.detail.researcher', value: item.researcher ?? '—' },
      { label: 'case.detail.assistance', value: this.label(`case.enums.assistance.${item.assistance_type}`) },
      { label: 'case.form.nationality', value: this.label(`case.enums.nationality.${item.nationality}`) },
      { label: 'case.detail.governorate', value: item.governorate ?? '—' },
      { label: 'case.detail.district', value: item.district ?? '—' },
      { label: 'case.detail.address', value: item.address ?? '—' },
      { label: 'case.detail.marital', value: this.label(`case.enums.marital.${item.marital_status}`) },
      { label: 'case.form.income_source', value: item.income_source ?? '—' },
      { label: 'case.form.housing_type', value: this.label(`case.enums.house_type.${item.housing_type}`) },
      { label: 'case.form.housing_status', value: this.label(`case.enums.house_condition.${item.housing_status}`) },
      { label: 'case.form.affiliated', value: item.affiliated_to ?? '—' },
    ];
  }

  summaryValues(item: CaseDetail): { label: string; value: string }[] {
    return [
      { label: 'case.detail.summary_id', value: `#${item.id}` },
      { label: 'case.detail.created', value: this.dateTime(item.created_at) },
      { label: 'case.detail.researcher', value: item.researcher ?? '—' },
      { label: 'case.detail.reviewer', value: item.reviewed_by ?? '—' },
      { label: 'case.detail.reviewed_at', value: this.dateTime(item.reviewed_at) },
    ];
  }

  private dateTime(value: string | null): string {
    return value ? this.datePipe.transform(value) ?? '—' : '—';
  }

  act(path: string, body: Record<string, unknown> = {}): void {
    this.sending.set(true);
    this.actionError.set(null);
    void firstValueFrom(this.api.action(`/social-cases/${this.id()}/${path}`, body)).then((res) => {
      this.sending.set(false);
      this.toast.success(res.message);
      this.data.reload();
    }).catch((err: unknown) => {
      this.sending.set(false);
      this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic', (err as HttpErrorResponse).error?.code);
    });
  }

  /** the reject endpoint saves the typed text into internal_notes via the `notes` field */
  submitReject(notes: string): void {
    this.sending.set(true);
    this.actionError.set(null);
    void firstValueFrom(this.api.action(`/social-cases/${this.id()}/reject`, { notes })).then((res) => {
      this.sending.set(false);
      this.toast.success(res.message);
      this.rejectDialog()?.done();
      this.data.reload();
    }).catch((err: unknown) => {
      this.sending.set(false);
      this.actionError.set((err as HttpErrorResponse));
      this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic', (err as HttpErrorResponse).error?.code);
    });
  }

  onDeleted(): void {
    void this.router.navigateByUrl('/cases');
  }
}
