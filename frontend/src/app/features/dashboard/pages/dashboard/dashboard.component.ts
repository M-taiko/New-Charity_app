import { ChangeDetectionStrategy, Component, computed, inject, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';
import { ButtonModule } from 'primeng/button';
import { SelectModule } from 'primeng/select';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { AuthService } from '@core/auth/auth.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { ChartCardComponent } from '@shared/ui/chart-card/chart-card.component';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { IconComponent } from '@shared/ui/icon/icon.component';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
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

/**
 * Dashboard with the role variants (plan 20): finance (manager, accountant,
 * supervisor), agent, researcher. Every stat tile and chart links to the
 * page its data comes from.
 */
@Component({
  selector: 'app-dashboard-page',
  standalone: true,
  imports: [
    TranslatePipe,
    RouterLink,
    ButtonModule,
    SelectModule,
    FormsModule,
    PageHeaderComponent,
    StatTileComponent,
    ChartCardComponent,
    StatusStampComponent,
    IconComponent,
    DatetimePipe,
  ],
  template: `
    <app-page-header [title]="'dashboard.welcome' | translate: { name: userName() }" />

    @if (data.isLoading() && !data.value()) {
      <div class="kpi-grid">
        @for (s of [1, 2, 3, 4]; track s) {
          <div class="skeleton skeleton--tile"></div>
        }
      </div>
    } @else if (data.value(); as d) {
      @if (d.view === 'finance') {
        <div class="kpi-grid">
          <a class="tile-link" routerLink="/treasury" [title]="'common.open_page' | translate">
            <app-stat-tile
              [label]="'dashboard.finance.total_treasury_and_custodies'"
              [value]="d.total_treasury_and_custodies"
              [money]="true"
            />
          </a>
          <a class="tile-link" routerLink="/treasury" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'dashboard.finance.treasury_balance'" [value]="d.treasury_balance" [money]="true" />
          </a>
          <a class="tile-link" routerLink="/custodies" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'dashboard.finance.total_custodies'" [value]="d.total_custodies_amount" [money]="true" />
          </a>
        </div>
        <div class="kpi-grid">
          <a class="tile-link" routerLink="/expenses" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'dashboard.finance.total_spent'" [value]="d.total_spent" [money]="true" />
          </a>
          <a class="tile-link" routerLink="/expenses" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'dashboard.finance.today_expenses'" [value]="d.today_expenses" [money]="true" />
          </a>
          <a class="tile-link" routerLink="/custodies" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'dashboard.finance.active_custodies'" [value]="d.active_custodies" />
          </a>
          <a class="tile-link" routerLink="/users" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'dashboard.finance.agents_count'" [value]="d.agents_evaluation.length" />
          </a>
        </div>

        <div class="kpi-grid">
          <a class="tile-link" routerLink="/expenses" [title]="'common.open_page' | translate">
            <app-chart-card [title]="'dashboard.finance.charts.expenses_by_day'" [series]="byDaySeries()" chartType="bar" />
          </a>
          <a class="tile-link" routerLink="/users" [title]="'common.open_page' | translate">
            <app-chart-card [title]="'dashboard.finance.agents_eval'" [series]="agentsSeries()" />
          </a>
          <a class="tile-link" routerLink="/cases" [title]="'common.open_page' | translate">
            <app-chart-card [title]="'dashboard.finance.researchers_eval'" [series]="researchersSeries()" />
          </a>
        </div>

        <section class="chart-card u-mt-4">
          <div class="chart-card__head">
            <span class="chart-card__title">{{ 'dashboard.finance.year' | translate }}</span>
            <p-select appendTo="body"
              [options]="d.year_stats.available_years"
              [(ngModel)]="selectedYear" name="selectedYear"
              (ngModelChange)="reload()"
              [ariaLabel]="'dashboard.finance.year' | translate"
            />
          </div>
          <div class="kpi-grid u-mt-2">
            <a class="tile-link" routerLink="/custodies" [title]="'common.open_page' | translate">
              <app-stat-tile [label]="'dashboard.finance.total_custodies'" [value]="d.year_stats.total_custodies_amount" [money]="true" [inset]="true" />
            </a>
            <a class="tile-link" routerLink="/expenses" [title]="'common.open_page' | translate">
              <app-stat-tile [label]="'dashboard.finance.total_spent'" [value]="d.year_stats.total_expenses" [money]="true" [inset]="true" />
            </a>
            <a class="tile-link" routerLink="/custodies/return-requests" [title]="'common.open_page' | translate">
              <app-stat-tile [label]="'glossary.remaining'" [value]="d.year_stats.total_returned" [money]="true" [inset]="true" />
            </a>
            <a class="tile-link" routerLink="/cases" [title]="'common.open_page' | translate">
              <app-stat-tile [label]="'dashboard.researcher.approved'" [value]="d.year_stats.approved_cases" [inset]="true" />
            </a>
          </div>
        </section>
      }

      @if (d.view === 'agent' && d.agent_stats; as s) {
        <div class="kpi-grid">
          <a class="tile-link" routerLink="/custodies/mine" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'dashboard.agent.remaining'" [value]="s.remaining" [money]="true" />
          </a>
          <a class="tile-link" routerLink="/custodies/mine" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'dashboard.agent.received'" [value]="s.received" [money]="true" />
          </a>
          <a class="tile-link" routerLink="/custodies/mine" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'dashboard.agent.spent'" [value]="s.spent" [money]="true" />
          </a>
        </div>
        <div class="kpi-grid">
          <a class="tile-link" routerLink="/custodies/mine" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'dashboard.agent.returned'" [value]="s.returned" [money]="true" />
          </a>
          <a class="tile-link" routerLink="/transfers" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'dashboard.agent.transferred_out'" [value]="s.transferred_out" [money]="true" />
          </a>
          @if (parseFloat(s.frozen) > 0) {
            <a class="tile-link" routerLink="/transfers" [title]="'common.open_page' | translate">
              <app-stat-tile [label]="'dashboard.agent.frozen'" [value]="s.frozen" [money]="true" [inset]="true" />
            </a>
          }
          <a class="tile-link" routerLink="/custodies/mine" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'dashboard.agent.pending_return'" [value]="s.pending_return" [money]="true" />
          </a>
        </div>
        <div class="u-flex u-gap-2 u-flex-wrap u-mt-4">
          <p-button [label]="'nav.my_custodies' | translate" routerLink="/custodies/mine" [outlined]="true" size="small" />
          <p-button [label]="'nav.my_expenses' | translate" routerLink="/expenses/mine" [outlined]="true" size="small" />
        </div>
      }

      @if (d.view === 'researcher' && d.researcher_stats; as s) {
        <div class="kpi-grid">
          <a class="tile-link" routerLink="/cases/mine" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'dashboard.researcher.total_cases'" [value]="s.total_cases" />
          </a>
          <a class="tile-link" routerLink="/cases/mine" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'dashboard.researcher.approved'" [value]="s.approved_cases" />
          </a>
          <a class="tile-link" routerLink="/cases/mine" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'dashboard.researcher.pending'" [value]="s.pending_cases" />
          </a>
        </div>
        <div class="kpi-grid">
          <a class="tile-link" routerLink="/cases/mine" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'dashboard.researcher.rejected'" [value]="s.rejected_cases" />
          </a>
          <a class="tile-link" routerLink="/cases/mine" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'dashboard.researcher.approval_rate'" [value]="s.approval_rate + '%'" />
          </a>
        </div>
        <section class="chart-card u-mt-4">
          <div class="chart-card__head">
            <span class="chart-card__title">{{ 'dashboard.researcher.recent_cases' | translate }}</span>
          </div>
          <ul class="timeline">
            @for (caseItem of s.recent_cases; track caseItem.id) {
              <li class="timeline__item">
                <div class="timeline__head">
                  <a [routerLink]="['/cases', caseItem.id]">{{ caseItem.name }}</a>
                  <app-status-stamp [status]="caseItem.status" namespace="social_case" />
                </div>
                <p class="timeline__body">{{ caseItem.created_at | datetime }}</p>
              </li>
            }
          </ul>
        </section>
      }

      <!-- T3-111: the per-role quick-action grid (dashboard/shortcuts/{role}.blade.php) -->
      @if (shortcuts().length > 0) {
        <section class="chart-card u-mt-4">
          <div class="chart-card__head">
            <span class="chart-card__title">{{ 'dashboard.shortcuts_title' | translate }}</span>
          </div>
          <div class="kpi-grid u-mt-2">
            @for (shortcut of shortcuts(); track shortcut.label) {
              <a class="tile-link" [routerLink]="shortcut.route" [title]="'common.open_page' | translate">
                <div class="chart-card">
                  <div class="chart-card__body">
                    <div class="u-flex u-gap-2">
                      <app-icon [name]="shortcut.icon" [size]="20" />
                      <strong>{{ shortcut.label | translate }}</strong>
                    </div>
                    <p class="u-muted u-small u-mt-2">{{ shortcut.desc | translate }}</p>
                  </div>
                </div>
              </a>
            }
          </div>
        </section>
      }
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DashboardPage {
  private http = inject(HttpClient);
  private auth = inject(AuthService);

  selectedYear = signal(new Date().getFullYear());

  userName = computed(() => this.auth.me()?.name ?? '');

  data = resource<DashboardData, { year: number }>({
    loader: () =>
      firstValueFrom(
        this.http.get<ItemResponse<DashboardData>>('/dashboard', {
          params: { year: String(this.selectedYear()) },
        }),
      ).then((res) => res.data),
  });

  byDaySeries = computed(() =>
    (this.data.value()?.direction_figures?.by_day ?? []).map((day) => ({ label: day.label, value: day.amount })),
  );

  agentsSeries = computed(() =>
    (this.data.value()?.agents_evaluation ?? [])
      .slice(0, 6)
      .map((row) => ({ label: row.agent.name, value: row.rating })),
  );

  researchersSeries = computed(() =>
    (this.data.value()?.researchers_evaluation ?? [])
      .slice(0, 6)
      .map((row) => ({ label: row.researcher.name, value: row.rating })),
  );

  /**
   * T3-111: the per-role shortcut grid. The server's `view` picks the
   * agent/researcher variants; within the finance view Blade checks the
   * accountant role first (production managers hold every role).
   */
  shortcuts = computed<Shortcut[]>(() => {
    const view = this.data.value()?.view;
    if (view === 'agent') {
      return AGENT_SHORTCUTS;
    }
    if (view === 'researcher') {
      return RESEARCHER_SHORTCUTS;
    }
    return this.auth.can('is_accountant') ? ACCOUNTANT_SHORTCUTS : MANAGER_SHORTCUTS;
  });

  parseFloat(value: string): number {
    return parseFloat(value) || 0;
  }

  reload(): void {
    this.data.reload();
  }
}

interface Shortcut {
  /** i18n key (nav.* or dashboard.shortcuts.*) */
  label: string;
  /** i18n key under dashboard.shortcuts.* */
  desc: string;
  route: string;
  /** tabler icon name from the generated sprite */
  icon: string;
}

/** dashboard/shortcuts/manager.blade.php */
const MANAGER_SHORTCUTS: Shortcut[] = [
  { label: 'nav.custodies', desc: 'dashboard.shortcuts.custodies_desc', route: '/custodies', icon: 'wallet' },
  { label: 'nav.cases', desc: 'dashboard.shortcuts.cases_desc', route: '/cases', icon: 'heart-handshake' },
  { label: 'nav.users', desc: 'dashboard.shortcuts.users_desc', route: '/users', icon: 'users-group' },
  { label: 'nav.reports', desc: 'dashboard.shortcuts.reports_desc', route: '/reports', icon: 'report-analytics' },
  { label: 'dashboard.shortcuts.new_custody', desc: 'dashboard.shortcuts.new_custody_desc', route: '/custodies/new', icon: 'plus' },
  { label: 'dashboard.shortcuts.new_case', desc: 'dashboard.shortcuts.new_case_desc', route: '/cases/new', icon: 'plus' },
];

/** dashboard/shortcuts/accountant.blade.php */
const ACCOUNTANT_SHORTCUTS: Shortcut[] = [
  { label: 'nav.treasury', desc: 'dashboard.shortcuts.treasury_desc', route: '/treasury', icon: 'building-bank' },
  { label: 'nav.expenses', desc: 'dashboard.shortcuts.expenses_desc', route: '/expenses', icon: 'receipt-2' },
  { label: 'nav.custodies', desc: 'dashboard.shortcuts.custodies_desc', route: '/custodies', icon: 'wallet' },
  { label: 'nav.reports', desc: 'dashboard.shortcuts.reports_desc', route: '/reports', icon: 'report-analytics' },
  { label: 'dashboard.shortcuts.new_custody', desc: 'dashboard.shortcuts.new_custody_desc', route: '/custodies/new', icon: 'plus' },
  { label: 'dashboard.shortcuts.new_expense', desc: 'dashboard.shortcuts.new_expense_desc', route: '/expenses/new', icon: 'plus' },
];

/** dashboard/shortcuts/agent.blade.php */
const AGENT_SHORTCUTS: Shortcut[] = [
  { label: 'nav.my_custodies', desc: 'dashboard.shortcuts.my_custodies_desc', route: '/custodies/mine', icon: 'wallet' },
  { label: 'nav.my_expenses', desc: 'dashboard.shortcuts.my_expenses_desc', route: '/expenses/mine', icon: 'receipt-2' },
  { label: 'dashboard.shortcuts.quick_expense', desc: 'dashboard.shortcuts.quick_expense_desc', route: '/expenses/new', icon: 'plus' },
  { label: 'dashboard.shortcuts.my_report', desc: 'dashboard.shortcuts.my_report_desc', route: '/custodies/mine', icon: 'report-analytics' },
  { label: 'dashboard.shortcuts.track_custody', desc: 'dashboard.shortcuts.track_custody_desc', route: '/custodies/mine', icon: 'eye' },
  { label: 'dashboard.shortcuts.active_custodies', desc: 'dashboard.shortcuts.active_custodies_desc', route: '/custodies/mine', icon: 'list-check' },
];

/** dashboard/shortcuts/researcher.blade.php */
const RESEARCHER_SHORTCUTS: Shortcut[] = [
  { label: 'nav.my_cases', desc: 'dashboard.shortcuts.my_cases_desc', route: '/cases/mine', icon: 'user' },
  { label: 'dashboard.shortcuts.new_case', desc: 'dashboard.shortcuts.new_case_desc', route: '/cases/new', icon: 'plus' },
  { label: 'dashboard.shortcuts.all_cases', desc: 'dashboard.shortcuts.all_cases_desc', route: '/cases', icon: 'heart-handshake' },
  { label: 'dashboard.shortcuts.pending_cases', desc: 'dashboard.shortcuts.pending_cases_desc', route: '/cases/mine', icon: 'history' },
  { label: 'dashboard.shortcuts.approved_cases', desc: 'dashboard.shortcuts.approved_cases_desc', route: '/cases/mine', icon: 'list-check' },
  { label: 'dashboard.shortcuts.my_report', desc: 'dashboard.shortcuts.my_report_desc', route: '/cases/mine', icon: 'report-analytics' },
];
