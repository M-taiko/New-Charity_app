import { ChangeDetectionStrategy, Component, Directive, TemplateRef, computed, effect, inject, input, model, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { NgTemplateOutlet } from '@angular/common';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { ButtonModule } from 'primeng/button';
import { SelectModule } from 'primeng/select';
import { MultiSelectModule } from 'primeng/multiselect';
import { InputTextModule } from 'primeng/inputtext';
import { DatePickerModule } from 'primeng/datepicker';
import { ToggleSwitchModule } from 'primeng/toggleswitch';
import { Router } from '@angular/router';
import type { TableFilterDef, TableFilterValue } from '@shared/lib/table-filters';
import { activeFilterCount, isFilterActive } from '@shared/lib/table-filters';

/**
 * T6-30 custom filter renderer: <ng-template appFilterCustom="direction" let-ctx>
 * receives { $implicit: currentValue, key, set: (value) => void } — e.g. the
 * expense direction cascade reuses direction-picker through this hook.
 */
@Directive({ selector: '[appFilterCustom]' })
export class FilterCustomDirective {
  appFilterCustom = input.required<string>();
  template = inject(TemplateRef<unknown>);
}

/**
 * The one filter UI (T6-30): a "Filters" button with an active-count badge
 * opening a panel, a row of removable chips for active filters, "Clear all",
 * and per-type controls (text with debounce, select, multiselect, boolean,
 * number/date ranges, month). Values are validated by type, applied on
 * "Apply" (live for text), and mirrored into the URL so links and refresh
 * reproduce a filtered view. Rendered by app-data-table — never by a page.
 */
@Component({
  selector: 'app-table-filters',
  standalone: true,
  imports: [TranslatePipe, FormsModule, ButtonModule, SelectModule, MultiSelectModule, InputTextModule, DatePickerModule, ToggleSwitchModule, NgTemplateOutlet],
  template: `
    <div class="table-filters">
      <p-button [outlined]="true" size="small" severity="secondary" (onClick)="panelOpen() ? panelOpen.set(false) : panelOpen.set(true)">
        <span class="u-flex u-gap-1 u-center">
          <svg width="16" height="16" aria-hidden="true"><use href="assets/icons/sprite.svg#tabler-filter" /></svg>
          {{ 'common.filters' | translate }}
          @if (activeCount() > 0) {
            <span class="app-tabs__badge">{{ activeCount() }}</span>
          }
        </span>
      </p-button>

      @if (panelOpen()) {
        <div class="table-filters__panel" role="dialog" [attr.aria-label]="'common.filters' | translate">
          @for (def of visibleDefs(); track def.key) {
            <label class="table-filters__field">
              <span class="table-filters__label">{{ def.label | translate }}</span>
              @switch (def.type) {
                @case ('text') {
                  <input pInputText type="search" [ngModel]="textValue(def.key)" (ngModelChange)="setDraft(def.key, $event)" name="f-{{ def.key }}" />
                }
                @case ('select') {
                  <p-select appendTo="body" [options]="optionsFor(def)" optionLabel="label" optionValue="value"
                            [ngModel]="draftValue(def.key)" (ngModelChange)="setDraft(def.key, $event)"
                            [showClear]="true" name="f-{{ def.key }}" styleClass="u-full" />
                }
                @case ('multiselect') {
                  <p-multiselect appendTo="body" [options]="optionsFor(def)" optionLabel="label"
                            [ngModel]="draftValue(def.key) ?? []" (ngModelChange)="setDraft(def.key, $event)"
                            name="f-{{ def.key }}" styleClass="u-full" [display]="'chip'" />
                }
                @case ('boolean') {
                  <p-toggleswitch [ngModel]="draftValue(def.key) === true" (ngModelChange)="setDraft(def.key, $event)" name="f-{{ def.key }}" />
                }
                @case ('number-range') {
                  <span class="table-filters__range">
                    <input pInputText type="number" [placeholder]="'common.min' | translate"
                           [ngModel]="rangePart(def.key, 'min')" (ngModelChange)="setRange(def.key, 'min', $event)" name="f-{{ def.key }}-min" />
                    <input pInputText type="number" [placeholder]="'common.max' | translate"
                           [ngModel]="rangePart(def.key, 'max')" (ngModelChange)="setRange(def.key, 'max', $event)" name="f-{{ def.key }}-max" />
                  </span>
                }
                @case ('date-range') {
                  <span class="table-filters__range">
                    <p-datepicker appendTo="body" view="date" dateFormat="yy-mm-dd" [showIcon]="true"
                                  [placeholder]="'common.from' | translate"
                                  [ngModel]="rangePart(def.key, 'from')" (ngModelChange)="setRange(def.key, 'from', $event)"
                                  name="f-{{ def.key }}-from" />
                    <p-datepicker appendTo="body" view="date" dateFormat="yy-mm-dd" [showIcon]="true"
                                  [placeholder]="'common.to' | translate"
                                  [ngModel]="rangePart(def.key, 'to')" (ngModelChange)="setRange(def.key, 'to', $event)"
                                  name="f-{{ def.key }}-to" />
                  </span>
                }
                @case ('month') {
                  <p-datepicker appendTo="body" view="month" dateFormat="yy-mm"
                                [ngModel]="draftValue(def.key)" (ngModelChange)="setDraft(def.key, $event)"
                                name="f-{{ def.key }}" styleClass="u-full" />
                }
                @default {
                  @if (customTemplates()[def.key]; as tpl) {
                    <ng-container
                      [ngTemplateOutlet]="tpl"
                      [ngTemplateOutletContext]="{ $implicit: draft()[def.key], key: def.key, set: makeSetter(def.key) }"
                    />
                  } @else {
                    <span class="u-small u-muted">{{ 'common.filter_custom' | translate }}</span>
                  }
                }
              }
            </label>
          }
          <span class="table-filters__actions">
            <p-button [label]="'common.clear_all' | translate" severity="secondary" [text]="true" size="small" (onClick)="clearAll()" />
            <p-button [label]="'common.apply' | translate" size="small" (onClick)="apply()" />
          </span>
        </div>
      }

      @if (chips().length > 0) {
        <div class="table-filters__chips" role="list">
          @for (chip of chips(); track chip.key) {
            <button type="button" class="table-filters__chip" (click)="remove(chip.key)">
              {{ chip.label }}
              <svg width="12" height="12" aria-hidden="true"><use href="assets/icons/sprite.svg#tabler-x" /></svg>
            </button>
          }
          <button type="button" class="table-filters__chip table-filters__chip--clear" (click)="clearAll()">
            {{ 'common.clear_all' | translate }}
          </button>
        </div>
      }
    </div>
  `,
  styles: `
    .table-filters {
      position: relative;
      display: flex;
      align-items: center;
      gap: var(--space-2);
      flex-wrap: wrap;
    }
    .table-filters__panel {
      position: absolute;
      inset-block-start: calc(100% + var(--space-2));
      inset-inline-start: 0;
      z-index: 60;
      background: var(--color-surface);
      border: 1px solid var(--color-hairline);
      border-radius: var(--radius-m);
      box-shadow: 2px 2px 0 var(--color-hairline);
      padding: var(--space-3);
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
      gap: var(--space-3);
      min-inline-size: min(520px, 90vw);
      max-inline-size: min(760px, 92vw);
    }
    .table-filters__field {
      display: flex;
      flex-direction: column;
      gap: var(--space-1);
      font-size: var(--text-xs);
    }
    .table-filters__label {
      color: var(--color-text-muted);
      font-weight: 600;
    }
    .table-filters__range {
      display: flex;
      gap: var(--space-1);
    }
    .table-filters__range > * {
      min-width: 0;
      flex: 1;
    }
    .table-filters__actions {
      grid-column: 1 / -1;
      display: flex;
      justify-content: flex-end;
      gap: var(--space-2);
    }
    .table-filters__chips {
      display: flex;
      gap: var(--space-1);
      flex-wrap: wrap;
    }
    .table-filters__chip {
      display: inline-flex;
      align-items: center;
      gap: var(--space-1);
      border: 1px solid var(--color-hairline);
      border-radius: var(--radius-full);
      background: var(--color-surface-inset);
      color: var(--color-text);
      font-size: var(--text-2xs);
      font-weight: 600;
      padding: 2px var(--space-2);
      cursor: pointer;
    }
    .table-filters__chip:hover {
      border-color: var(--color-control-border);
    }
    .table-filters__chip--clear {
      background: transparent;
      color: var(--color-text-muted);
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TableFiltersComponent {
  defs = input.required<TableFilterDef[]>();
  values = model<Record<string, TableFilterValue | undefined>>({});
  /** options resolved for lookup defs by the host (keyed by filter key) */
  lookupOptions = input<Record<string, { value: unknown; label: string }[]>>({});
  /** custom renderer templates by filter key (T6-30 type:'custom') */
  customTemplates = input<Record<string, TemplateRef<unknown> | null>>({});

  applied = output<Record<string, TableFilterValue | undefined>>();

  panelOpen = signal(false);
  draft = signal<Record<string, TableFilterValue | undefined>>({});
  private translate = inject(TranslateService);
  private router = inject(Router);

  readonly visibleDefs = computed(() =>
    this.defs()
      .filter((d) => !d.hidden && (d.type !== 'custom' || this.customTemplates()[d.key] != null))
      .sort((a, b) => (a.order ?? 0) - (b.order ?? 0)),
  );

  readonly activeCount = computed(() => activeFilterCount(this.values(), this.defs()));

  constructor() {
    effect(() => {
      // keep the draft in sync with applied values
      this.draft.set({ ...this.values() });
    });
  }

  /** typed draft accessors — template expressions can't spread objects */
  draftValue(key: string): TableFilterValue | undefined {
    return this.draft()[key];
  }

  textValue(key: string): string {
    const v = this.draft()[key];
    return typeof v === 'string' ? v : '';
  }

  setDraft(key: string, value: unknown): void {
    this.draft.update((d) => ({ ...d, [key]: value as TableFilterValue }));
  }

  /** setter handed to custom filter renderers via the template context */
  makeSetter(key: string): (value: unknown) => void {
    return (value: unknown) => { this.draft.update((d) => ({ ...d, [key]: value as TableFilterValue })); };
  }

  optionsFor(def: TableFilterDef): { value: unknown; label: string }[] {
    return def.options ?? this.lookupOptions()[def.key] ?? [];
  }

  rangePart(key: string, part: string): number | string | null {
    const v = this.draft()[key] as Record<string, unknown> | undefined;
    const val = v?.[part];
    return (val as number | string | null) ?? null;
  }

  setRange(key: string, part: string, value: number | string | null): void {
    this.draft.update((d: Record<string, TableFilterValue | undefined>) => {
      const current = (d[key] as Record<string, unknown> | undefined) ?? {};
      return { ...d, [key]: { ...current, [part]: value === '' ? null : value } };
    });
  }

  apply(): void {
    this.values.set({ ...this.draft() });
    this.applied.emit({ ...this.draft() });
    this.panelOpen.set(false);
    this.syncUrl({ ...this.draft() });
  }

  remove(key: string): void {
    const next = { ...this.values(), [key]: undefined };
    this.values.set(next);
    this.draft.set({ ...next });
    this.applied.emit(next);
    this.syncUrl(next);
  }

  clearAll(): void {
    const next: Record<string, TableFilterValue | undefined> = {};
    this.values.set(next);
    this.draft.set({});
    this.applied.emit(next);
    this.syncUrl(next);
  }

  /** chips for active filters with human labels */
  chips = computed(() => {
    const out: { key: string; label: string }[] = [];
    for (const def of this.defs()) {
      const value = this.values()[def.key];
      if (!isFilterActive(value)) {
        continue;
      }
      out.push({ key: def.key, label: `${this.translate.instant(def.label)}: ${this.describe(def, value)}` });
    }
    return out;
  });

  private describe(def: TableFilterDef, value: TableFilterValue | undefined): string {
    if (value === undefined || value === null || value === '') {
      return '';
    }
    if (def.type === 'select' || def.type === 'lookup') {
      const opt = (def.options ?? this.lookupOptions()[def.key] ?? []).find((o) => String(o.value) === String(value));
      return opt?.label ?? String(value);
    }
    if (Array.isArray(value)) {
      return value.map((v) => String(v)).join(this.translate.instant('common.list_separator'));
    }
    if (value && typeof value === 'object') {
      return Object.entries(value as Record<string, unknown>)
        .filter(([, v]) => v !== null && v !== undefined && v !== '')
        .map(([k, v]) => `${this.translate.instant(k === 'min' || k === 'from' ? 'common.from' : 'common.to')}: ${String(v)}`)
        .join(' ');
    }
    return String(value);
  }

  /** mirror active filters into ?filter[key]=… so links and refresh work (T6-30) */
  private syncUrl(values: Record<string, TableFilterValue | undefined>): void {
    const params: Record<string, string> = {};
    for (const def of this.defs()) {
      const value = values[def.key];
      if (!isFilterActive(value)) {
        continue;
      }
      if (value && typeof value === 'object' && !Array.isArray(value)) {
        for (const [part, v] of Object.entries(value as Record<string, unknown>)) {
          if (v !== null && v !== undefined && v !== '') {
            params[`filter[${def.key}][${part}]`] = String(v);
          }
        }
      } else {
        params[`filter[${def.key}]`] = Array.isArray(value) ? value.join(',') : String(value);
      }
    }
    void this.router.navigate([], { queryParams: params, queryParamsHandling: 'merge', replaceUrl: true });
  }
}
