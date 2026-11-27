import { ChangeDetectionStrategy, Component, input, model, output } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { DialogModule } from 'primeng/dialog';
import { SharedModule } from 'primeng/api';
import { ActionButtonComponent } from '../actions/action-button.component';
import { StatusStampComponent } from '../status-stamp/status-stamp.component';

/**
 * N-03 — the quick view: one record's summary in a drawer on the start side
 * (the right in RTL), 420px wide, the whole screen on a phone, without
 * leaving the list. The list keeps its filters and its scroll, the address
 * does not change, and the focus goes back to the row that opened it.
 *
 * What it shows:
 *  - `title` / `subtitle`: the record's name and a line under it
 *  - `status` + `statusNamespace`: its status stamp
 *  - the default slot: the summary (an `app-key-value-list`)
 *  - `[quick-actions]`: the record's one or two primary actions, decided by
 *    the page exactly as on the detail page (the same abilities)
 *  - "فتح الصفحة الكاملة": the detail page itself
 *
 * The drawer is PrimeNG's dialog on the right: it brings the focus trap,
 * Escape, the mask and the scroll lock. What it does not bring — the focus
 * going back to the row — is done here.
 */
@Component({
  selector: 'app-quick-view',
  standalone: true,
  imports: [SharedModule, TranslatePipe, DialogModule, ActionButtonComponent, StatusStampComponent],
  template: `
    <p-dialog appendTo="body"
      [visible]="visible()"
      (visibleChange)="onVisibleChange($event)"
      (onHide)="onHidden()"
      [header]="title()"
      [modal]="true"
      [draggable]="false"
      [resizable]="false"
      [dismissableMask]="true"
      [closeOnEscape]="true"
      [blockScroll]="true"
      position="right"
      styleClass="quick-view"
      maskStyleClass="quick-view-mask"
      [closeAriaLabel]="'common.close' | translate"
    >
      @if (subtitle() !== '') {
        <p class="quick-view__subtitle">{{ subtitle() }}</p>
      }
      @if (status() !== null && status() !== '' && !loading()) {
        <app-status-stamp [status]="status() ?? ''" [namespace]="statusNamespace()" [large]="true" />
      }
      @if (loading()) {
        <div class="quick-view__loading" role="status" [attr.aria-label]="'common.loading' | translate">
          <div class="skeleton skeleton--text"></div>
          <div class="skeleton skeleton--text"></div>
          <div class="skeleton skeleton--text"></div>
          <div class="skeleton skeleton--text"></div>
          <div class="skeleton skeleton--text"></div>
        </div>
      } @else {
        <ng-content />
      }
      <ng-template pTemplate="footer">
        <div class="quick-view__actions">
          <ng-content select="[quick-actions]" />
          @if (!loading()) {
            <app-action-button kind="openPage" label="common.open_full_page" size="small" [route]="fullRoute()" />
          }
        </div>
      </ng-template>
    </p-dialog>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class QuickViewComponent {
  visible = model(false);
  title = input('');
  /** one line under the title: who holds the record, its number */
  subtitle = input('');
  /** the record's status code (null: no stamp) and the namespace its label comes from */
  status = input<string | null>(null);
  statusNamespace = input('');
  loading = input(false);
  /** where "فتح الصفحة الكاملة" goes */
  fullRoute = input<readonly unknown[]>([]);
  /** the row that opened the drawer — it gets the focus back when the drawer closes */
  returnFocusTo = input<HTMLElement | null>(null);
  /** the drawer closed (Escape, the mask, its close button) */
  closed = output();

  /** what had the focus when the drawer opened, when the page named no row */
  private focusedBefore: HTMLElement | null = null;
  /** false while the drawer closes for one of its own actions: that action's dialog takes the focus */
  private bringFocusBack = true;

  open(): void {
    this.focusedBefore = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    this.bringFocusBack = true;
    this.visible.set(true);
  }

  /**
   * `returnFocus` is false when the drawer closes for one of its actions:
   * the action's own dialog or page takes the focus, and the row must not
   * pull it back a moment later.
   */
  close(returnFocus = true): void {
    if (this.visible()) {
      this.bringFocusBack = returnFocus;
      this.visible.set(false);
    }
  }

  onVisibleChange(next: boolean): void {
    this.visible.set(next);
  }

  /** after the leave animation: the focus goes back to where the drawer was opened from */
  onHidden(): void {
    this.closed.emit();
    if (!this.bringFocusBack) {
      return;
    }
    const target = this.returnFocusTo() ?? this.focusedBefore;
    // gone from the page meanwhile (the list was reloaded): there is nothing to give the focus back to
    if (!target?.isConnected) {
      return;
    }
    target.focus();
  }
}
