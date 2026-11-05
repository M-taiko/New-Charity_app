import { ChangeDetectionStrategy, Component, computed, inject, input, resource, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { firstValueFrom } from 'rxjs';
import type { HttpErrorResponse} from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { ButtonModule } from 'primeng/button';
import { SharedModule } from 'primeng/api';
import { MenuModule } from 'primeng/menu';
import type { MenuItem } from 'primeng/api';
import { ToastService } from '@core/errors/toast.service';
import { AuthService } from '@core/auth/auth.service';
import { ContentDialogComponent } from '@shared/ui/content-dialog/content-dialog.component';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { KeyValueListComponent } from '@shared/ui/key-value-list/key-value-list.component';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { LedgerTableComponent, LedgerBodyDirective, LedgerColDirective } from '@shared/ui/ledger-table/ledger-table.component';
import type { LedgerColumn } from '@shared/ui/ledger-table/ledger-table.component';
import { ConfirmDeleteComponent } from '@shared/ui/confirm-delete/confirm-delete.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import type { ActionResponse, ItemResponse } from '@shared/models/api.models';

/** Expense details (plan 20, T3-20): summary tiles, grouped info cards, review
 *  panel, the full Blade action set (delete / request edit / review) and an
 *  in-page attachment preview. Visibility mirrors expenses/modern-show. */
@Component({
  selector: 'app-expense-detail',
  standalone: true,
  imports: [MenuModule, TranslatePipe, SharedModule, ContentDialogComponent, RouterLink, ButtonModule, PageHeaderComponent, AsyncPageComponent, KeyValueListComponent, StatusStampComponent, PrintSheetComponent, StatTileComponent, LedgerTableComponent, LedgerBodyDirective, LedgerColDirective, ConfirmDeleteComponent, MoneyPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-print-sheet [title]="'expense.detail.title' | translate">
      <app-page-header [title]="headerTitle()" [subtitle]="safeValue()?.user ?? ''">
        <div actions class="u-flex u-gap-2 u-flex-wrap">
          <!-- T6-10: max 3 visible buttons; decisions live in the sticky bar -->
          <p-button [label]="'common.print' | translate" size="small" severity="secondary" [outlined]="true" (click)="printPage()" />
          @if (safeValue()?.can_edit) {
            <p-button [label]="'common.edit' | translate" size="small" severity="secondary" [outlined]="true" routerLink="['/expenses', id(), 'edit']" />
          }
          @if (moreMenuItems().length > 0) {
            <p-button [label]="'common.more' | translate" size="small" severity="secondary" [outlined]="true" (onClick)="moreMenu.toggle($event)" />
            <p-menu #moreMenu appendTo="body" [model]="moreMenuItems()" popup="true" />
          }
        </div>
      </app-page-header>

      <app-async-page [resource]="data" variant="detail" [backLink]="['/expenses']">
        @if (safeValue(); as e) {
          <!-- T6-10: one sticky action bar — primary decision first, destructive last -->
          <div class="sticky-actions u-flex u-gap-2 u-flex-wrap">
            @if (e.can_review) {
              <p-button [label]="'expense.list.review' | translate" size="small" severity="success" (onClick)="act('mark-reviewed')" [loading]="sending()" />
            }
            @if (e.can_unreview) {
              <p-button [label]="'expense.list.unreview' | translate" size="small" severity="warn" [outlined]="true" (onClick)="act('unreview')" [loading]="sending()" />
            }
            @if (canDelete()) {
              <p-button [label]="'common.delete' | translate" size="small" severity="danger" [outlined]="true" (onClick)="confirmDelete.open()" />
            }
          </div>

          <!-- summary band: amount / type / date / source -->
          <div class="kpi-grid">
            <app-stat-tile [label]="'expense.detail.amount'" [value]="e.amount" [money]="true" />
            <app-stat-tile [label]="'expense.detail.type'" [value]="typeLabel(e)" />
            <app-stat-tile [label]="'expense.detail.date'" [value]="fmtDate(e.expense_date)" />
            <app-stat-tile [label]="'expense.detail.source_tile'" [value]="sourceLabel(e)" />
          </div>

          <div class="detail-grid u-mt-2">
            <!-- main facts -->
            <section class="chart-card">
              <div class="chart-card__head">
                <span class="chart-card__title">{{ 'expense.detail.facts' | translate }}</span>
                @if (e.is_quick_expense) { <span class="stamp stamp--info">{{ 'expense.list.quick' | translate }}</span> }
              </div>
              <app-key-value-list [items]="facts(e)" />
            </section>

            <!-- review panel -->
            <section class="chart-card">
              <div class="chart-card__head"><span class="chart-card__title">{{ 'expense.detail.review_panel' | translate }}</span></div>
              <div class="detail-grid__stamp">
                <app-status-stamp [status]="e.reviewed ? 'reviewed' : 'not_reviewed'" namespace="review_state" [large]="true" />
              </div>
              @if (approvalState() === 'pending_edit') {
                <p class="u-mt-2"><span class="stamp stamp--pending">{{ 'expense.detail.state_pending_edit' | translate }}</span></p>
              } @else if (approvalState() === 'approved_edit') {
                <p class="u-mt-2"><span class="stamp stamp--info">{{ 'expense.detail.state_approved_edit' | translate }}</span></p>
              }
              @if (pendingEdit(); as req) {
                <p class="u-mt-2 u-small">
                  <a [routerLink]="['/expenses/edit-requests', req.id]">{{ 'expense.detail.view_request' | translate }}</a>
                </p>
              }
              <app-key-value-list [items]="reviewFacts(e)" />
            </section>
          </div>

          @if (lineItems().length) {
            <section class="chart-card u-mt-4">
              <div class="chart-card__head"><span class="chart-card__title">{{ 'expense.detail.line_items' | translate }}</span></div>
              <app-ledger-table [columns]="lineItemColumns" [rows]="lineItems()">
                <ng-template appLedgerBody let-item>
                  <tr class="ledger-table__row">
                    <td appLedgerCol="description">{{ item.description }}</td>
                    <td appLedgerCol="quantity"><span class="u-bidi">{{ item.quantity }}</span></td>
                    <td appLedgerCol="unit_price">{{ item.unit_price | money: false }}</td>
                    <td appLedgerCol="total">{{ lineTotal(item) | money: false }}</td>
                  </tr>
                </ng-template>
                <tfoot footer>
                  <tr class="ledger-table__total">
                    <td colspan="3">{{ 'common.total' | translate }}</td>
                    <td class="ledger-table__cell-num">{{ itemsTotal() | money: false }}</td>
                  </tr>
                </tfoot>
              </app-ledger-table>
            </section>
          }

          @if (e.custody_split.length > 1) {
            <section class="chart-card u-mt-4">
              <div class="chart-card__head"><span class="chart-card__title">{{ 'expense.detail.custody_split' | translate }}</span></div>
              <p class="u-small u-muted">{{ 'expense.detail.custody_split_note' | translate }}</p>
              @for (s of e.custody_split; track s.id) {
                <div class="u-flex-between u-small detail-split__row">
                  <a [routerLink]="['/custodies', s.id]">#{{ s.id }}</a>
                  <span class="u-bidi">{{ s.amount | money }}</span>
                </div>
              }
            </section>
          }
        }
      </app-async-page>

      <!-- attachment preview (T3-20): images inline, other files open/download -->
      <app-content-dialog
        [visible]="previewOpen()"
        (visibleChange)="previewOpen.set($event)"
        [header]="'expense.detail.attachment'"
        [width]="'min(720px, 94vw)'"
      >
        <div class="u-column u-gap-3 u-center">
          @if (attachmentIsImage()) {
            <img
              [src]="downloadUrl()"
              [alt]="'expense.detail.attachment_image_alt' | translate"
              [style.max-width]="'100%'"
              [style.max-height]="'65vh'"
              [style.object-fit]="'contain'"
              [style.margin-inline]="'auto'"
              [style.display]="'block'"
            />
          } @else {
            <div class="u-flex u-gap-2 u-center">
              <svg width="24" height="24" aria-hidden="true">
                <use href="assets/icons/sprite.svg#tabler-paperclip" />
              </svg>
              <span class="u-bidi">{{ attachmentName() }}</span>
            </div>
            <p class="u-small u-muted">{{ 'expense.detail.attachment_file_hint' | translate }}</p>
          }
        </div>
        <p-footer>
          <div class="u-flex u-gap-2 u-justify-end">
            <p-button [label]="'common.cancel' | translate" severity="secondary" [text]="true" (onClick)="previewOpen.set(false)" />
            <a class="p-button p-button-sm p-button-outlined p-button-text-only" [href]="downloadUrl()" target="_blank" rel="noopener">
              {{ 'expense.detail.download' | translate }}
            </a>
          </div>
        </p-footer>
      </app-content-dialog>

      <app-confirm-delete
        #confirmDelete
        [path]="'/expenses/' + id() + '/delete'"
        [entity]="'#' + id()"
        (deleted)="afterDelete()"
      />
    </app-print-sheet>
  `,
})
export class ExpenseDetailPage {
  /** secondary actions in the header More menu (T6-10) */
  readonly moreMenuItems = computed<MenuItem[]>(() => {
    const items: MenuItem[] = [];
    const e = this.safeValue();
    if (e?.has_attachment) {
      items.push({ label: this.translate.instant('expense.detail.view_attachment'), command: () => { this.openPreview(); } });
    }
    if (this.canRequestEdit()) {
      items.push({
        label: e?.reviewed
          ? this.translate.instant('expense.detail.request_edit_pending')
          : this.translate.instant('expense.detail.request_edit'),
        routerLink: ['/expenses', this.id(), 'edit-request'],
      });
    }
    return items;
  });

  private http = inject(HttpClient);
  private toast = inject(ToastService);
  private translate = inject(TranslateService);
  private router = inject(Router);
  private auth = inject(AuthService);
  private dateTime = new DatetimePipe();
  private money = new MoneyPipe();

  id = input.required<number>();
  sending = signal(false);
  previewOpen = signal(false);

  data = resource<ExpenseDetail, { id: number }>({
    params: () => ({ id: this.id() }),
    loader: ({ params }) =>
      firstValueFrom(this.http.get<ItemResponse<ExpenseDetail>>(`/expenses/${params.id}`)).then((r) => r.data),
  });

  /** data.value() throws on an errored resource (e.g. 404) — null keeps the template alive */
  readonly safeValue = computed(() => {
    try {
      return this.data.value() ?? null;
    } catch {
      return null;
    }
  });

  /** "#12 — 1,234.50 ج.م" (T4-30: the currency label comes from the money pipe, no literal) */
  readonly headerTitle = computed(() => {
    const e = this.safeValue();
    return e ? `#${e.id} — ${this.money.transform(e.amount)}` : `#${this.id()}`;
  });

  /** the pending edit request on this expense, if any (T3-20 item 5) */
  readonly pendingEdit = computed(() => this.safeValue()?.edit_requests?.find((r) => r.status === 'pending') ?? null);

  /** an approved edit request ≈ Blade's hasApprovedEdit()/isApproved() badges */
  readonly approvalState = computed<'pending_edit' | 'approved_edit' | null>(() => {
    const requests = this.safeValue()?.edit_requests ?? [];
    if (requests.some((r) => r.status === 'pending')) {
      return 'pending_edit';
    }
    if (requests.some((r) => r.status === 'approved')) {
      return 'approved_edit';
    }
    return null;
  });

  /** Blade 207: delete for محاسب or مدير (the API enforces it again) */
  readonly canDelete = computed(() => this.auth.can('is_accountant') || this.auth.can('is_manager'));

  /** Blade 193: request-edit for the owning agent, not approved, no pending edit request */
  readonly canRequestEdit = computed(
    () => this.auth.can('is_agent') && this.pendingEdit() === null && this.approvalState() !== 'approved_edit',
  );

  /** the static ledger columns for the line-items card (T4-21) */
  readonly lineItemColumns: LedgerColumn[] = [
    { field: 'description', header: 'expense.detail.item_description' },
    { field: 'quantity', header: 'expense.detail.item_quantity', numeric: true },
    { field: 'unit_price', header: 'expense.detail.item_unit_price', numeric: true },
    { field: 'total', header: 'expense.detail.item_total', numeric: true },
  ];

  lineItems = computed(() => {
    const items = this.data.value()?.line_items;
    return Array.isArray(items) ? (items as LineItem[]) : [];
  });

  lineTotal(item: LineItem): number {
    return (Number(item.quantity) || 0) * (Number(item.unit_price) || 0);
  }

  itemsTotal(): number {
    return this.lineItems().reduce((sum, i) => sum + this.lineTotal(i), 0);
  }

  downloadUrl(): string {
    return `/api/v1/expenses/${this.id()}/download-attachment`;
  }

  attachmentName(): string {
    return basename(this.safeValue()?.attachment ?? '');
  }

  attachmentIsImage(): boolean {
    return isImagePath(this.safeValue()?.attachment);
  }

  openPreview(): void {
    this.previewOpen.set(true);
  }

  afterDelete(): void {
    void this.router.navigate(['/expenses']);
  }

  fmtDate(value: string | null): string {
    return this.dateTime.transform(value, false);
  }

  /** enums.<ns>.<value> with the raw-code fallback — never blank */
  enumLabel(value: string | null | undefined, namespace: string): string {
    if (!value) return '—';
    const key = `enums.${namespace}.${value}`;
    const label = this.translate.instant(key);
    return label !== key ? label : value;
  }

  typeLabel(e: ExpenseDetail): string {
    return this.enumLabel(e.type, 'expense_type');
  }

  sourceLabel(e: ExpenseDetail): string {
    return this.translate.instant(e.source === 'treasury' ? 'expense.detail.source_treasury' : 'expense.detail.source_custody');
  }

  facts(e: ExpenseDetail): { label: string; value: string }[] {
    return [
      { label: 'expense.detail.direction', value: e.direction ?? '—' },
      { label: 'expense.detail.description', value: e.description ?? '—' },
      { label: 'expense.detail.location', value: e.location ?? '—' },
      { label: 'expense.detail.case', value: e.case ?? '—' },
      ...(e.notes ? [{ label: 'expense.detail.notes', value: e.notes }] : []),
    ];
  }

  reviewFacts(e: ExpenseDetail): { label: string; value: string }[] {
    return [
      {
        label: 'expense.detail.review_state',
        value: this.enumLabel(e.reviewed ? 'reviewed' : 'not_reviewed', 'review_state'),
      },
      { label: 'expense.detail.reviewed_by', value: e.reviewed_by ?? '—' },
      { label: 'expense.detail.reviewed_at', value: e.reviewed_at ? this.dateTime.transform(e.reviewed_at) : '—' },
      { label: 'expense.detail.created_at', value: e.created_at ? this.dateTime.transform(e.created_at) : '—' },
    ];
  }

  act(path: 'mark-reviewed' | 'unreview'): void {
    this.sending.set(true);
    void firstValueFrom(this.http.post<ActionResponse>(`/expenses/${this.id()}/${path}`, {}))
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        this.data.reload();
      })
      .catch((err: unknown) => { this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic'); });
  }

  printPage(): void {
    window.print();
  }
}

interface LineItem {
  description: string;
  quantity: number;
  unit_price: number;
}

interface EditRequestSummary {
  id: number;
  status: string;
  requested_by: string | null;
  requested_at: string | null;
}

interface ExpenseDetail {
  id: number; user: string | null; type: string; amount: string; description: string | null;
  created_at: string | null; expense_date: string | null; direction: string | null;
  reviewed: boolean; reviewed_by: string | null; reviewed_at: string | null;
  is_quick_expense: boolean; has_attachment: boolean;
  can_review: boolean; can_unreview: boolean; can_edit: boolean;
  case: string | null; location: string | null; notes: string | null;
  source: string | null; treasury: unknown;
  attachment: string | null;
  edit_requests: EditRequestSummary[] | null;
  custody_split: { id: number; amount: string }[];
  line_items: unknown;
}

/** "expense_attachments/abc.jpg" → "abc.jpg" (display only) */
function basename(path: string): string {
  if (!path) {
    return '—';
  }
  const parts = path.split('/');
  return parts[parts.length - 1] || path;
}

/** images preview inline in the dialog; everything else opens/downloads */
function isImagePath(path: string | null | undefined): boolean {
  return typeof path === 'string' && /\.(jpe?g|png|gif|webp)$/i.test(path);
}
