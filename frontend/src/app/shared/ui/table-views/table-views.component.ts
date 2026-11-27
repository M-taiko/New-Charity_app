import { ChangeDetectionStrategy, Component, type ElementRef, computed, inject, input, output, signal, viewChild } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { IconComponent } from '@shared/ui/icon/icon.component';
import { activeView, nameProblem, type NameProblem, type SavedViews, type ViewSnapshot } from '@shared/lib/saved-views';

/** a column a view may hide */
export interface ViewColumn {
  readonly field: string;
  /** i18n key */
  readonly header: string;
}

/**
 * Saved views (N-02) — the table toolbar's "العروض المحفوظة" control: a
 * button opening a panel (save the table as it is, apply, rename, set the
 * default, delete, and the visible columns), and the chip of the view on
 * screen, which says "معدَّل" once the table moved away from it.
 *
 * Rendered by app-data-table — never by a page. It draws and validates; the
 * table keeps the views and performs the actions.
 */
@Component({
  selector: 'app-table-views',
  standalone: true,
  host: {
    // Escape closes the open panel wherever focus is (a click on Save leaves the button disabled, and the focus nowhere)
    '(document:keydown.escape)': 'onEscape($event)',
  },
  imports: [TranslatePipe, FormsModule, ButtonModule, InputTextModule, IconComponent],
  template: `
    <div class="table-views">
      <!-- a native button with PrimeNG's button directive (not the p-button component):
           its expanded state belongs on the button assistive technology reads -->
      <span #toggleWrap class="table-views__toggle">
      <button pButton type="button" severity="secondary" [outlined]="true" size="small"
              [attr.aria-expanded]="panelOpen()" [attr.aria-haspopup]="'dialog'"
              (click)="panelOpen() ? close() : open()">
        <app-icon pButtonIcon name="star" [size]="16" />
        <span pButtonLabel class="table-views__label">{{ 'table.views.button' | translate }}</span>
      </button>
      </span>

      @if (active(); as on) {
        <span class="table-views__chip" [attr.data-edited]="on.edited ? 'true' : null">
          <app-icon name="star" [size]="12" />
          <span class="table-views__chip-name">{{ on.view.name }}</span>
          @if (on.edited) {
            <span class="table-views__edited">{{ 'table.views.edited' | translate }}</span>
          }
          <button type="button" class="table-views__leave" (click)="leave.emit()"
                  [attr.aria-label]="'table.views.leave' | translate: { name: on.view.name }"
                  [title]="'table.views.leave' | translate: { name: on.view.name }">
            <app-icon name="x" [size]="12" />
          </button>
        </span>
      }

      @if (panelOpen()) {
        <div class="table-views__panel" role="dialog" [attr.aria-label]="'table.views.title' | translate">
          <!-- save what is on screen -->
          <form class="table-views__form" (submit)="$event.preventDefault(); submitSave()">
            <label class="table-views__field">
              <span class="table-views__field-label">{{ 'table.views.save_current' | translate }}</span>
              <input #nameInput pInputText type="text" name="view-name" [ngModel]="name()" (ngModelChange)="name.set($event)"
                     [placeholder]="'table.views.name_placeholder' | translate" [maxlength]="40" [attr.autocomplete]="'off'" />
            </label>
            <p-button type="submit" [label]="'table.views.save' | translate" size="small" [disabled]="saveProblem() !== null" />
          </form>
          @if (saveMessage(); as message) {
            <p class="table-views__note" role="status">{{ message }}</p>
          }

          <!-- the saved views -->
          @if (views().views.length === 0) {
            <p class="u-small u-muted">{{ 'table.views.empty' | translate }}</p>
          }
          <ul class="table-views__list">
            @for (view of views().views; track view.id) {
              <li class="table-views__item" [class.table-views__item--active]="view.id === activeId()">
                @if (renaming() === view.id) {
                  <form class="table-views__form" (submit)="$event.preventDefault(); submitRename(view.id)">
                    <input pInputText type="text" name="rename-view" [ngModel]="newName()" (ngModelChange)="newName.set($event)"
                           [maxlength]="40" [attr.aria-label]="'table.views.rename' | translate" />
                    <p-button type="submit" [label]="'common.save' | translate" size="small" [disabled]="renameMessage(view.id) !== null" />
                    <p-button [label]="'common.cancel' | translate" size="small" severity="secondary" [text]="true" (onClick)="renaming.set(null)" />
                  </form>
                  @if (renameMessage(view.id); as message) {
                    <p class="table-views__note" role="status">{{ message }}</p>
                  }
                } @else {
                  <button type="button" class="table-views__apply" (click)="apply.emit(view.id)"
                          [attr.aria-current]="view.id === activeId() ? 'true' : null">
                    <span class="table-views__apply-name">{{ view.name }}</span>
                    @if (view.id === views().defaultId) {
                      <span class="table-views__default">{{ 'table.views.default_badge' | translate }}</span>
                    }
                  </button>
                  <span class="table-views__actions">
                    <button type="button" class="topbar__icon-btn"
                            [attr.aria-label]="(view.id === views().defaultId ? 'table.views.unset_default' : 'table.views.set_default') | translate: { name: view.name }"
                            [attr.aria-pressed]="view.id === views().defaultId"
                            (click)="setDefault.emit(view.id === views().defaultId ? null : view.id)">
                      <app-icon name="pin" [size]="16" />
                    </button>
                    <button type="button" class="topbar__icon-btn"
                            [attr.aria-label]="'table.views.rename' | translate: { name: view.name }"
                            (click)="startRename(view)">
                      <app-icon name="pencil" [size]="16" />
                    </button>
                    <button type="button" class="topbar__icon-btn"
                            [attr.aria-label]="'table.views.delete' | translate: { name: view.name }"
                            (click)="confirmingDelete.set(view.id)">
                      <app-icon name="trash" [size]="16" />
                    </button>
                  </span>
                }
                @if (confirmingDelete() === view.id) {
                  <span class="table-views__confirm" role="group" [attr.aria-label]="'table.views.confirm_delete' | translate">
                    <span>{{ 'table.views.confirm_delete' | translate: { name: view.name } }}</span>
                    <p-button [label]="'common.delete' | translate" size="small" severity="danger" (onClick)="remove.emit(view.id)" />
                    <p-button [label]="'common.cancel' | translate" size="small" severity="secondary" [text]="true" (onClick)="confirmingDelete.set(null)" />
                  </span>
                }
              </li>
            }
          </ul>

          <!-- what the table shows -->
          @if (columns().length > 0) {
            <fieldset class="table-views__columns">
              <legend class="table-views__field-label">{{ 'table.views.columns' | translate }}</legend>
              @for (column of columns(); track column.field) {
                <label class="table-views__column">
                  <input type="checkbox" [checked]="!hiddenColumns().has(column.field)"
                         [disabled]="!hiddenColumns().has(column.field) && visibleCount() === 1"
                         (change)="toggleColumn(column.field, $event)" />
                  <span>{{ column.header | translate }}</span>
                </label>
              }
            </fieldset>
            <p class="u-small u-muted">{{ 'table.views.columns_hint' | translate }}</p>
          }
        </div>
      }
    </div>
  `,
  styles: `
    .table-views {
      position: relative;
      display: flex;
      align-items: center;
      gap: var(--space-2);
      flex-wrap: wrap;
      min-width: 0;
    }
    .table-views__label {
      font-size: var(--text-xs);
    }
    .table-views__toggle {
      display: inline-flex;
    }
    .table-views__chip {
      display: inline-flex;
      align-items: center;
      gap: var(--space-1);
      min-width: 0;
      max-width: 100%;
      border: 1px solid var(--color-primary);
      border-radius: var(--radius-full);
      background: var(--color-primary-soft);
      color: var(--color-on-primary-soft);
      font-size: var(--text-2xs);
      font-weight: 600;
      padding: 2px var(--space-1) 2px var(--space-2);
    }
    .table-views__chip-name {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      max-inline-size: 180px;
    }
    .table-views__edited {
      border: 1px dashed var(--color-on-primary-soft);
      border-radius: var(--radius-s);
      padding: 0 var(--space-1);
      white-space: nowrap;
    }
    .table-views__leave {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      inline-size: 22px;
      block-size: 22px;
      flex-shrink: 0;
      border: 0;
      border-radius: var(--radius-full);
      background: transparent;
      color: inherit;
      cursor: pointer;
    }
    .table-views__leave:hover {
      background: var(--color-primary);
      color: var(--color-on-primary);
    }
    .table-views__leave:focus-visible {
      outline: 2px solid var(--color-focus);
      outline-offset: 1px;
    }
    .table-views__panel {
      position: absolute;
      inset-block-start: calc(100% + var(--space-2));
      inset-inline-start: 0;
      z-index: 60;
      display: flex;
      flex-direction: column;
      gap: var(--space-3);
      inline-size: min(380px, 92vw);
      max-block-size: min(70vh, 560px);
      overflow-y: auto;
      padding: var(--space-3);
      background: var(--color-surface);
      border: 1px solid var(--color-hairline);
      border-radius: var(--radius-m);
      box-shadow: 2px 2px 0 var(--color-hairline);
      text-align: start;
    }
    .table-views__form {
      display: flex;
      flex-direction: column;
      align-items: stretch;
      gap: var(--space-2);
    }
    .table-views__form > p-button {
      align-self: flex-start;
    }
    .table-views__form > input {
      flex: 1 1 160px;
      min-width: 0;
    }
    .table-views__field {
      display: flex;
      flex-direction: column;
      gap: var(--space-1);
      min-width: 0;
    }
    .table-views__field-label {
      color: var(--color-text-muted);
      font-size: var(--text-xs);
      font-weight: 600;
    }
    .table-views__note {
      color: var(--color-danger);
      font-size: var(--text-xs);
    }
    .table-views__list {
      list-style: none;
      margin: 0;
      padding: 0;
      display: flex;
      flex-direction: column;
      gap: var(--space-1);
    }
    .table-views__item {
      display: flex;
      align-items: center;
      gap: var(--space-1);
      flex-wrap: wrap;
      padding: var(--space-1) var(--space-2);
      border: 1px solid var(--color-hairline);
      border-radius: var(--radius-m);
    }
    .table-views__item--active {
      border-color: var(--color-primary);
      background: var(--color-primary-soft);
    }
    .table-views__apply {
      display: flex;
      align-items: center;
      gap: var(--space-1);
      flex: 1 1 auto;
      min-width: 0;
      padding: var(--space-1) 0;
      border: 0;
      background: transparent;
      color: inherit;
      font: inherit;
      font-weight: 600;
      text-align: start;
      cursor: pointer;
    }
    .table-views__apply:hover {
      text-decoration: underline;
    }
    .table-views__apply:focus-visible {
      outline: 2px solid var(--color-focus);
      outline-offset: 2px;
      border-radius: var(--radius-s);
    }
    .table-views__apply-name {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .table-views__default {
      flex-shrink: 0;
      border: 1px solid var(--color-hairline);
      border-radius: var(--radius-s);
      padding: 0 var(--space-1);
      color: var(--color-text-muted);
      font-size: var(--text-2xs);
      font-weight: 600;
      white-space: nowrap;
    }
    .table-views__actions {
      display: flex;
      align-items: center;
      gap: var(--space-1);
      flex-shrink: 0;
    }
    .table-views__actions .topbar__icon-btn[aria-pressed='true'] {
      color: var(--color-primary);
      background: var(--color-primary-soft);
    }
    .table-views__confirm {
      flex-basis: 100%;
      display: flex;
      align-items: center;
      gap: var(--space-2);
      flex-wrap: wrap;
    }
    .table-views__columns {
      display: flex;
      flex-wrap: wrap;
      gap: var(--space-1) var(--space-3);
      margin: 0;
      padding: var(--space-2);
      border: 1px solid var(--color-hairline);
      border-radius: var(--radius-m);
    }
    .table-views__columns legend {
      padding: 0 var(--space-1);
    }
    .table-views__column {
      display: inline-flex;
      align-items: center;
      gap: var(--space-1);
      font-size: var(--text-xs);
      cursor: pointer;
    }
    /* a phone: the panel is a sheet above the content, never wider than the screen —
       above the page's floating action button (940), below dialogs and toasts */
    @media (max-width: 767px) {
      .table-views__panel {
        position: fixed;
        inset-inline: var(--space-3);
        inset-block-start: auto;
        inset-block-end: var(--space-3);
        inline-size: auto;
        max-block-size: 80vh;
        z-index: 1000;
      }
      .table-views__chip-name {
        max-inline-size: 120px;
      }
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TableViewsComponent {
  views = input.required<SavedViews>();
  /** the table as it is now */
  current = input.required<ViewSnapshot>();
  /** the columns a view may hide (pinned ones stay) */
  columns = input<readonly ViewColumn[]>([]);

  apply = output<string>();
  /** the panel was opened — the table closes the filters panel; the two never lie on top of each other */
  opened = output();
  saveAs = output<string>();
  renameTo = output<{ id: string; name: string }>();
  remove = output<string>();
  /** null = no default view */
  setDefault = output<string | null>();
  /** the chip's close: the view is no longer the one on screen; the table stays as it is */
  leave = output();
  columnVisibility = output<{ field: string; visible: boolean }>();

  panelOpen = signal(false);
  name = signal('');
  renaming = signal<string | null>(null);
  newName = signal('');
  confirmingDelete = signal<string | null>(null);

  private translate = inject(TranslateService);
  /** the wrapper of the toggle — the element the focus returns to when the panel closes */
  private readonly toggleWrap = viewChild<ElementRef<HTMLElement>>('toggleWrap');
  private readonly nameInput = viewChild<ElementRef<HTMLInputElement>>('nameInput');

  /** the view on the chip, and whether the table moved away from it */
  readonly active = computed(() => activeView(this.views(), this.current()));

  /** the id of the view on the chip, or null */
  readonly activeId = computed(() => this.active()?.view.id ?? null);

  readonly hiddenColumns = computed(() => new Set(this.current().hiddenColumns));

  /** how many columns are on screen — the last one cannot be hidden */
  readonly visibleCount = computed(() => Math.max(0, this.columns().length - [...this.hiddenColumns()].filter((field) => this.columns().some((column) => column.field === field)).length));

  /** why the name in the box cannot be saved; null = it can */
  readonly saveProblem = computed<NameProblem | null>(() => (this.name().trim() === '' ? 'empty' : nameProblem(this.views(), this.name())));

  /** the message under the box when the name is refused (an empty box says nothing) */
  readonly saveMessage = computed<string | null>(() => this.messageFor(this.saveProblem()));

  open(): void {
    this.name.set('');
    this.renaming.set(null);
    this.confirmingDelete.set(null);
    this.panelOpen.set(true);
    this.opened.emit();
    // the panel takes the focus: its first field
    setTimeout(() => { this.nameInput()?.nativeElement.focus(); });
  }

  /**
   * `returnFocus` is false when the table closes this panel because the
   * filters panel is opening — the focus belongs where the user just clicked.
   */
  close(returnFocus = true): void {
    this.panelOpen.set(false);
    if (returnFocus) {
      // focus goes back to the control that opened the panel
      this.toggleWrap()?.nativeElement.querySelector<HTMLElement>('button')?.focus();
    }
  }

  /** the panel is closed by Escape from anywhere on the page; nothing else listens for it while the panel is open */
  onEscape(event: KeyboardEvent): void {
    if (!this.panelOpen()) {
      return;
    }
    event.preventDefault();
    event.stopPropagation();
    this.close();
  }

  private messageFor(problem: NameProblem | null): string | null {
    // keys written out literally so check-i18n sees every one of them
    if (problem === null || problem === 'empty') {
      return null;
    }
    return problem === 'taken' ? this.translate.instant('table.views.name_taken') : this.translate.instant('table.views.name_limit');
  }

  submitSave(): void {
    if (this.saveProblem() !== null) {
      return;
    }
    this.saveAs.emit(this.name().trim());
    this.name.set('');
    // the save button goes disabled with the empty box: the focus returns to the box, not to the page
    this.nameInput()?.nativeElement.focus();
  }

  startRename(view: { id: string; name: string }): void {
    this.newName.set(view.name);
    this.confirmingDelete.set(null);
    this.renaming.set(view.id);
  }

  /** the message under the rename box when the name is refused (an unchanged one says nothing) */
  renameMessage(id: string): string | null {
    const current = this.views().views.find((view) => view.id === id)?.name ?? '';
    const typed = this.newName().trim();
    return typed === '' || typed === current ? null : this.messageFor(nameProblem(this.views(), typed));
  }

  submitRename(id: string): void {
    if (this.renameMessage(id) !== null) {
      return;
    }
    const typed = this.newName().trim();
    const current = this.views().views.find((view) => view.id === id)?.name ?? '';
    if (typed === '' || typed === current) {
      this.renaming.set(null);
      return;
    }
    this.renameTo.emit({ id, name: typed });
    this.renaming.set(null);
  }

  toggleColumn(field: string, event: Event): void {
    const box = event.target as HTMLInputElement | null;
    this.columnVisibility.emit({ field, visible: box?.checked ?? false });
  }
}
