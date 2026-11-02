# Generates the shared BEM block partials (plan 15.9). Idempotent; edit freely afterwards.
import os

BASE = os.path.join(os.path.dirname(__file__), '..', 'src', 'styles', '03-blocks')

blocks = {}

blocks['_print'] = '''/* Print always uses the light theme values (plan 23) */
@media print {
  @page {
    size: A4;
    margin: 14mm;
  }

  html,
  body {
    background: var(--palette-white) !important;
    color: var(--palette-ink) !important;
  }

  .app-shell__sidebar,
  .app-shell__topbar,
  .app-shell__bottom-nav,
  .filter-bar,
  .toast-host,
  .sticky-actions,
  .p-paginator,
  .u-print-hidden {
    display: none !important;
  }

  .app-shell__main,
  .app-shell__content {
    display: block !important;
    padding: 0 !important;
    margin: 0 !important;
    max-width: none !important;
  }

  .print-sheet__org {
    display: block !important;
    border-block-end: 2px solid var(--palette-taupe);
    padding-block-end: 8px;
    margin-block-end: 12px;
  }

  table {
    border-collapse: collapse;
  }

  thead {
    display: table-header-group;
  }

  tr {
    break-inside: avoid;
  }
}
'''

blocks['app-shell'] = '''/* App shell: sidebar (right in RTL) + topbar + content (plan 19.1) */
.app-shell {
  display: flex;
  min-height: 100vh;
  flex-direction: row-reverse; /* RTL: sidebar on the reading-start (right) side */
}

.app-shell--without-sidebar {
  flex-direction: row;
}

.app-shell__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.app-shell__content {
  width: 100%;
  max-width: var(--content-max);
  margin-inline: auto;
  padding: var(--space-6) var(--gutter) var(--space-10);
  flex: 1;
}

@media (max-width: 576px) {
  .app-shell__content {
    padding: var(--space-4) var(--space-4) calc(var(--space-10) + var(--topbar-h));
  }
}
'''

blocks['topbar'] = '''.topbar {
  height: var(--topbar-h);
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding-inline: var(--space-4);
  background: var(--color-surface);
  border-block-end: 1px solid var(--color-hairline);
  position: sticky;
  top: 0;
  z-index: 900;
}

.topbar__title {
  font-size: var(--text-md);
  font-weight: 600;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.topbar__spacer {
  flex: 1;
}

.topbar__search {
  width: clamp(160px, 26vw, 320px);
}

.topbar__icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border: 1px solid var(--color-control-border);
  border-radius: var(--radius-m);
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
  transition: background-color var(--dur-fast) var(--ease-out);
}

.topbar__icon-btn:hover {
  background: var(--color-surface-raised);
}

.topbar__broadcast {
  border: 1.5px solid var(--color-margin-rule);
  border-radius: var(--radius-m);
  background: var(--color-surface-inset);
  padding: var(--space-2) var(--space-3);
  display: flex;
  align-items: center;
  gap: var(--space-2);
  max-width: 100%;
}
'''

blocks['sidebar'] = '''.sidebar {
  width: var(--sidebar-w);
  background: var(--color-surface);
  border-inline-start: 1px solid var(--color-hairline);
  display: flex;
  flex-direction: column;
  position: sticky;
  top: 0;
  height: 100vh;
  overflow-y: auto;
  transition: width var(--dur-base) var(--ease-out);
  z-index: 950;
}

.sidebar--collapsed {
  width: var(--sidebar-w-collapsed);
}

.sidebar__brand {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-4);
  border-block-end: 1px solid var(--color-hairline);
  min-height: var(--topbar-h);
}

.sidebar__brand-logo {
  width: 36px;
  height: 36px;
  flex-shrink: 0;
}

.sidebar__brand-name {
  font-family: var(--font-display);
  font-size: var(--text-md);
  font-weight: 700;
  white-space: nowrap;
}

.sidebar__group-label {
  padding: var(--space-4) var(--space-4) var(--space-1);
  font-size: var(--text-2xs);
  color: var(--color-text-muted);
  white-space: nowrap;
}

.sidebar--collapsed .sidebar__group-label,
.sidebar--collapsed .sidebar__brand-name,
.sidebar--collapsed .sidebar__link-label {
  display: none;
}

.sidebar__link {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) var(--space-4);
  margin: 1px var(--space-2);
  border-radius: var(--radius-m);
  color: var(--color-text);
  text-decoration: none;
  white-space: nowrap;
  border-inline-start: 3px solid transparent;
}

.sidebar__link:hover {
  background: var(--color-surface-raised);
}

.sidebar__link--active {
  background: var(--color-primary-soft);
  border-inline-start-color: var(--color-primary);
  font-weight: 600;
}

.sidebar__link-icon {
  width: 22px;
  height: 22px;
  flex-shrink: 0;
}

.sidebar__toggle {
  margin-block-start: auto;
  border-block-start: 1px solid var(--color-hairline);
  border-radius: 0;
  justify-content: center;
}

@media (max-width: 1023px) {
  .sidebar {
    display: none;
  }
}
'''

blocks['bottom-nav'] = '''.bottom-nav {
  display: none;
  position: fixed;
  inset-inline: 0;
  bottom: 0;
  height: calc(var(--topbar-h) + 4px);
  background: var(--color-surface);
  border-block-start: 1px solid var(--color-hairline);
  z-index: 900;
}

.bottom-nav__item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  font-size: var(--text-2xs);
  color: var(--color-text-muted);
  text-decoration: none;
  padding-block: var(--space-1);
}

.bottom-nav__item--active {
  color: var(--color-primary);
  font-weight: 600;
}

@media (max-width: 1023px) {
  .bottom-nav {
    display: flex;
  }
}
'''

blocks['page-header'] = '''.page-header {
  margin-block-end: var(--space-5);
}

.page-header__row {
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  flex-wrap: wrap;
}

.page-header__title {
  font-family: var(--font-display);
  font-size: var(--text-2xl);
  font-weight: 700;
}

.page-header__subtitle {
  color: var(--color-text-muted);
  font-size: var(--text-xs);
  margin-block-start: 2px;
}

.page-header__actions {
  margin-inline-start: auto;
  display: flex;
  gap: var(--space-2);
  flex-wrap: wrap;
}

.page-header__meta {
  display: flex;
  gap: var(--space-2);
  align-items: center;
  margin-block-start: var(--space-2);
  flex-wrap: wrap;
}
'''

blocks['filter-bar'] = '''.filter-bar {
  background: var(--color-surface);
  border: 1px solid var(--color-hairline);
  border-radius: var(--radius-l);
  padding: var(--space-3) var(--space-4);
  margin-block-end: var(--space-4);
}

.filter-bar__row {
  display: flex;
  gap: var(--space-2);
  flex-wrap: wrap;
  align-items: flex-end;
}

.filter-bar__chips {
  display: flex;
  gap: var(--space-2);
  flex-wrap: wrap;
  margin-block-start: var(--space-2);
}

.filter-bar__spacer {
  flex: 1;
}
'''

blocks['data-table'] = '''/* The ledger table (plan 11.1): hairline rules, margin double-rule, decimal-aligned numerals */
.ledger-table {
  background: var(--color-surface);
  border: 1px solid var(--color-hairline);
  border-radius: var(--radius-l);
  overflow: hidden;
}

.ledger-table__header {
  padding: var(--space-2) var(--space-4);
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-wrap: wrap;
  background: var(--color-surface-inset);
  border-block-end: 1px solid var(--color-hairline);
}

.ledger-table__title {
  font-weight: 600;
}

.ledger-table__spacer {
  flex: 1;
}

.ledger-table__summary {
  padding: var(--space-2) var(--space-4);
  border-block-start: 1px solid var(--color-hairline);
  background: var(--color-surface-inset);
  font-size: var(--text-xs);
  display: flex;
  gap: var(--space-4);
  flex-wrap: wrap;
}

.ledger-table__cell-num {
  text-align: end;
  font-variant-numeric: tabular-nums;
  direction: ltr;
  unicode-bidi: isolate;
}

.ledger-table__row-flash {
  animation: ledger-flash 900ms var(--ease-out) 1;
}

@keyframes ledger-flash {
  0% {
    background-color: var(--color-primary-soft);
  }

  100% {
    background-color: transparent;
  }
}

.ledger-table__actions {
  display: flex;
  gap: var(--space-1);
  justify-content: flex-end;
}

.ledger-table__stacked-row {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: var(--space-2) var(--space-4);
  padding: var(--space-3) 0;
}

.ledger-table__stacked-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.ledger-table__stacked-label {
  font-size: var(--text-2xs);
  color: var(--color-text-muted);
}
'''

blocks['stat-tile'] = '''.stat-tile {
  background: var(--color-surface);
  border: 1px solid var(--color-hairline);
  border-radius: var(--radius-l);
  padding: var(--space-4) var(--space-5);
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  position: relative;
  min-width: 0;
}

.stat-tile--inset {
  background: var(--color-surface-inset);
}

.stat-tile--wide {
  grid-column: span 2;
}

.stat-tile__label {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
  display: flex;
  align-items: center;
  gap: var(--space-1);
}

.stat-tile__value {
  font-family: var(--font-display);
  font-size: var(--text-2xl);
  font-weight: 700;
  line-height: 1.2;
  direction: ltr;
  unicode-bidi: isolate;
  text-align: start;
}

.stat-tile__value--hero {
  font-size: var(--text-hero);
}

.stat-tile__currency {
  font-family: var(--font-ui);
  font-size: var(--text-xs);
  font-weight: 400;
  color: var(--color-text-muted);
}

.stat-tile__caption {
  font-size: var(--text-2xs);
  color: var(--color-text-muted);
}

.stat-tile__spark {
  margin-block-start: var(--space-2);
}
'''

blocks['kpi-grid'] = '''.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--space-4);
  margin-block-end: var(--space-5);
}

@media (min-width: 1366px) {
  .kpi-grid--asymmetric {
    grid-template-columns: 2fr 1fr 1fr 1fr;
  }
}
'''

blocks['stamp'] = '''/* Stamps: meaning by icon+fill+border, never by color alone (plan 10.1.3) */
.stamp {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: 1px var(--space-2);
  border-radius: var(--radius-s);
  font-size: var(--text-2xs);
  font-weight: 600;
  border: 1.5px solid transparent;
  white-space: nowrap;
}

.stamp__icon {
  width: 13px;
  height: 13px;
  flex-shrink: 0;
}

/* success / approved */
.stamp--success {
  border-color: var(--color-primary);
  color: var(--color-primary);
  background: var(--color-surface);
}

/* rejected / error — strongest: solid fill + double border */
.stamp--danger {
  background: var(--palette-ink);
  color: var(--palette-white);
  box-shadow: 0 0 0 1px var(--palette-ink), 0 0 0 2px var(--color-surface), 0 0 0 3px var(--palette-ink);
}

:root[data-theme='dark'] .stamp--danger {
  background: var(--palette-cream);
  color: var(--palette-ink);
  box-shadow: 0 0 0 1px var(--palette-cream), 0 0 0 2px var(--color-surface), 0 0 0 3px var(--palette-cream);
}

/* pending — dashed */
.stamp--pending {
  border-style: dashed;
  border-color: var(--color-text-muted);
  color: var(--color-text-muted);
}

/* info — filled soft */
.stamp--info {
  background: var(--color-primary-soft);
  color: var(--color-text);
  border-color: transparent;
}

/* neutral / closed */
.stamp--neutral {
  background: var(--color-surface-inset);
  color: var(--color-text-muted);
}

.stamp--lg {
  font-size: var(--text-sm);
  padding: var(--space-1) var(--space-3);
}

.stamp--lg .stamp__icon {
  width: 16px;
  height: 16px;
}

.stamp--stamp-in {
  animation: stamp-in 220ms var(--ease-out) 1;
}

@keyframes stamp-in {
  0% {
    transform: scale(1.08) rotate(-2deg);
    opacity: 0.6;
  }

  100% {
    transform: scale(1) rotate(0);
    opacity: 1;
  }
}
'''

blocks['flow-ribbon'] = '''.flow-ribbon {
  display: flex;
  align-items: center;
  gap: 0;
  flex-wrap: wrap;
}

.flow-ribbon__node {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: 2px var(--space-2);
  border-radius: var(--radius-full);
  border: 1.5px solid var(--color-hairline);
  background: var(--color-surface);
  font-size: var(--text-2xs);
  color: var(--color-text-muted);
}

.flow-ribbon__node--done {
  border-color: var(--color-primary);
  color: var(--color-primary);
}

.flow-ribbon__node--current {
  border-color: var(--color-text-muted);
  border-style: dashed;
  color: var(--color-text);
  animation: flow-march 1.6s linear infinite;
}

@keyframes flow-march {
  to {
    background-position: 12px 0;
  }
}

.flow-ribbon__node-icon {
  width: 12px;
  height: 12px;
}

.flow-ribbon__link {
  width: 22px;
  border-block-start: 1.5px solid var(--color-hairline);
}

.flow-ribbon__link--dashed {
  border-block-start-style: dashed;
}
'''

blocks['balance-ring'] = '''.balance-ring {
  display: flex;
  align-items: center;
  gap: var(--space-4);
  flex-wrap: wrap;
}

.balance-ring__svg {
  width: 132px;
  height: 132px;
  flex-shrink: 0;
}

.balance-ring__track {
  stroke: var(--color-hairline);
}

.balance-ring__center-value {
  font-family: var(--font-display);
  font-size: var(--text-md);
  font-weight: 700;
  fill: var(--color-text);
}

.balance-ring__center-label {
  font-family: var(--font-ui);
  font-size: 9px;
  fill: var(--color-text-muted);
}

.balance-ring__legend {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 180px;
}
'''

blocks['segmented-bar'] = '''.segmented-bar {
  display: flex;
  width: 100%;
  height: 12px;
  border-radius: var(--radius-s);
  overflow: hidden;
  border: 1px solid var(--color-hairline);
}

.segmented-bar__segment {
  min-width: 0;
}

.segmented-bar__legend {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2) var(--space-4);
  margin-block-start: var(--space-2);
  font-size: var(--text-2xs);
}
'''

blocks['direction-trail'] = '''.direction-trail {
  display: inline-flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--space-1);
  min-width: 0;
}

.direction-trail__chip {
  background: var(--color-surface-inset);
  border: 1px solid var(--color-hairline);
  border-radius: var(--radius-s);
  padding: 0 var(--space-2);
  font-size: var(--text-2xs);
  color: var(--color-text);
  white-space: nowrap;
}

.direction-trail__chip--last {
  background: var(--color-primary-soft);
  border-color: var(--color-control-border);
  font-weight: 600;
}

.direction-trail__sep {
  color: var(--color-text-muted);
  font-size: var(--text-2xs);
}

.direction-trail--select .direction-trail__chip {
  cursor: pointer;
}

.direction-trail--select .direction-trail__chip:hover,
.direction-trail__chip--selected {
  border-color: var(--color-primary);
  background: var(--color-primary-soft);
}
'''

blocks['money'] = '''.money {
  font-variant-numeric: tabular-nums;
  direction: ltr;
  unicode-bidi: isolate;
  display: inline-block;
  white-space: nowrap;
}

.money__value {
  font-weight: inherit;
}

.money__currency {
  font-size: 0.75em;
  font-weight: 400;
  color: var(--color-text-muted);
  margin-inline-start: 4px;
}

.money--negative .money__value {
  font-weight: 700;
}

.money--hero {
  font-family: var(--font-display);
  font-size: var(--text-2xl);
  font-weight: 700;
}
'''

blocks['field'] = '''.field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.field__label {
  font-size: var(--text-xs);
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 4px;
}

.field__required {
  color: var(--color-text);
  font-weight: 700;
}

.field__hint {
  font-size: var(--text-2xs);
  color: var(--color-text-muted);
}

.field__error {
  font-size: var(--text-2xs);
  font-weight: 700;
  color: var(--color-text);
  display: flex;
  align-items: center;
  gap: 4px;
}

.field--invalid .p-inputtext,
.field--invalid .p-select,
.field--invalid .p-textarea {
  border: 2px solid var(--palette-ink);
}

:root[data-theme='dark'] .field--invalid .p-inputtext,
:root[data-theme='dark'] .field--invalid .p-select,
:root[data-theme='dark'] .field--invalid .p-textarea {
  border-color: var(--palette-cream);
}
'''

blocks['form-section'] = '''.form-section {
  background: var(--color-surface);
  border: 1px solid var(--color-hairline);
  border-radius: var(--radius-l);
  padding: var(--space-5);
  margin-block-end: var(--space-4);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.form-section__title {
  font-size: var(--text-md);
  font-weight: 600;
  padding-block-end: var(--space-2);
  border-block-end: 1px solid var(--color-hairline);
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.form-section__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: var(--space-4);
}
'''

blocks['sticky-actions'] = '''.sticky-actions {
  position: sticky;
  inset-block-end: 0;
  background: var(--color-surface);
  border-block-start: 1px solid var(--color-hairline);
  padding: var(--space-3) var(--space-4);
  display: flex;
  gap: var(--space-2);
  justify-content: flex-end;
  z-index: 10;
}
'''

blocks['empty-state'] = '''.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-10) var(--space-4);
  text-align: center;
  color: var(--color-text-muted);
}

.empty-state__pattern {
  width: 96px;
  height: 96px;
  color: var(--color-hairline);
}

.empty-state__title {
  font-weight: 600;
  color: var(--color-text);
}

.empty-state__text {
  font-size: var(--text-xs);
  max-width: 420px;
}
'''

blocks['skeleton'] = '''.skeleton {
  background: var(--color-surface-inset);
  border-radius: var(--radius-m);
  animation: skeleton-fade 1.4s var(--ease-in-out) infinite alternate;
}

.skeleton--text {
  height: 14px;
}

.skeleton--title {
  height: 24px;
  width: 40%;
}

.skeleton--tile {
  height: 96px;
}

.skeleton--row {
  height: 42px;
  margin-block-end: var(--space-2);
}

@keyframes skeleton-fade {
  from {
    opacity: 1;
  }

  to {
    opacity: 0.55;
  }
}

:root[data-motion='off'] .skeleton,
:root[data-motion='lite'] .skeleton {
  animation: none;
}
'''

blocks['timeline'] = '''.timeline {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
}

.timeline__item {
  display: grid;
  grid-template-columns: 20px 1fr;
  gap: var(--space-3);
  padding-block: var(--space-3);
  border-inline-start: 1px solid var(--color-margin-rule);
  padding-inline-start: var(--space-4);
  position: relative;
}

.timeline__item::before {
  content: '';
  position: absolute;
  inset-inline-start: -5px;
  top: calc(var(--space-3) + 6px);
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: var(--color-surface);
  border: 2px solid var(--color-primary);
}

.timeline__item--muted::before {
  border-color: var(--color-text-muted);
}

.timeline__head {
  display: flex;
  gap: var(--space-2);
  align-items: baseline;
  flex-wrap: wrap;
}

.timeline__title {
  font-weight: 600;
  font-size: var(--text-xs);
}

.timeline__time {
  font-size: var(--text-2xs);
  color: var(--color-text-muted);
}

.timeline__body {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}
'''

blocks['chart-card'] = '''.chart-card {
  background: var(--color-surface);
  border: 1px solid var(--color-hairline);
  border-radius: var(--radius-l);
  padding: var(--space-4) var(--space-5);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-width: 0;
}

.chart-card__head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-wrap: wrap;
}

.chart-card__title {
  font-weight: 600;
  font-size: var(--text-sm);
}

.chart-card__spacer {
  flex: 1;
}

.chart-card__body {
  position: relative;
  min-height: 220px;
}

.chart-card__table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--text-xs);
}

.chart-card__table th,
.chart-card__table td {
  text-align: start;
  padding: var(--space-1) var(--space-2);
  border-block-end: 1px solid var(--color-hairline);
}
'''

blocks['dialog'] = '''.dialog {
  border-radius: var(--radius-l);
}

.dialog__alert {
  border: 1.5px solid var(--palette-ink);
  border-radius: var(--radius-m);
  padding: var(--space-2) var(--space-3);
  margin-block-end: var(--space-3);
  font-size: var(--text-xs);
  display: flex;
  gap: var(--space-2);
  align-items: flex-start;
}

:root[data-theme='dark'] .dialog__alert {
  border-color: var(--palette-cream);
}
'''

blocks['toast-host'] = '''.toast-host {
  position: fixed;
  inset-block-end: var(--space-4);
  inset-inline-start: var(--space-4);
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  z-index: 1200;
}

.toast-host__item {
  background: var(--color-surface);
  border: 1px solid var(--color-margin-rule);
  border-inline-start: 4px solid var(--color-primary);
  border-radius: var(--radius-m);
  padding: var(--space-2) var(--space-4);
  box-shadow: 2px 2px 0 var(--color-hairline);
  max-width: min(420px, 90vw);
  animation: toast-in 160ms var(--ease-out);
}

.toast-host__item--error {
  border-inline-start-color: var(--palette-ink);
}

:root[data-theme='dark'] .toast-host__item--error {
  border-inline-start-color: var(--palette-cream);
}

@keyframes toast-in {
  from {
    transform: translateY(8px);
    opacity: 0;
  }

  to {
    transform: translateY(0);
    opacity: 1;
  }
}
'''

blocks['notification-bell'] = '''.notification-bell {
  position: relative;
}

.notification-bell__badge {
  position: absolute;
  top: 2px;
  inset-inline-end: 2px;
  min-width: 16px;
  height: 16px;
  padding-inline: 3px;
  border-radius: var(--radius-full);
  background: var(--palette-ink);
  color: var(--palette-white);
  font-size: 10px;
  line-height: 16px;
  text-align: center;
  font-variant-numeric: tabular-nums;
}

:root[data-theme='dark'] .notification-bell__badge {
  background: var(--palette-cream);
  color: var(--palette-ink);
}

.notification-bell__list {
  width: min(380px, 92vw);
  max-height: 60vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

.notification-bell__item {
  padding: var(--space-2) var(--space-3);
  border-block-end: 1px solid var(--color-hairline);
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.notification-bell__item--unread {
  background: var(--color-surface-raised);
}

.notification-bell__item-title {
  font-weight: 600;
  font-size: var(--text-xs);
}

.notification-bell__item-text {
  font-size: var(--text-2xs);
  color: var(--color-text-muted);
}
'''

blocks['theme-toggle'] = '''.theme-toggle {
  border-radius: var(--radius-full);
}
'''

blocks['command-palette'] = '''.command-palette__input {
  width: 100%;
}

.command-palette__results {
  max-height: 50vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

.command-palette__group {
  padding: var(--space-1) var(--space-3);
  font-size: var(--text-2xs);
  color: var(--color-text-muted);
  background: var(--color-surface-inset);
}

.command-palette__item {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-m);
  cursor: pointer;
  font-size: var(--text-xs);
}

.command-palette__item:hover,
.command-palette__item--active {
  background: var(--color-surface-raised);
}
'''

blocks['key-value-list'] = '''.key-value-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: var(--space-2) var(--space-6);
}

.key-value-list__item {
  display: flex;
  flex-direction: column;
  border-inline-start: 2px solid var(--color-margin-rule);
  padding-inline-start: var(--space-2);
}

.key-value-list__key {
  font-size: var(--text-2xs);
  color: var(--color-text-muted);
}

.key-value-list__value {
  font-size: var(--text-sm);
  min-height: 1.4em;
}
'''

blocks['attachments'] = '''.attachments__file {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2);
  border: 1px solid var(--color-hairline);
  border-radius: var(--radius-m);
  background: var(--color-surface-inset);
  font-size: var(--text-xs);
}

.attachments__name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
'''

blocks['chat'] = '''.chat {
  display: flex;
  flex-direction: column;
  height: calc(100vh - var(--topbar-h) - 140px);
  min-height: 420px;
  background: var(--color-surface);
  border: 1px solid var(--color-hairline);
  border-radius: var(--radius-l);
}

.chat__log {
  flex: 1;
  overflow-y: auto;
  padding: var(--space-4);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.chat__msg {
  max-width: 72%;
  border: 1px solid var(--color-hairline);
  border-radius: var(--radius-m);
  padding: var(--space-2) var(--space-3);
  background: var(--color-surface-inset);
}

.chat__msg--mine {
  align-self: flex-end;
  background: var(--color-primary-soft);
}

.chat__msg-head {
  font-size: var(--text-2xs);
  color: var(--color-text-muted);
  display: flex;
  gap: var(--space-2);
}

.chat__body {
  font-size: var(--text-sm);
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.chat__composer {
  display: flex;
  gap: var(--space-2);
  padding: var(--space-3);
  border-block-start: 1px solid var(--color-hairline);
}

.chat__poll {
  margin-block-start: var(--space-2);
  border: 1px dashed var(--color-control-border);
  border-radius: var(--radius-m);
  padding: var(--space-2);
}
'''

blocks['login'] = '''.login {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1fr 1fr;
}

.login__side {
  background: var(--color-primary);
  color: var(--color-on-primary);
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: var(--space-4);
  padding: var(--space-8);
  position: relative;
  overflow: hidden;
}

.login__side-pattern {
  position: absolute;
  inset: 0;
  opacity: 1;
}

.login__side-content {
  position: relative;
  text-align: center;
}

.login__title {
  font-family: var(--font-display);
  font-size: var(--text-2xl);
  font-weight: 700;
}

.login__form-side {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-6);
}

.login__form {
  width: min(400px, 100%);
  background: var(--color-surface);
  border: 1px solid var(--color-hairline);
  border-radius: var(--radius-l);
  padding: var(--space-8);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.login__brand {
  width: 56px;
  height: 56px;
  margin-inline: auto;
}

.login__actions {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

@media (max-width: 768px) {
  .login {
    grid-template-columns: 1fr;
  }

  .login__side {
    display: none;
  }
}
'''

blocks['line-items'] = '''.line-items__row {
  display: grid;
  grid-template-columns: 1fr 100px 140px 40px;
  gap: var(--space-2);
  align-items: center;
}

.line-items__total {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  font-weight: 600;
  padding-block-start: var(--space-2);
}

.line-items__warning {
  font-size: var(--text-2xs);
  color: var(--color-text);
  border: 1.5px dashed var(--color-control-border);
  border-radius: var(--radius-m);
  padding: var(--space-2);
  margin-block-start: var(--space-2);
  display: flex;
  gap: var(--space-1);
  align-items: center;
}

@media (max-width: 576px) {
  .line-items__row {
    grid-template-columns: 1fr 80px 40px;
  }

  .line-items__cell-price {
    grid-column: 1 / -1;
  }
}
'''

blocks['utilities-import'] = '''@use '04-utilities/utilities' as *;
'''

os.makedirs(BASE, exist_ok=True)
for name, content in blocks.items():
    if name.startswith('_'):
        continue
    with open(os.path.join(BASE, f'{name}.scss'), 'w', encoding='utf-8', newline='\n') as f:
        f.write(content.lstrip())
print('wrote', len(blocks), 'block files')
