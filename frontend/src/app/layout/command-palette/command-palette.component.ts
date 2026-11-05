import { ChangeDetectionStrategy, Component, computed, inject, input, output, signal } from '@angular/core';
import { Router } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';
import { DialogModule } from 'primeng/dialog';
import { FormsModule } from '@angular/forms';
import { NAV_GROUPS } from '../shell/nav-items';
import { AuthService } from '@core/auth/auth.service';
import { LocalStorageService } from '@core/storage/local-storage.service';

interface PaletteEntry {
  label: string;
  route: string;
  icon: string;
  recent?: boolean;
}

/**
 * Command palette (plan 21.2): Ctrl+K jumps to any page the user may open,
 * plus a "recently opened" list remembered locally.
 */
@Component({
  selector: 'app-command-palette',
  standalone: true,
  imports: [TranslatePipe, DialogModule, FormsModule],
  template: `
    <p-dialog appendTo="body"
      [visible]="open()"
      (visibleChange)="closed.emit()"
      [header]="'command.title' | translate"
      [modal]="true"
      [draggable]="false"
      [resizable]="false"
      [dismissableMask]="true"
      [closeOnEscape]="true"
      [style]="{ width: 'min(520px, 94vw)' }"
    >
      <input
        type="text"
        pInputText
        class="command-palette__input"
        [(ngModel)]="term" name="term"
        [placeholder]="'command.placeholder' | translate"
        [attr.aria-label]="'command.placeholder' | translate"
        autocomplete="off"
      />
      <div class="command-palette__results u-mt-2">
        @if (term().length === 0 && recent().length > 0) {
          <div class="command-palette__group">{{ 'command.recent' | translate }}</div>
        }
        @for (entry of results(); track entry.route) {
          <button
            type="button"
            class="command-palette__item"
            (click)="go(entry)"
            [class.command-palette__item--active]="entry.recent"
          >
            <svg width="16" height="16" aria-hidden="true">
              <use [attr.href]="'assets/icons/sprite.svg#tabler-' + entry.icon" />
            </svg>
            {{ entry.label | translate }}
          </button>
        } @empty {
          <p class="u-muted command-palette__empty">{{ 'command.no_results' | translate }}</p>
        }
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
  private router = inject(Router);
  private auth = inject(AuthService);
  private storage = inject(LocalStorageService);

  open = input(false);
  closed = output();

  readonly term = signal('');
  readonly recent = signal<string[]>(this.storage.get('command.recent', []));

  private allEntries = computed<PaletteEntry[]>(() => {
    const abilities = this.auth.abilities();
    if (!abilities) {
      return [];
    }
    const entries: PaletteEntry[] = [];
    for (const group of NAV_GROUPS) {
      for (const item of group.items) {
        if (item.route && (!item.requires || this.auth.can(item.requires))) {
          entries.push({ label: item.label, route: item.route, icon: item.icon });
        }
      }
    }
    return entries;
  });

  results = computed<PaletteEntry[]>(() => {
    const term = this.term().trim();
    if (term.length === 0) {
      const recents = this.recent()
        .map((route) => this.allEntries().find((entry) => entry.route === route))
        .filter((entry): entry is PaletteEntry => entry !== undefined)
        .map((entry) => ({ ...entry, recent: true }));
      return recents.length > 0 ? recents.slice(0, 6) : this.allEntries().slice(0, 8);
    }
    return this.allEntries()
      .filter((entry) => entry.label.includes(term) || entry.route.includes(term))
      .slice(0, 10);
  });

  go(entry: PaletteEntry): void {
    const recents = [entry.route, ...this.recent().filter((route) => route !== entry.route)].slice(0, 5);
    this.recent.set(recents);
    this.storage.set('command.recent', recents);
    this.closed.emit();
    void this.router.navigateByUrl(entry.route);
  }
}
