import { ChangeDetectionStrategy, Component, type ElementRef, computed, effect, inject, input, output, signal, viewChild } from '@angular/core';
import { Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { DialogModule } from 'primeng/dialog';
import { InputTextModule } from 'primeng/inputtext';
import { firstValueFrom } from 'rxjs';
import { NAV_GROUPS } from '../shell/nav-items';
import { AuthService } from '@core/auth/auth.service';
import { LocalStorageService } from '@core/storage/local-storage.service';
import { iconHref } from '@shared/ui/icon/icon-symbol';
import {
  SEARCH_DEBOUNCE_MS,
  answerIsFor,
  moveActive,
  needsRecordSearch,
  paletteItems,
  sectionStart,
  splitMatch,
  type GroupInfo,
  type PaletteItem,
  type PageEntry,
  type SearchAnswer,
  type SearchGroupKey,
} from '@shared/lib/command-palette';

const LIST_ID = 'command-palette-list';
const MAX_RECENTS = 6;
const MAX_SUGGESTED_PAGES = 8;
const MAX_MATCHING_PAGES = 5;
/** the dialog's leave animation, and a little — after it the focus can go back */
const CLOSE_FOCUS_DELAY_MS = 300;

type Heading = 'pages' | SearchGroupKey;

/** the headings' translation keys — written out literally so check-i18n sees every one of them */
const HEADING_KEYS: Record<Heading, string> = {
  pages: 'command.pages',
  custodies: 'command.groups.custodies',
  expenses: 'command.groups.expenses',
  social_cases: 'command.groups.social_cases',
  users: 'command.groups.users',
};

/**
 * Command palette (plan 21.2 + N-01): Ctrl+K jumps to any page the user may
 * open, with a "recently opened" list remembered locally — and, from two
 * characters on, searches the records themselves: custodies, expenses,
 * social cases and users, each under the rule of its own list (the server
 * scopes them: GET /api/v1/search). One list for the arrow keys: pages,
 * then each group's records and its "see all results", which opens the
 * group's list page with `?q=`.
 *
 * The decisions that need neither Angular nor the network live in
 * `shared/lib/command-palette.ts`.
 */
@Component({
  selector: 'app-command-palette',
  standalone: true,
  imports: [TranslatePipe, DialogModule, FormsModule, InputTextModule],
  template: `
    <p-dialog appendTo="body"
      [visible]="open()"
      (visibleChange)="onVisible($event)"
      [header]="'command.title' | translate"
      [modal]="true"
      [draggable]="false"
      [resizable]="false"
      [dismissableMask]="true"
      [closeOnEscape]="true"
      [style]="{ width: 'min(560px, 94vw)' }"
    >
      <input
        #paletteInput
        type="search"
        pInputText
        class="command-palette__input"
        role="combobox"
        [attr.aria-expanded]="true"
        [attr.aria-controls]="LIST_ID"
        [attr.aria-autocomplete]="'list'"
        [attr.aria-activedescendant]="items().length > 0 ? activeDomId() : null"
        [attr.aria-label]="'command.placeholder' | translate"
        [ngModel]="term()"
        (ngModelChange)="onTerm($event)"
        (keydown)="onKeydown($event)"
        [placeholder]="'command.placeholder' | translate"
        autocomplete="off"
      />
      <div class="command-palette__results u-mt-2">
        @if (term().trim() !== '' && !needsRecordSearch(term())) {
          <p class="command-palette__hint">{{ 'command.keep_typing' | translate }}</p>
        }
        <ul id="command-palette-list" role="listbox" [attr.aria-label]="'command.title' | translate">
          @for (item of items(); track $index; let i = $index) {
            @if (sectionStart(items(), i) !== '') {
              <li class="command-palette__group" role="presentation">{{ headingOf(i) | translate }}</li>
            }
            <li
              role="option"
              [id]="itemId(i)"
              [attr.aria-selected]="i === active()"
              class="command-palette__item"
              [class.command-palette__item--active]="i === active()"
              [class.command-palette__item--all]="item.kind === 'all'"
              (click)="activate(item)"
              (mouseenter)="active.set(i)"
            >
              @switch (item.kind) {
                @case ('page') {
                  <svg width="16" height="16" aria-hidden="true">
                    <use [attr.href]="iconHref(item.entry.icon)" />
                  </svg>
                  <span>{{ item.entry.label | translate }}</span>
                }
                @case ('hit') {
                  <svg width="16" height="16" aria-hidden="true">
                    <use [attr.href]="iconHref(item.group.icon)" />
                  </svg>
                  <span class="command-palette__record">
                    <span class="command-palette__record-title">
                      @if (splitMatch(item.hit.title, term()); as parts) {
                        {{ parts.before }}<mark>{{ parts.match }}</mark>{{ parts.after }}
                      } @else {
                        {{ item.hit.title }}
                      }
                    </span>
                    <span class="command-palette__record-sub">
                      @if (item.hit.subtitle) {
                        {{ item.hit.subtitle }} ·
                      }
                      #{{ item.hit.id }}
                    </span>
                  </span>
                }
                @case ('all') {
                  <span class="command-palette__all">{{ 'command.see_all' | translate: { group: headingKey(item.group.key) | translate } }}</span>
                }
              }
            </li>
          } @empty {
            <li class="u-muted command-palette__empty" role="presentation">
              @if (searching()) {
                {{ 'command.searching' | translate }}
              } @else {
                {{ 'command.no_results' | translate }}
              }
            </li>
          }
        </ul>
      </div>
    </p-dialog>
  `,
  styles: `
    .command-palette__empty {
      padding: var(--space-3);
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CommandPaletteComponent {
  readonly iconHref = iconHref;
  /** exposed to the template — templates see class members, not module imports */
  readonly LIST_ID = LIST_ID;
  readonly sectionStart = sectionStart;
  readonly splitMatch = splitMatch;
  readonly needsRecordSearch = needsRecordSearch;

  private router = inject(Router);
  private auth = inject(AuthService);
  private storage = inject(LocalStorageService);
  private http = inject(HttpClient);
  private translate = inject(TranslateService);

  private readonly input = viewChild<ElementRef<HTMLInputElement>>('paletteInput');

  open = input(false);
  closed = output();

  readonly term = signal('');
  readonly recent = signal<string[]>(this.storage.get<string[]>('command.recent', []).filter((route) => typeof route === 'string'));

  /** the server's answer for the term on screen; null: nothing was asked for it */
  private readonly answer = signal<SearchAnswer | null>(null);
  readonly searching = signal(false);
  /** the item the arrow keys are on */
  readonly active = signal(0);

  private debounce: ReturnType<typeof setTimeout> | null = null;
  /** only the newest request's answer is shown */
  private requestNo = 0;
  /** what had the focus when the palette opened (the search box's trigger, usually): it gets it back */
  private focusedBefore: HTMLElement | null = null;

  private allEntries = computed<PageEntry[]>(() => {
    const abilities = this.auth.abilities();
    if (!abilities) {
      return [];
    }
    const entries: PageEntry[] = [];
    for (const group of NAV_GROUPS) {
      for (const item of group.items) {
        if (item.route && (!item.requires || this.auth.can(item.requires))) {
          // the name as the user reads it: it is what a page is matched against
          entries.push({ label: item.label, text: this.translate.instant(item.label), route: item.route, icon: item.icon });
        }
      }
    }
    return entries;
  });

  /** with nothing typed: the pages opened last; with a term: every page, to match against it */
  private pages = computed<PageEntry[]>(() => {
    const entries = this.allEntries();
    if (this.term().trim() !== '') {
      return entries;
    }
    const recents = this.recent()
      .map((route) => entries.find((entry) => entry.route === route))
      .filter((entry): entry is PageEntry => entry !== undefined)
      .map((entry) => ({ ...entry, recent: true }));
    return recents.length > 0 ? recents.slice(0, MAX_RECENTS) : entries.slice(0, MAX_SUGGESTED_PAGES);
  });

  /**
   * The groups of records this user may search — the server's own rule, so
   * nothing is asked for that would come back empty by rule — and where
   * "see all results" takes each of them: the management list or "my" list.
   */
  readonly groups = computed<GroupInfo[]>(() => {
    const can = this.auth.can.bind(this.auth);
    const groups: GroupInfo[] = [];
    if (can('may_manage_treasury') || can('is_agent')) {
      groups.push({ key: 'custodies', label: HEADING_KEYS.custodies, icon: 'wallet', list: can('may_manage_treasury') ? '/custodies' : '/custodies/mine', detail: '/custodies' });
    }
    groups.push({ key: 'expenses', label: HEADING_KEYS.expenses, icon: 'receipt', list: can('may_view_all_expenses') ? '/expenses' : '/expenses/mine', detail: '/expenses' });
    if (can('is_manager') || can('is_accountant') || can('is_researcher')) {
      groups.push({ key: 'social_cases', label: HEADING_KEYS.social_cases, icon: 'heart-handshake', list: can('is_manager') || can('is_accountant') ? '/social-cases' : '/social-cases/mine', detail: '/social-cases' });
    }
    if (can('may_manage_users')) {
      groups.push({ key: 'users', label: HEADING_KEYS.users, icon: 'users', list: '/users', detail: '/users' });
    }
    return groups;
  });

  /** everything on screen, in the order the arrow keys walk down it */
  readonly items = computed<PaletteItem[]>(() => paletteItems({ term: this.term(), pages: this.pages(), groups: this.groups(), hits: this.answer()?.data ?? null }, MAX_MATCHING_PAGES));

  constructor() {
    effect(() => {
      if (this.open()) {
        this.term.set('');
        this.active.set(0);
        this.answer.set(null);
        this.searching.set(false);
        // the input is created with the dialog: the focus goes to it as soon as it exists
        this.focusedBefore = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        setTimeout(() => this.input()?.nativeElement.focus());
      } else {
        this.clearPending();
        this.requestNo++;
        // once the dialog has gone (its leave animation), the focus goes back to where the palette
        // was opened from — unless the user already put it somewhere else
        const target = this.focusedBefore;
        setTimeout(() => {
          if (target !== null && target.isConnected && document.activeElement === document.body) {
            target.focus();
          }
        }, CLOSE_FOCUS_DELAY_MS);
      }
    });
    // the list can shrink under the cursor (a character typed, an answer arriving)
    effect(() => {
      const length = this.items().length;
      if (this.active() > Math.max(0, length - 1)) {
        this.active.set(Math.max(0, length - 1));
      }
    });
  }

  onVisible(next: boolean): void {
    if (!next) {
      this.closed.emit();
    }
  }

  onTerm(value: string): void {
    this.term.set(value);
    this.active.set(0);
    this.clearPending();
    if (!needsRecordSearch(value)) {
      // too short to ask: the pages alone
      this.requestNo++;
      this.answer.set(null);
      this.searching.set(false);
      return;
    }
    this.searching.set(true);
    const q = value.trim();
    this.debounce = setTimeout(() => { void this.search(q); }, SEARCH_DEBOUNCE_MS);
  }

  private async search(q: string): Promise<void> {
    const request = ++this.requestNo;
    try {
      const answer = await firstValueFrom(this.http.get<SearchAnswer>('/search', { params: { q } }));
      // a slower answer to an earlier text is dropped
      if (request === this.requestNo && answerIsFor(answer, q)) {
        this.answer.set(answer);
      }
    } catch {
      if (request === this.requestNo) {
        this.answer.set(null);
      }
    } finally {
      if (request === this.requestNo) {
        this.searching.set(false);
      }
    }
  }

  private clearPending(): void {
    if (this.debounce !== null) {
      clearTimeout(this.debounce);
      this.debounce = null;
    }
  }

  // ── the keyboard ──────────────────────────────────────────────────────────

  onKeydown(event: KeyboardEvent): void {
    const length = this.items().length;
    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault();
        this.active.set(moveActive(this.active(), 1, length));
        this.scrollActiveIntoView();
        break;
      case 'ArrowUp':
        event.preventDefault();
        this.active.set(moveActive(this.active(), -1, length));
        this.scrollActiveIntoView();
        break;
      case 'Home':
        if (length > 0) {
          event.preventDefault();
          this.active.set(0);
        }
        break;
      case 'End':
        if (length > 0) {
          event.preventDefault();
          this.active.set(length - 1);
        }
        break;
      case 'Enter': {
        event.preventDefault();
        if (this.active() < length) {
          this.activate(this.items()[this.active()]);
        }
        break;
      }
      default:
        break;
    }
  }

  private scrollActiveIntoView(): void {
    document.getElementById(this.activeDomId())?.scrollIntoView({ block: 'nearest' });
  }

  itemId(index: number): string {
    return `${LIST_ID}-${index}`;
  }

  activeDomId(): string {
    return this.itemId(this.active());
  }

  headingKey(key: Heading): string {
    return HEADING_KEYS[key];
  }

  /** the heading above the item at `index`, when it starts a section */
  headingOf(index: number): string {
    const start = sectionStart(this.items(), index);
    // nothing typed: the pages on screen are the ones opened last
    if (start === 'pages' && this.term().trim() === '') {
      return this.recent().length > 0 ? 'command.recent' : 'command.pages';
    }
    return start === '' ? '' : this.headingKey(start);
  }

  // ── choosing ──────────────────────────────────────────────────────────────

  activate(item: PaletteItem): void {
    if (item.kind === 'page') {
      this.remember(item.entry.route);
      this.navigate(item.entry.route);
      return;
    }
    if (item.kind === 'hit') {
      this.navigate(`${item.group.detail}/${item.hit.id}`);
      return;
    }
    this.navigate(`${item.group.list}?q=${encodeURIComponent(this.term().trim())}`);
  }

  private navigate(route: string): void {
    this.closed.emit();
    void this.router.navigateByUrl(route);
  }

  private remember(route: string): void {
    const recents = [route, ...this.recent().filter((other) => other !== route)].slice(0, 5);
    this.recent.set(recents);
    this.storage.set('command.recent', recents);
  }
}
