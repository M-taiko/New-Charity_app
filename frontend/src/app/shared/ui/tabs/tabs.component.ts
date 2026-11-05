import type { OnInit } from '@angular/core';
import { ChangeDetectionStrategy, Component, computed, effect, inject, input, model, output } from '@angular/core';
import { Router } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';

/** One tab of an app-tabs bar (T6-11). */
export interface AppTab {
  /** stable id — becomes ?tab=<id> so links and refresh keep the tab */
  readonly id: string;
  /** i18n key */
  readonly label: string;
  /** optional badge/count shown next to the label */
  readonly count?: number | null;
  readonly disabled?: boolean;
  /** cross-page tab (T6-01): clicking navigates instead of switching panels */
  readonly route?: string;
}

/**
 * The one tabs component (T6-11): accessible tablist with arrow-key
 * navigation, count badges and an ink indicator; the active tab is mirrored
 * into ?tab= so a link or a refresh reproduces the view, and restored from
 * the URL on load. Pages render only the active panel — one table at a time.
 *
 * Usage: `<app-tabs id="mine" [tabs]="tabs" [(active)]="tab" />`
 */
@Component({
  selector: 'app-tabs',
  standalone: true,
  imports: [TranslatePipe],
  template: `
    <div class="app-tabs" role="tablist" (keydown)="onKeydown($event)">
      @for (t of tabs(); track t.id) {
        <button
          type="button"
          class="app-tabs__tab"
          role="tab"
          [id]="'app-tab-' + id() + '-' + t.id"
          [attr.aria-selected]="active() === t.id"
          [attr.aria-controls]="'app-panel-' + id() + '-' + t.id"
          [tabindex]="active() === t.id ? 0 : -1"
          [class.app-tabs__tab--active]="active() === t.id"
          [disabled]="t.disabled"
          (click)="t.route ? navigate(t.route) : select(t.id)"
        >
          <span class="app-tabs__label">{{ t.label | translate }}</span>
          @if (t.count !== undefined && t.count !== null) {
            <span class="app-tabs__badge" [class.app-tabs__badge--zero]="t.count === 0">{{ t.count }}</span>
          }
        </button>
      }
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AppTabsComponent implements OnInit {
  /** unique name of this tablist on the page (a11y ids, ?tab sync) */
  id = input.required<string>();
  tabs = input.required<AppTab[]>();
  /** sync the active tab into ?tab= (default true) */
  syncUrl = input(true);

  active = model<string>('');
  tabChange = output<string>();

  private router = inject(Router);

  constructor() {
    // mirror changes into the URL
    effect(() => {
      const active = this.active();
      if (!active || !this.syncUrl()) {
        return;
      }
      void this.router.navigate([], {
        queryParams: { tab: active },
        queryParamsHandling: 'merge',
        replaceUrl: true,
      });
    });
  }

  ngOnInit(): void {
    // restore ?tab= once on load — overrides the page's default with the
    // shared URL state so links and refresh land on the same tab
    if (!this.syncUrl()) {
      return;
    }
    const params = this.router.parseUrl(this.router.url).queryParams;
    const fromUrl = typeof params.tab === 'string' ? params.tab : null;
    if (fromUrl && fromUrl !== this.active() && this.tabs().some((t) => t.id === fromUrl)) {
      this.active.set(fromUrl);
    }
  }

  selectableIndexes = computed(() =>
    this.tabs().map((t, i) => (t.disabled ? -1 : i)).filter((i) => i >= 0),
  );

  /** cross-page tabs navigate (T6-01) */
  navigate(route: string): void {
    void this.router.navigateByUrl(route);
  }

  select(tabId: string): void {
    if (this.active() === tabId) {
      return;
    }
    this.active.set(tabId);
    this.tabChange.emit(tabId);
  }

  /** arrow-key navigation per the WAI-ARIA tabs pattern */
  onKeydown(event: KeyboardEvent): void {
    const keys = ['ArrowRight', 'ArrowLeft', 'Home', 'End'];
    if (!keys.includes(event.key)) {
      return;
    }
    event.preventDefault();
    const tabs = this.tabs();
    const selectable = this.selectableIndexes();
    if (selectable.length === 0) {
      return;
    }
    const current = tabs.findIndex((t) => t.id === this.active());
    let next: number;
    if (event.key === 'Home') {
      next = selectable[0];
    } else if (event.key === 'End') {
      next = selectable[selectable.length - 1];
    } else {
      const dir = event.key === 'ArrowRight' ? 1 : -1;
      const pos = selectable.indexOf(current >= 0 ? current : selectable[0]);
      const wrapped = ((pos < 0 ? 0 : pos) + dir + selectable.length) % selectable.length;
      next = selectable[wrapped];
    }
    document.getElementById(`app-tab-${this.id()}-${tabs[next].id}`)?.focus();
    this.select(tabs[next].id);
  }
}
