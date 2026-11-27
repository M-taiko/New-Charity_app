import { ChangeDetectionStrategy, Component, computed, inject, resource, signal, viewChild } from '@angular/core';
import { RouterLink } from '@angular/router';
import { DatePipe, DecimalPipe } from '@angular/common';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { SelectModule } from 'primeng/select';
import { MenuModule } from 'primeng/menu';
import { PopoverModule, Popover } from 'primeng/popover';
import { ButtonModule } from 'primeng/button';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { AuthService } from '@core/auth/auth.service';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { ChartCardComponent } from '@shared/ui/chart-card/chart-card.component';
import type { ChartSeries } from '@shared/ui/chart-card/chart-card.component';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { IconComponent } from '@shared/ui/icon/icon.component';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import type { KeyValueItem } from '@shared/ui/key-value-list/key-value-list.component';
import { reloadList } from '@shared/lib/list-state';
import { apiFailure } from '@shared/lib/server-field-errors';
import { ActionButtonComponent } from '@shared/ui/actions/action-button.component';
import { LocalStorageService } from '@core/storage/local-storage.service';
import { ApiClient } from '@core/http/api-client';
import { ToastService } from '@core/errors/toast.service';
import { BalanceRingComponent } from '@shared/ui/balance-ring/balance-ring.component';
import { entityColor } from '@shared/lib/chart-colors';
import { ConfirmDialogComponent } from '@shared/ui/confirm-dialog/confirm-dialog.component';
import { ActionIconComponent } from '@shared/ui/actions/action-button.component';
import type { ItemResponse } from '@shared/models/api.models';
import type { MenuItem } from 'primeng/api';

interface AgentStats {
  received: string;
  spent: string;
  returned: string;
  transferred_out: string;
  frozen: string;
  pending_return: string;
  remaining: string;
}

interface ResearcherStats {
  total_cases: number;
  approved_cases: number;
  pending_cases: number;
  rejected_cases: number;
  approval_rate: number;
  recent_cases: { id: number; name: string; status: string; created_at: string | null }[];
}

interface DashboardData {
  view: 'finance' | 'agent' | 'researcher';
  range?: string;
  treasury_balance: string;
  total_treasury_and_custodies: string;
  total_custodies_amount: string;
  active_custodies: number;
  /** B-08: status=pending custodies — its own attention row */
  pending_custodies: number;
  /** B-08: active custodies with money left and no expense for 7+ days */
  custodies_needing_follow_up: number;
  pending_cases: number;
  today_expenses: string;
  total_spent: string;
  agent_stats: AgentStats | null;
  researcher_stats: ResearcherStats | null;
  year_stats: {
    selected_year: number;
    available_years: number[];
    total_custodies_amount: string;
    total_expenses: string;
    total_returned: string;
    approved_cases: number;
  };
  agents_evaluation: {
    agent: { id: number; name: string };
    rating: number;
    remaining: string;
    expense_count: number;
    custody_count: number;
  }[];
  researchers_evaluation: {
    researcher: { id: number; name: string };
    total_cases: number;
    approval_rate: number;
    rating: number;
  }[];
  direction_figures: { grand_total: number; today: number; by_day?: { label: string; date: string; amount: number }[] } | null;
}

/** one customizable widget (T6-60): id + i18n title */
interface WidgetDef {
  id: string;
  titleKey: string;
}

/** GET /dashboard/performance (T7-08) — the heavy widget data, lazy endpoint */
interface PerformanceData {
  kpis: Record<'expenses' | 'income' | 'custodies' | 'cases', { value: string; prev: string; delta: number; spark: { d: string; v: string }[] }>;
  aging: { bucket: string; count: number; total: string }[];
  case_funnel: { stage: string; count: number }[];
  approval_rate: number;
  /** `id` is the expense category's id (N-07) — a response cached before it was added may omit it */
  spending_by_direction: { id?: number; label: string; value: string }[];
  hr: { attendance_today: Record<string, number>; pending_salaries: { count: number; total: string }; kpi_achievement: number };
}

/** a pending custody transfer row from /custodies/mine (agent view, T7-08) */
interface MineTransfer {
  id: number;
  amount: string;
  status: string;
  notes: string | null;
  created_at: string | null;
  /** the same endpoint's party names (N-06: shown in the confirm) */
  sender?: string | null;
  receiver?: string | null;
}

interface MineTransfers {
  sent_transfers: MineTransfer[];
  received_transfers: MineTransfer[];
}

/** GET /dashboard/activity (T7-08) — the lazy activity feed */
interface ActivityFeedItem {
  id: number;
  event: string;
  description: string;
  user: string | null;
  created_at: string | null;
}

const DEFAULT_WIDGETS: WidgetDef[] = [
  { id: 'kpi', titleKey: 'dashboard.bento.kpi' },
  { id: 'quick-actions', titleKey: 'dashboard.bento.quick_actions' },
  { id: 'attention', titleKey: 'dashboard.bento.attention' },
  { id: 'insights', titleKey: 'dashboard.bento.insights' },
  { id: 'money-flow', titleKey: 'dashboard.bento.money_flow' },
  { id: 'performance', titleKey: 'dashboard.bento.performance' },
  { id: 'activity', titleKey: 'dashboard.bento.activity' },
];

/** N-09: same widgets in the same order */
function sameWidgetOrder(a: readonly string[], b: readonly string[]): boolean {
  return a.length === b.length && a.every((id, i) => id === b[i]);
}

/**
 * The dashboard rewrite (Task6 T6-60): a responsive bento grid — 1 column on
 * phones, 2 on tablets, 12-col spans on desktop. Every widget is a card with
 * a title, skeleton (async-page), empty state and "view all" link; the KPI
 * strip counts up and links to its page; ratings are ranked lists with
 * progress bars (never doughnuts); chart legends are HTML lists under the
 * chart so nothing wraps inside the canvas. Role variants: finance, agent,
 * researcher. The widget order/visibility is saved per user.
 */
@Component({
  selector: 'app-dashboard-page',
  standalone: true,
  imports: [
    TranslatePipe,
    DecimalPipe,
    DatePipe,
    RouterLink,
    ActionButtonComponent,
    ActionIconComponent,
    SelectModule,
    MenuModule,
    PopoverModule,
    ButtonModule,
    FormsModule,
    AsyncPageComponent,
    ChartCardComponent,
    StatTileComponent,
    StatusStampComponent,
    IconComponent,
    DatetimePipe,
    MoneyPipe,
    BalanceRingComponent,
    ConfirmDialogComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="dash-topbar">
      <div>
        <h1 class="dash-topbar__greeting">{{ 'dashboard.welcome' | translate: { name: userName() } }}</h1>
        <p class="dash-topbar__date">{{ today | date: dateFormat }}</p>
      </div>
      <div class="dash-topbar__actions u-flex u-gap-2 u-flex-wrap">
        <p-select appendTo="body" inputId="dash-range" [options]="rangeOptions" optionLabel="label" optionValue="value"
                  [ngModel]="range()" (ngModelChange)="setRange($event)" name="range" styleClass="dash-topbar__select" />
        <!-- P-01: quiet "⋯" menu — refresh + the customize popover live here,
             the header keeps ONE primary (create custody) -->
        <p-button [text]="true" [rounded]="true" severity="secondary" [ariaLabel]="'dashboard.bento.more' | translate" (onClick)="headerMenu.toggle($event)">
          <app-action-icon name="dots-vertical" />
        </p-button>
        <p-menu #headerMenu [model]="headerMenuItems()" popup="true" appendTo="body" />
        <p-popover #customizePop appendTo="body">
          <div class="dash-customize__pop">
            <strong class="dash-customize__title">{{ 'dashboard.bento.customize' | translate }}</strong>
            <div class="u-flex u-gap-2 u-flex-wrap">
              @for (w of allWidgets(); track w.id) {
                <label class="dash-customize__item">
                  <input type="checkbox" [checked]="isWidgetVisible(w.id)" (change)="toggleWidget(w.id)" />
                  {{ w.titleKey | translate }}
                </label>
              }
            </div>
          </div>
        </p-popover>
        @if (canCreateCustody()) {
          <app-action-button kind="create" label="custody.list.create" [route]="['/custodies', 'new']" />
        }
      </div>
    </div>

    <app-async-page [resource]="data" variant="list" [hold]="!layoutSettled()">
      @if (data.value(); as d) {
        <!-- N-09: laid out once — only when the widget order can no longer change under it -->
        @if (layoutSettled()) {
        <div class="bento">
          <!-- KPI strip (RD-4.1 → R2-03): the Stitch 4-card row sits DIRECTLY
               on the page — no wrapper card around the tiles -->
          @if (visible('kpi')) {
            <section class="bento__widget bento__widget--full bento__widget--bare" [attr.aria-label]="'dashboard.bento.kpi' | translate">
              @if (d.view === 'finance') {
                <div class="kpi-grid">
                  <app-stat-tile class="tile-link" label="dashboard.finance.treasury_balance" [value]="d.treasury_balance" [money]="true" iconName="custody" accent="treasury" caption="dashboard.finance.available_now" />
                  <!-- N-09: the two tiles that get a delta + sparkline from the
                       slower /performance request reserve those rows from the
                       first frame (reserveSpark) — the row used to grow by
                       67px when that request landed and push the page down -->
                  <app-stat-tile class="tile-link" label="dashboard.finance.total_custodies" [value]="d.total_custodies_amount" [money]="true" iconName="myExpense" accent="custodies" caption="dashboard.finance.n_active_custodies" [captionParams]="{ n: d.active_custodies }" [delta]="perfDelta('custodies')" [spark]="perfSpark('custodies')" [reserveSpark]="true" />
                  <app-stat-tile class="tile-link" label="dashboard.finance.total_spent" [value]="d.total_spent" [money]="true" iconName="myExpense" accent="expenses" caption="dashboard.finance.today_expenses_hint" [captionParams]="{ n: d.today_expenses | money: false }" [delta]="perfDelta('expenses')" [spark]="perfSpark('expenses')" [reserveSpark]="true" />
                  <app-stat-tile class="tile-link" label="dashboard.finance.agents_count" [value]="d.agents_evaluation.length" iconName="users" accent="hr" caption="dashboard.finance.pending_cases_hint" [captionParams]="{ n: d.pending_cases }" />
                </div>
              } @else if (d.view === 'agent' && d.agent_stats; as a) {
                <div class="kpi-grid">
                  <app-stat-tile label="dashboard.agent.remaining" [value]="a.remaining" [money]="true" iconName="custody" accent="custodies" />
                  <app-stat-tile label="dashboard.agent.received" [value]="a.received" [money]="true" iconName="transfer" accent="treasury" />
                  <app-stat-tile label="dashboard.agent.spent" [value]="a.spent" [money]="true" iconName="myExpense" accent="expenses" />
                  <app-stat-tile label="dashboard.agent.returned" [value]="a.returned" [money]="true" iconName="return" accent="reports" />
                  @if (toNum(a.frozen) > 0) {
                    <app-stat-tile label="dashboard.agent.frozen" [value]="a.frozen" [money]="true" iconName="clock" accent="tasks" />
                  }
                </div>

                <!-- T7-08: agent variant widgets — balance ring, pending
                     transfers with accept/cancel, quick add expense -->
                <div class="dash-insight-cols u-mt-3">
                  <div>
                    <h3 class="dash-rank__title">{{ 'dashboard.agent.balance_ring' | translate }}</h3>
                    <app-balance-ring [segments]="agentRing(a)" [total]="a.remaining" />
                  </div>
                  <div>
                    <div class="u-flex u-gap-2 u-flex-wrap u-center-y">
                      <h3 class="dash-rank__title u-flex-1">{{ 'dashboard.agent.pending_transfers' | translate }}</h3>
                      <app-action-button kind="quickAdd" label="dashboard.agent.quick_expense" size="small" [route]="['/expenses/new']" />
                    </div>
                    @if (mine.value(); as m) {
                      <ul class="dash-bars">
                        @for (t of pendingTransfers(m, 'received'); track t.id) {
                          <li>
                            <span class="dash-bars__label u-bidi">{{ t.amount | money: false }}</span>
                            <span class="u-small u-muted dash-bars__notes">{{ t.notes ?? '—' }}</span>
                            <app-action-button kind="accept" label="dashboard.agent.accept_transfer" size="small" [loading]="transferBusy() === t.id" (action)="askAccept(t)" />
                          </li>
                        }
                        @for (t of pendingTransfers(m, 'sent'); track t.id) {
                          <li>
                            <span class="dash-bars__label u-bidi">{{ t.amount | money: false }}</span>
                            <span class="u-small u-muted dash-bars__notes">{{ t.notes ?? '—' }}</span>
                            <app-action-button kind="cancel" label="dashboard.agent.cancel_transfer" size="small" [loading]="transferBusy() === t.id" (action)="askCancel(t)" />
                          </li>
                        } @empty {
                          @if (pendingTransfers(m, 'received').length === 0) {
                            <li><span class="u-muted u-small">{{ 'dashboard.agent.no_pending_transfers' | translate }}</span></li>
                          }
                        }
                      </ul>
                    }
                  </div>
                </div>
              } @else if (d.view === 'researcher' && d.researcher_stats; as r) {
                <div class="kpi-grid">
                  <app-stat-tile label="dashboard.researcher.total_cases" [value]="r.total_cases" iconName="case" accent="cases" />
                  <app-stat-tile label="dashboard.researcher.approved" [value]="r.approved_cases" iconName="success" accent="treasury" />
                  <app-stat-tile label="dashboard.researcher.pending" [value]="r.pending_cases" iconName="clock" accent="expenses" />
                  <app-stat-tile label="dashboard.researcher.approval_rate" [value]="r.approval_rate + '%'" iconName="percentage" accent="reports" />
                </div>

                <!-- T7-08: researcher variant widgets — my-cases funnel + gauge -->
                <div class="u-mt-3">
                  <h3 class="dash-rank__title">{{ 'dashboard.insights.funnel' | translate }}</h3>
                  <ul class="dash-bars">
                    @for (stage of researcherFunnel(r); track stage.stage) {
                      <li>
                        <span class="dash-bars__label">{{ stage.label | translate }}</span>
                        <span class="dash-rank__bar"><span class="dash-rank__fill dash-rank__fill--alt" [style.width.%]="stage.percent"></span></span>
                        <span class="dash-bars__value u-bidi">{{ stage.count }}</span>
                      </li>
                    }
                  </ul>
                  <p class="dash-gauge">
                    {{ 'dashboard.insights.approval_rate' | translate }}:
                    <strong class="u-bidi">{{ r.approval_rate }}%</strong>
                    <span class="dash-rank__bar"><span class="dash-rank__fill" [style.width.%]="r.approval_rate"></span></span>
                  </p>
                </div>
              }
            </section>
          }

          <!-- Quick actions (RD-4.1): the 2/3-width tile grid, hover primary border -->
          @if (visible('quick-actions')) {
            <section class="bento__widget bento__widget--wide">
              <div class="chart-card__head"><span class="chart-card__title">{{ 'dashboard.bento.quick_actions' | translate }}</span></div>
              <div class="dash-chips">
                @for (chip of quickActions(d.view); track chip.route) {
                  <a class="dash-chip" [routerLink]="chip.route">
                    <app-icon [name]="chip.icon" [chip]="chip.accent" [size]="18" />
                    <span>{{ chip.label | translate }}</span>
                  </a>
                }
              </div>
            </section>
          }

          <!-- Needs attention (RD-4.1 + B-08): three DISTINCT rows, each
               deep-linking to its pre-filtered list -->
          @if (visible('attention')) {
            <section class="bento__widget">
              <div class="chart-card__head"><span class="chart-card__title">{{ 'dashboard.bento.attention' | translate }}</span></div>
              <ul class="dash-attention">
                <li>
                  <a routerLink="/custodies" [queryParams]="{ status: 'pending' }">
                    {{ 'dashboard.attention.pending_custodies' | translate }}
                    <span class="dash-attention__count dash-attention__count--neutral u-bidi">{{ d.pending_custodies }}</span>
                  </a>
                </li>
                <li>
                  <a routerLink="/social-cases" [queryParams]="{ status: 'pending' }">
                    {{ 'dashboard.finance.pending_cases' | translate }}
                    <span class="dash-attention__count dash-attention__count--neutral u-bidi">{{ d.pending_cases }}</span>
                  </a>
                </li>
                <li>
                  <a routerLink="/custodies/tracking">
                    {{ 'dashboard.attention.follow_up' | translate }}
                    <span class="dash-attention__count dash-attention__count--primary u-bidi">{{ d.custodies_needing_follow_up }}</span>
                  </a>
                </li>
              </ul>
            </section>
          }

          <!-- Insights (T7-08 → P-01): aging buckets + case funnel moved out of
               the main flow into a collapsed "additional analytics" section;
               the KPI delta/spark cards merged INTO the KPI row above -->
          <!-- N-09: the collapsed details box is laid out from the first frame;
               only its (hidden) content waits for /performance. The whole box
               used to be inserted when that request landed, pushing the two
               rows under it down -->
          @if (visible('insights') && d.view === 'finance') {
            <details class="bento__widget bento__widget--full dash-extra">
              <summary class="dash-extra__summary">
                <span class="chart-card__title">{{ 'dashboard.insights.extra' | translate }}</span>
              </summary>
              @if (perf.value(); as p) {
              <div class="dash-insight-cols">
                <div>
                  <h3 class="dash-rank__title">{{ 'dashboard.insights.aging' | translate }}</h3>
                  <ul class="dash-bars">
                    @for (bucket of p.aging; track bucket.bucket) {
                      <li>
                        <span class="dash-bars__label u-bidi">{{ bucket.bucket }}</span>
                        <span class="dash-rank__bar"><span class="dash-rank__fill" [style.width.%]="(bucket.count / agingMax()) * 100"></span></span>
                        <span class="dash-bars__value u-bidi">{{ bucket.count }} · {{ bucket.total | money: false }}</span>
                      </li>
                    }
                  </ul>
                </div>
                <div>
                  <h3 class="dash-rank__title">{{ 'dashboard.insights.funnel' | translate }}</h3>
                  <ul class="dash-bars">
                    @for (stage of p.case_funnel; track stage.stage) {
                      <li>
                        <span class="dash-bars__label">{{ 'enums.social_case.' + stage.stage | translate }}</span>
                        <span class="dash-rank__bar"><span class="dash-rank__fill dash-rank__fill--alt" [style.width.%]="(stage.count / funnelMax()) * 100"></span></span>
                        <span class="dash-bars__value u-bidi">{{ stage.count }}</span>
                      </li>
                    }
                  </ul>
                  <p class="dash-gauge">
                    {{ 'dashboard.insights.approval_rate' | translate }}:
                    <strong class="u-bidi">{{ p.approval_rate }}%</strong>
                    <span class="dash-rank__bar"><span class="dash-rank__fill" [style.width.%]="p.approval_rate"></span></span>
                  </p>
                </div>
              </div>
              }
            </details>
          }

          <!-- Money flow (T6-60.4 → P-01 row 3 → R2-03): the chart card IS the
               widget — no card-in-card, one title + one toggle per chart -->
          @if (visible('money-flow')) {
            <section class="bento__widget bento__widget--wide bento__widget--bare">
              <app-chart-card [title]="'dashboard.finance.charts.expenses_by_day'" [series]="byDaySeries()" chartType="bar" [height]="220"
                              viewAllRoute="/reports" emptyLabel="dashboard.finance.charts.no_expenses_7d" accent="expenses" iconName="expense" />
            </section>
            <!-- N-09: the donut card is there from the first frame — loading,
                 then drawn in a body that already had its height. It used to
                 appear when /performance landed and make this row 200px taller -->
            <section class="bento__widget bento__widget--bare">
              <app-chart-card [title]="'dashboard.insights.spending'" [series]="spendingSeries()" chartType="doughnut" legendVariant="ranked" [height]="170" accent="expenses" iconName="expense" [loading]="perf.isLoading()" />
            </section>
          }

          <!-- Performance (T6-60.5 → P-01 row 4): agent ranking. N-07: each bar
               wears its entity's colour — an agent here is the same colour as in
               the agents-balance report (entityColor), never a rank colour -->
          @if (visible('performance') && d.view === 'finance') {
            <section class="bento__widget">
              <div class="chart-card__head">
                <span class="chart-card__title">{{ 'dashboard.bento.performance' | translate }}</span>
              </div>
              <h3 class="dash-rank__title">{{ 'dashboard.finance.agents_eval' | translate }}</h3>
              <ol class="dash-rank">
                @for (agent of topAgents(); track agent.agent.id) {
                  <li>
                    <span class="dash-rank__name">{{ agent.agent.name }}</span>
                    <span class="dash-rank__bar">
                      <span class="dash-rank__fill" [style.width.%]="ratingPercent(agent.rating)" [style.background]="entityColor(agent.agent.id)"></span>
                    </span>
                    <span class="dash-rank__score u-bidi">★ {{ agent.rating | number: '1.1-1' }}</span>
                  </li>
                } @empty {
                  <li><span class="u-muted">{{ 'common.no_data' | translate }}</span></li>
                }
              </ol>
              <h3 class="dash-rank__title">{{ 'dashboard.finance.researchers_eval' | translate }}</h3>
              <ol class="dash-rank">
                @for (researcher of topResearchers(); track researcher.researcher.id) {
                  <li>
                    <span class="dash-rank__name">{{ researcher.researcher.name }}</span>
                    <span class="dash-rank__bar">
                      <span class="dash-rank__fill" [style.width.%]="researcher.approval_rate" [style.background]="entityColor(researcher.researcher.id)"></span>
                    </span>
                    <span class="dash-rank__score u-bidi">{{ researcher.approval_rate }}%</span>
                  </li>
                } @empty {
                  <li><span class="u-muted">{{ 'common.no_data' | translate }}</span></li>
                }
              </ol>
            </section>
          }

          <!-- Activity (T6-60.6): recent cases / links by role -->
          @if (visible('activity')) {
            <section class="bento__widget">
              <div class="chart-card__head"><span class="chart-card__title">{{ 'dashboard.bento.activity' | translate }}</span></div>
              @if (d.view === 'researcher' && d.researcher_stats; as r) {
                <h3 class="dash-rank__title">{{ 'dashboard.researcher.recent_cases' | translate }}</h3>
                <ul class="dash-activity">
                  @for (c of r.recent_cases.slice(0, 6); track c.id) {
                    <li>
                      <a [routerLink]="['/social-cases', c.id]">{{ c.name }}</a>
                      <app-status-stamp [status]="c.status" namespace="social_case" />
                      <span class="u-small u-muted u-bidi">{{ c.created_at | datetime: false }}</span>
                    </li>
                  }
                </ul>
              } @else if (activityFeed.value(); as feed) {
                <!-- T7-08: the real activity feed from the lazy endpoint -->
                <ul class="dash-activity">
                  @for (item of feed.slice(0, 8); track item.id) {
                    <li>
                      <span class="dash-activity__text">{{ item.description }}</span>
                      <span class="u-small u-muted u-bidi">{{ item.created_at | datetime: false }}</span>
                    </li>
                  } @empty {
                    <li><span class="u-muted">{{ 'common.no_data' | translate }}</span></li>
                  }
                </ul>
              } @else {
                <ul class="dash-activity">
                  <li><a routerLink="/expenses">{{ 'dashboard.finance.today_expenses' | translate }}: <span class="u-bidi">{{ d.today_expenses | money }}</span></a></li>
                  <li><a routerLink="/activity">{{ 'nav.activity' | translate }}</a></li>
                  <li><a routerLink="/tasks">{{ 'nav.tasks' | translate }}</a></li>
                </ul>
              }
            </section>
          }
        </div>
        }
      }
    </app-async-page>

          <!-- T7-08: agent transfer decisions — the same endpoints custodies/mine uses.
               N-06: each confirm shows the money it moves -->
      <app-confirm-dialog #acceptDlg header="dashboard.agent.accept_transfer" message="dashboard.agent.accept_confirm" confirmLabel="dashboard.agent.accept_transfer" confirmKind="accept" [details]="transferDetails()" (confirmed)="submitAccept()" />
      <app-confirm-dialog #cancelDlg header="dashboard.agent.cancel_transfer" message="dashboard.agent.cancel_confirm" confirmLabel="dashboard.agent.cancel_transfer" confirmKind="cancel" [details]="transferDetails()" (confirmed)="submitCancel()" />
  `,
})
export class DashboardPage {
  private http = inject(HttpClient);
  private api = inject(ApiClient);
  private toast = inject(ToastService);
  private moneyPipe = new MoneyPipe();
  private auth = inject(AuthService);
  private storage = inject(LocalStorageService);
  private translate = inject(TranslateService);

  /** the customize popover (P-01: opened from the "⋯" header menu) */
  private readonly customizePop = viewChild.required<Popover>('customizePop');

  /** RD-4.1: the header CTA only for users who may create custodies */
  canCreateCustody = computed(() => this.auth.can('may_create_custody'));

  readonly today = new Date();
  readonly dateFormat = this.translate.instant('dashboard.date_format');
  readonly rangeOptions = [
    { label: this.translate.instant('dashboard.range.today'), value: 'today' },
    { label: this.translate.instant('dashboard.range.7d'), value: '7d' },
    { label: this.translate.instant('dashboard.range.30d'), value: '30d' },
    { label: this.translate.instant('dashboard.range.month'), value: 'month' },
    { label: this.translate.instant('dashboard.range.year'), value: 'year' },
  ];

  /** P-01: refresh + customize live in the quiet "⋯" menu — the header keeps
   *  ONE primary action (create custody) + the range chip */
  readonly headerMenuItems = computed<MenuItem[]>(() => [
    {
      label: this.translate.instant('dashboard.bento.customize'),
      command: (event) => {
        this.customizePop().show(event.originalEvent);
      },
    },
    {
      label: this.translate.instant('dashboard.bento.refresh_now'),
      command: () => {
        this.data.reload();
        this.perf.reload();
      },
    },
  ]);

  range = signal<string>('30d');
  /** this device's copy of the user's widget order — null on a first visit (or a damaged copy) */
  private readonly storedOrder: string[] | null = this.readStoredOrder();

  private readStoredOrder(): string[] | null {
    const stored: unknown = this.storage.get<unknown>('dashboard.widgets', null);
    return Array.isArray(stored) && stored.every((id) => typeof id === 'string') ? stored : null;
  }
  /** widget ids in the user's saved order; missing = hidden (T6-60: GET/POST dashboard/layout) */
  widgetOrder = signal<string[]>(this.storedOrder ?? DEFAULT_WIDGETS.map((w) => w.id));
  /**
   * N-09: true once the widgets can be laid out without being rearranged
   * afterwards — at once when this device already holds the user's order,
   * otherwise once the server has answered (or failed). An order that arrived
   * after the first frame used to move every widget on the page.
   */
  readonly layoutSettled = signal(this.storedOrder !== null);

  constructor() {
    // T6-60: the layout lives on the server per user; localStorage is the
    // offline seed and the cache
    void firstValueFrom(this.http.get<{ data: { widgets: string[] | null } }>('/dashboard/layout'))
      .then((res) => {
        const server = res.data?.widgets;
        if (Array.isArray(server) && server.length > 0) {
          this.storage.set('dashboard.widgets', server);
          // a different order saved on another device is kept for the NEXT
          // visit: applying it under the user would rearrange the page
          if (this.storedOrder === null || sameWidgetOrder(this.storedOrder, server)) {
            this.widgetOrder.set(server);
          }
        } else {
          // seed the server with the local/default order
          void firstValueFrom(this.http.post('/dashboard/layout', { widgets: this.widgetOrder() })).catch(() => undefined);
        }
      })
      .catch(() => undefined)
      .finally(() => {
        this.layoutSettled.set(true);
      });
  }

  userName = computed(() => this.auth.me()?.name ?? '');

  data = resource<DashboardData, { range: string }>({
    params: () => ({ range: this.range() }),
    loader: ({ params }) =>
      firstValueFrom(
        this.http.get<ItemResponse<DashboardData>>('/dashboard', { params: { range: params.range } }),
      ).then((res) => res.data),
  });

  /** T7-08: the heavy widgets ride their own lazy endpoint, not the main payload */
  perf = resource<PerformanceData, { range: string }>({
    params: () => ({ range: this.range() }),
    loader: ({ params }) =>
      firstValueFrom(
        this.http.get<ItemResponse<PerformanceData>>('/dashboard/performance', { params: { range: params.range } }),
      ).then((res) => res.data),
  });

  /** T7-08: lazy activity feed (own events for non-managers) */
  activityFeed = resource<ActivityFeedItem[], unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<ActivityFeedItem[]>>('/dashboard/activity')).then((r) => r.data),
  });

  /** T7-08: the agent view's pending transfers — loaded only for agents */
  mine = resource<MineTransfers | null, { want: boolean }>({
    params: () => ({ want: this.data.value()?.view === 'agent' }),
    loader: ({ params }) =>
      params.want
        ? firstValueFrom(this.http.get<ItemResponse<MineTransfers>>('/custodies/mine')).then((r) => r.data)
        : Promise.resolve(null),
  });

  /** one in-flight transfer action at a time (accept/cancel) */
  readonly transferBusy = signal<number | null>(null);
  private readonly transferTarget = signal<MineTransfer | null>(null);
  private readonly acceptDlg = viewChild.required<ConfirmDialogComponent>('acceptDlg');
  private readonly cancelDlg = viewChild.required<ConfirmDialogComponent>('cancelDlg');

  /** the agent's balance ring: remaining vs spent vs returned (T7-08) */
  agentRing(a: AgentStats): { label: string; amount: string }[] {
    return [
      { label: 'dashboard.agent.remaining', amount: a.remaining },
      { label: 'dashboard.agent.spent', amount: a.spent },
      { label: 'dashboard.agent.returned', amount: a.returned },
    ];
  }

  pendingTransfers(m: MineTransfers | null, side: 'received' | 'sent'): MineTransfer[] {
    return ((m?.[side === 'received' ? 'received_transfers' : 'sent_transfers'] ?? [])).filter((t) => t.status === 'pending');
  }

  /** the researcher's own funnel (T7-08): pending → approved/rejected out of the total */
  researcherFunnel(r: ResearcherStats): { stage: string; label: string; count: number; percent: number }[] {
    const max = Math.max(1, r.total_cases);
    const stages: { stage: string; label: string; count: number }[] = [
      { stage: 'total', label: 'dashboard.researcher.total_cases', count: r.total_cases },
      { stage: 'pending', label: 'dashboard.researcher.pending', count: r.pending_cases },
      { stage: 'approved', label: 'dashboard.researcher.approved', count: r.approved_cases },
      { stage: 'rejected', label: 'case.list.rejected', count: r.rejected_cases },
    ];
    return stages.map((s) => ({ ...s, percent: (s.count / max) * 100 }));
  }

  askAccept(t: MineTransfer): void {
    this.transferTarget.set(t);
    this.acceptDlg().open();
  }

  askCancel(t: MineTransfer): void {
    this.transferTarget.set(t);
    this.cancelDlg().open();
  }

  /** POST /custody-transfers/{id}/approve — the same call custodies/mine makes */
  submitAccept(): void {
    if (this.transferBusy() !== null) {
      return;
    }
    const t = this.transferTarget();
    if (t === null) {
      return;
    }
    this.transferBusy.set(t.id);
    void firstValueFrom(this.api.action(`/custody-transfers/${t.id}/approve`))
      .then((res) => {
        this.transferBusy.set(null);
        this.toast.success(res.message);
        reloadList(this.mine);
      })
      .catch((err: unknown) => { this.failRequest(err); });
  }

  /** POST /custody-transfers/{id}/cancel — the sender's cancel (T6-80 flow), with its restore in the toast (N-06) */
  submitCancel(): void {
    if (this.transferBusy() !== null) {
      return;
    }
    const t = this.transferTarget();
    if (t === null) {
      return;
    }
    this.transferBusy.set(t.id);
    void firstValueFrom(this.api.action(`/custody-transfers/${t.id}/cancel`))
      .then((res) => {
        this.transferBusy.set(null);
        this.toast.success(res.message, { undo: () => { this.restoreTransfer(t.id); } });
        reloadList(this.mine);
      })
      .catch((err: unknown) => {
        this.transferBusy.set(null);
        this.failRequest(err);
      });
  }

  /** N-06: the inverse of a cancel — back to pending, the amount frozen again */
  private restoreTransfer(id: number): void {
    void firstValueFrom(this.api.action(`/custody-transfers/${id}/restore`))
      .then((res) => {
        this.toast.info(res.message);
        reloadList(this.mine);
      })
      .catch((err: unknown) => {
        this.failRequest(err);
        reloadList(this.mine);
      });
  }

  /** the server's own message, or the generic one */
  private failRequest(err: unknown): void {
    const failure = apiFailure(err);
    this.toast.apiError(failure.message, failure.code);
  }

  /** N-06: what a transfer confirm is about — the amount, then the parties */
  readonly transferDetails = computed<readonly KeyValueItem[]>(() => {
    const t = this.transferTarget();
    if (t === null) {
      return [];
    }
    return [
      { label: 'common.amount_egp', value: this.moneyPipe.transform(t.amount, false) },
      ...(t.sender === undefined ? [] : [{ label: 'glossary.sender', value: t.sender ?? '—' }]),
      ...(t.receiver === undefined ? [] : [{ label: 'glossary.receiver', value: t.receiver ?? '—' }]),
    ];
  });

  /** the biggest funnel stage scales the bars */
  funnelMax(): number {
    return Math.max(1, ...(this.perf.value()?.case_funnel ?? []).map((s) => s.count));
  }

  /** D-05: the KPI tile's delta vs the previous period (null hides the chip) */
  perfDelta(key: 'expenses' | 'income' | 'custodies' | 'cases'): number | null {
    const kpi = this.perf.value()?.kpis[key];
    return kpi ? kpi.delta : null;
  }

  /** D-05: the KPI tile's sparkline values (oldest first) */
  perfSpark(key: 'expenses' | 'income' | 'custodies' | 'cases'): number[] {
    const kpi = this.perf.value()?.kpis[key];
    return (kpi?.spark ?? []).map((p) => Number.parseFloat(p.v) || 0);
  }

  agingMax(): number {
    return Math.max(1, ...(this.perf.value()?.aging ?? []).map((b) => b.count));
  }

  spendingSeries = computed<ChartSeries[]>(() =>
    (this.perf.value()?.spending_by_direction ?? []).map((row) => ({ label: row.label, value: this.toNum(row.value), id: row.id })),
  );

  /** N-07: an agent's / a researcher's colour on the ranking bars — the same one everywhere else */
  entityColor = entityColor;

  readonly allWidgets = signal<WidgetDef[]>(DEFAULT_WIDGETS);

  isWidgetVisible(id: string): boolean {
    return this.widgetOrder().includes(id);
  }

  visible(id: string): boolean {
    return this.isWidgetVisible(id);
  }

  toggleWidget(id: string): void {
    this.widgetOrder.update((order) =>
      order.includes(id)
        ? order.filter((w) => w !== id)
        : [...DEFAULT_WIDGETS.map((w) => w.id).filter((w) => order.includes(w)), id],
    );
    this.storage.set('dashboard.widgets', this.widgetOrder());
    // T6-60: persist per user server-side too
    void firstValueFrom(this.http.post('/dashboard/layout', { widgets: this.widgetOrder() })).catch(() => undefined);
  }

  setRange(range: string): void {
    this.range.set(range);
    this.data.reload();
    this.perf.reload();
  }

  toNum(v: string | number): number {
    const n = typeof v === 'string' ? Number.parseFloat(v) : v;
    return Number.isFinite(n) ? n : 0;
  }

  ratingPercent(rating: number): number {
    return Math.max(0, Math.min(100, Math.round((rating / 5) * 100)));
  }

  byDaySeries = computed<ChartSeries[]>(() =>
    (this.data.value()?.direction_figures?.by_day ?? []).map((day) => ({ label: day.label, value: day.amount })),
  );

  topAgents = computed(() =>
    [...(this.data.value()?.agents_evaluation ?? [])].sort((a, b) => b.rating - a.rating).slice(0, 6),
  );

  topResearchers = computed(() =>
    [...(this.data.value()?.researchers_evaluation ?? [])].sort((a, b) => b.approval_rate - a.approval_rate).slice(0, 6),
  );

  /** the compact quick-action chips per role (T6-60.2) */
  quickActions(view: string): { route: string; icon: string; accent: 'custodies' | 'expenses' | 'treasury' | 'cases' | 'hr' | 'tasks'; label: string }[] {
    if (view === 'agent') {
      return [
        { route: '/expenses/new', icon: 'receipt', accent: 'expenses', label: 'dashboard.agent.quick_expense' },
        { route: '/custodies/mine', icon: 'wallet', accent: 'custodies', label: 'nav.my_custodies' },
        { route: '/transfers/new', icon: 'arrows-exchange', accent: 'treasury', label: 'custody.list.transfer_custody' },
        { route: '/tasks', icon: 'list-check', accent: 'tasks', label: 'nav.tasks' },
      ];
    }
    if (view === 'researcher') {
      return [
        { route: '/social-cases/new', icon: 'heart-handshake', accent: 'cases', label: 'dashboard.researcher.new_case' },
        { route: '/social-cases/mine', icon: 'user', accent: 'cases', label: 'nav.my_cases' },
        { route: '/tasks', icon: 'list-check', accent: 'tasks', label: 'nav.tasks' },
      ];
    }
    return [
      { route: '/custodies/new', icon: 'wallet', accent: 'custodies', label: 'custody.list.create' },
      { route: '/expenses/new', icon: 'receipt', accent: 'expenses', label: 'expense.list.title' },
      { route: '/treasury/transfer', icon: 'arrows-exchange', accent: 'treasury', label: 'dashboard.quick.transfer' },
      { route: '/social-cases', icon: 'heart-handshake', accent: 'cases', label: 'nav.cases' },
      { route: '/hr', icon: 'briefcase', accent: 'hr', label: 'nav.hr' },
      { route: '/reports', icon: 'report-analytics', accent: 'cases', label: 'nav.reports' },
    ];
  }
}
