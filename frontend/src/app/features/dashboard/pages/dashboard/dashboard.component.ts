import { ChangeDetectionStrategy, Component, computed, inject, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { DatePipe, DecimalPipe } from '@angular/common';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { ButtonModule } from 'primeng/button';
import { SelectModule } from 'primeng/select';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { AuthService } from '@core/auth/auth.service';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { ChartCardComponent } from '@shared/ui/chart-card/chart-card.component';
import type { ChartSeries } from '@shared/ui/chart-card/chart-card.component';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { IconComponent } from '@shared/ui/icon/icon.component';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { CountUpDirective } from '@core/motion/motion-directives';
import { AutoRefreshComponent } from '@shared/ui/auto-refresh/auto-refresh.component';
import { LocalStorageService } from '@core/storage/local-storage.service';
import type { ItemResponse } from '@shared/models/api.models';

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

const DEFAULT_WIDGETS: WidgetDef[] = [
  { id: 'kpi', titleKey: 'dashboard.bento.kpi' },
  { id: 'quick-actions', titleKey: 'dashboard.bento.quick_actions' },
  { id: 'attention', titleKey: 'dashboard.bento.attention' },
  { id: 'money-flow', titleKey: 'dashboard.bento.money_flow' },
  { id: 'performance', titleKey: 'dashboard.bento.performance' },
  { id: 'activity', titleKey: 'dashboard.bento.activity' },
];

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
    ButtonModule,
    SelectModule,
    FormsModule,
    AsyncPageComponent,
    ChartCardComponent,
    StatusStampComponent,
    IconComponent,
    DatetimePipe,
    MoneyPipe,
    CountUpDirective,
    AutoRefreshComponent,
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
        <app-auto-refresh key="dashboard" (refresh)="data.reload()" />
        <p-button [label]="'dashboard.bento.customize' | translate" size="small" severity="secondary" [outlined]="true" (onClick)="customizeOpen.set(!customizeOpen())" />
      </div>
    </div>

    @if (customizeOpen()) {
      <section class="chart-card dash-customize">
        <div class="chart-card__head"><span class="chart-card__title">{{ 'dashboard.bento.customize' | translate }}</span></div>
        <div class="u-flex u-gap-2 u-flex-wrap">
          @for (w of allWidgets(); track w.id) {
            <label class="dash-customize__item">
              <input type="checkbox" [checked]="isWidgetVisible(w.id)" (change)="toggleWidget(w.id)" />
              {{ w.titleKey | translate }}
            </label>
          }
        </div>
      </section>
    }

    <app-async-page [resource]="data" variant="list">
      @if (data.value(); as d) {
        <div class="bento">
          <!-- KPI strip (T6-60.1): animated count-up, every number links to its page -->
          @if (visible('kpi')) {
            <section class="bento__widget bento__widget--wide" [attr.aria-label]="'dashboard.bento.kpi' | translate">
              @if (d.view === 'finance') {
                <div class="dash-kpis">
                  <a class="dash-kpi" routerLink="/treasury">
                    <span class="dash-kpi__label">{{ 'dashboard.finance.treasury_balance' | translate }}</span>
                    <span class="dash-kpi__value u-bidi" [appCountUp]="toNum(d.treasury_balance)">{{ d.treasury_balance | money: false }}</span>
                  </a>
                  <a class="dash-kpi" routerLink="/custodies">
                    <span class="dash-kpi__label">{{ 'dashboard.finance.total_custodies' | translate }}</span>
                    <span class="dash-kpi__value u-bidi" [appCountUp]="toNum(d.total_custodies_amount)">{{ d.total_custodies_amount | money: false }}</span>
                  </a>
                  <a class="dash-kpi" routerLink="/expenses">
                    <span class="dash-kpi__label">{{ 'dashboard.finance.total_spent' | translate }}</span>
                    <span class="dash-kpi__value u-bidi" [appCountUp]="toNum(d.total_spent)">{{ d.total_spent | money: false }}</span>
                  </a>
                  <a class="dash-kpi" routerLink="/expenses">
                    <span class="dash-kpi__label">{{ 'dashboard.finance.today_expenses' | translate }}</span>
                    <span class="dash-kpi__value u-bidi" [appCountUp]="toNum(d.today_expenses)">{{ d.today_expenses | money: false }}</span>
                  </a>
                  <a class="dash-kpi" routerLink="/custodies">
                    <span class="dash-kpi__label">{{ 'dashboard.finance.active_custodies' | translate }}</span>
                    <span class="dash-kpi__value u-bidi" [appCountUp]="d.active_custodies">{{ d.active_custodies }}</span>
                  </a>
                  <a class="dash-kpi" routerLink="/cases">
                    <span class="dash-kpi__label">{{ 'dashboard.finance.pending_cases' | translate }}</span>
                    <span class="dash-kpi__value u-bidi" [appCountUp]="d.pending_cases">{{ d.pending_cases }}</span>
                  </a>
                  <a class="dash-kpi" routerLink="/users">
                    <span class="dash-kpi__label">{{ 'dashboard.finance.agents_count' | translate }}</span>
                    <span class="dash-kpi__value u-bidi" [appCountUp]="d.agents_evaluation.length">{{ d.agents_evaluation.length }}</span>
                  </a>
                </div>
              } @else if (d.view === 'agent' && d.agent_stats; as a) {
                <div class="dash-kpis">
                  <div class="dash-kpi">
                    <span class="dash-kpi__label">{{ 'dashboard.agent.remaining' | translate }}</span>
                    <span class="dash-kpi__value u-bidi" [appCountUp]="toNum(a.remaining)">{{ a.remaining | money: false }}</span>
                  </div>
                  <div class="dash-kpi">
                    <span class="dash-kpi__label">{{ 'dashboard.agent.received' | translate }}</span>
                    <span class="dash-kpi__value u-bidi" [appCountUp]="toNum(a.received)">{{ a.received | money: false }}</span>
                  </div>
                  <div class="dash-kpi">
                    <span class="dash-kpi__label">{{ 'dashboard.agent.spent' | translate }}</span>
                    <span class="dash-kpi__value u-bidi" [appCountUp]="toNum(a.spent)">{{ a.spent | money: false }}</span>
                  </div>
                  <div class="dash-kpi">
                    <span class="dash-kpi__label">{{ 'dashboard.agent.returned' | translate }}</span>
                    <span class="dash-kpi__value u-bidi" [appCountUp]="toNum(a.returned)">{{ a.returned | money: false }}</span>
                  </div>
                  @if (toNum(a.frozen) > 0) {
                    <div class="dash-kpi">
                      <span class="dash-kpi__label">{{ 'dashboard.agent.frozen' | translate }}</span>
                      <span class="dash-kpi__value u-bidi" [appCountUp]="toNum(a.frozen)">{{ a.frozen | money: false }}</span>
                    </div>
                  }
                </div>
              } @else if (d.view === 'researcher' && d.researcher_stats; as r) {
                <div class="dash-kpis">
                  <div class="dash-kpi"><span class="dash-kpi__label">{{ 'dashboard.researcher.total_cases' | translate }}</span><span class="dash-kpi__value u-bidi" [appCountUp]="r.total_cases">{{ r.total_cases }}</span></div>
                  <div class="dash-kpi"><span class="dash-kpi__label">{{ 'dashboard.researcher.approved' | translate }}</span><span class="dash-kpi__value u-bidi" [appCountUp]="r.approved_cases">{{ r.approved_cases }}</span></div>
                  <div class="dash-kpi"><span class="dash-kpi__label">{{ 'dashboard.researcher.pending' | translate }}</span><span class="dash-kpi__value u-bidi" [appCountUp]="r.pending_cases">{{ r.pending_cases }}</span></div>
                  <div class="dash-kpi"><span class="dash-kpi__label">{{ 'dashboard.researcher.approval_rate' | translate }}</span><span class="dash-kpi__value u-bidi">{{ r.approval_rate }}%</span></div>
                </div>
              }
            </section>
          }

          <!-- Quick actions (T6-60.2): compact icon chips per role — no big cards -->
          @if (visible('quick-actions')) {
            <section class="bento__widget">
              <div class="chart-card__head"><span class="chart-card__title">{{ 'dashboard.bento.quick_actions' | translate }}</span></div>
              <div class="dash-chips">
                @for (chip of quickActions(d.view); track chip.route) {
                  <a class="dash-chip" [routerLink]="chip.route">
                    <app-icon [name]="chip.icon" [size]="16" [chip]="chip.accent" />
                    <span>{{ chip.label | translate }}</span>
                  </a>
                }
              </div>
            </section>
          }

          <!-- Needs attention (T6-60.3): counters linking into the filtered pages -->
          @if (visible('attention')) {
            <section class="bento__widget">
              <div class="chart-card__head"><span class="chart-card__title">{{ 'dashboard.bento.attention' | translate }}</span></div>
              <ul class="dash-attention">
                <li>
                  <a routerLink="/custodies">{{ 'dashboard.finance.pending_custodies' | translate }}</a>
                  <a routerLink="/cases">{{ 'dashboard.finance.pending_cases' | translate }} — <span class="u-bidi">{{ d.pending_cases }}</span></a>
                  <a routerLink="/custodies/tracking">{{ 'nav.custody_tracking' | translate }}</a>
                </li>
              </ul>
            </section>
          }

          <!-- Money flow (T6-60.4): bar chart + HTML legend list, never canvas legends -->
          @if (visible('money-flow')) {
            <section class="bento__widget bento__widget--wide">
              <div class="chart-card__head">
                <span class="chart-card__title">{{ 'dashboard.bento.money_flow' | translate }}</span>
                <a routerLink="/reports" class="dash-viewall">{{ 'dashboard.bento.view_all' | translate }}</a>
              </div>
              <app-chart-card [title]="'dashboard.finance.charts.expenses_by_day' | translate" [series]="byDaySeries()" chartType="bar" [height]="200" />
              @if (directionTop().length > 0) {
                <ul class="dash-legend">
                  @for (item of directionTop(); track item.label) {
                    <li>
                      <span class="dash-legend__chip" [style.background]="item.color"></span>
                      <span class="dash-legend__label">{{ item.label }}</span>
                      <span class="dash-legend__value u-bidi">{{ item.value | money: false }}</span>
                    </li>
                  }
                </ul>
              }
            </section>
          }

          <!-- Performance (T6-60.5): ranked lists with progress bars + star rating -->
          @if (visible('performance') && d.view === 'finance') {
            <section class="bento__widget bento__widget--wide">
              <div class="chart-card__head"><span class="chart-card__title">{{ 'dashboard.bento.performance' | translate }}</span></div>
              <h3 class="dash-rank__title">{{ 'dashboard.finance.agents_eval' | translate }}</h3>
              <ol class="dash-rank">
                @for (agent of topAgents(); track agent.agent.id) {
                  <li>
                    <span class="dash-rank__name">{{ agent.agent.name }}</span>
                    <span class="dash-rank__bar">
                      <span class="dash-rank__fill" [style.width.%]="ratingPercent(agent.rating)"></span>
                    </span>
                    <span class="dash-rank__score u-bidi">★ {{ agent.rating | number: '1.1-1' }}</span>
                  </li>
                }
              </ol>
              <h3 class="dash-rank__title">{{ 'dashboard.finance.researchers_eval' | translate }}</h3>
              <ol class="dash-rank">
                @for (researcher of topResearchers(); track researcher.researcher.id) {
                  <li>
                    <span class="dash-rank__name">{{ researcher.researcher.name }}</span>
                    <span class="dash-rank__bar">
                      <span class="dash-rank__fill dash-rank__fill--alt" [style.width.%]="researcher.approval_rate"></span>
                    </span>
                    <span class="dash-rank__score u-bidi">{{ researcher.approval_rate }}%</span>
                  </li>
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
                      <a [routerLink]="['/cases', c.id]">{{ c.name }}</a>
                      <app-status-stamp [status]="c.status" namespace="social_case" />
                      <span class="u-small u-muted u-bidi">{{ c.created_at | datetime: false }}</span>
                    </li>
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
    </app-async-page>
  `,
})
export class DashboardPage {
  private http = inject(HttpClient);
  private auth = inject(AuthService);
  private storage = inject(LocalStorageService);
  private translate = inject(TranslateService);

  readonly today = new Date();
  readonly dateFormat = this.translate.instant('dashboard.date_format');
  readonly rangeOptions = [
    { label: this.translate.instant('dashboard.range.today'), value: 'today' },
    { label: this.translate.instant('dashboard.range.7d'), value: '7d' },
    { label: this.translate.instant('dashboard.range.30d'), value: '30d' },
    { label: this.translate.instant('dashboard.range.month'), value: 'month' },
    { label: this.translate.instant('dashboard.range.year'), value: 'year' },
  ];

  range = signal<string>('30d');
  customizeOpen = signal(false);
  /** widget ids in the user's saved order; missing = hidden (T6-60: saved per user) */
  widgetOrder = signal<string[]>(this.storage.get('dashboard.widgets', DEFAULT_WIDGETS.map((w) => w.id)));

  userName = computed(() => this.auth.me()?.name ?? '');

  data = resource<DashboardData, { range: string }>({
    params: () => ({ range: this.range() }),
    loader: ({ params }) =>
      firstValueFrom(
        this.http.get<ItemResponse<DashboardData>>('/dashboard', { params: { range: params.range } }),
      ).then((res) => res.data),
  });

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
  }

  setRange(range: string): void {
    this.range.set(range);
    this.data.reload();
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

  /** color-blind-safe legend palette (T6-40) — HTML chips, not canvas dots */
  readonly legendPalette = ['#0F766E', '#B8860B', '#7A4F9E', '#2F6CB3', '#B3555E', '#4F8F3A'];

  topAgents = computed(() =>
    [...(this.data.value()?.agents_evaluation ?? [])].sort((a, b) => b.rating - a.rating).slice(0, 6),
  );

  topResearchers = computed(() =>
    [...(this.data.value()?.researchers_evaluation ?? [])].sort((a, b) => b.approval_rate - a.approval_rate).slice(0, 6),
  );

  directionTop(): { label: string; value: number; color: string }[] {
    return this.byDaySeries()
      .slice(0, 6)
      .map((s, i) => ({ label: s.label, value: s.value, color: this.legendPalette[i % this.legendPalette.length] }));
  }

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
        { route: '/cases/new', icon: 'heart-handshake', accent: 'cases', label: 'dashboard.researcher.new_case' },
        { route: '/cases/mine', icon: 'user', accent: 'cases', label: 'nav.my_cases' },
        { route: '/tasks', icon: 'list-check', accent: 'tasks', label: 'nav.tasks' },
      ];
    }
    return [
      { route: '/custodies/new', icon: 'wallet', accent: 'custodies', label: 'custody.list.create' },
      { route: '/expenses/new', icon: 'receipt', accent: 'expenses', label: 'expense.list.title' },
      { route: '/treasury/transfer', icon: 'arrows-exchange', accent: 'treasury', label: 'treasury.transfer.title' },
      { route: '/cases', icon: 'heart-handshake', accent: 'cases', label: 'nav.cases' },
      { route: '/hr', icon: 'briefcase', accent: 'hr', label: 'nav.hr' },
      { route: '/reports', icon: 'report-analytics', accent: 'cases', label: 'nav.reports' },
    ];
  }
}
