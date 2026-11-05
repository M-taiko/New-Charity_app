import { ChangeDetectionStrategy, Component, HostListener, inject, signal } from '@angular/core';
import { NavigationEnd, Router, RouterOutlet } from '@angular/router';
import { filter } from 'rxjs';
import { MotionService } from '@core/motion/motion.service';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet],
  template: `<router-outlet />`,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AppComponent {
  private motion = inject(MotionService);
  private router = inject(Router);
  /** increments per navigation — the rich mode page-enter animation replays */
  readonly navCount = signal(0);

  constructor() {
    // T6-50.1: each route opens with a transition in Full mode — the View
    // Transitions API where available, otherwise the CSS page-enter classes
    // (motion.scss) do the fade+slide per page type.
    this.router.events
      .pipe(filter((event): event is NavigationEnd => event instanceof NavigationEnd))
      .subscribe((event) => {
        this.navCount.update((n) => n + 1);
        if (this.motion.level() !== 'full') {
          return;
        }
        const doc = document as Document & {
          startViewTransition?: (cb: () => void) => {
            updateCallbackDone: Promise<void>;
            finished: Promise<void>;
          };
        };
        // the CSS fallback (data-motion=full + .page-enter) covers Chrome 109;
        // startViewTransition is the smoother path on newer engines. Skipped
        // while the tab is hidden — a transition on a hidden document rejects
        // with InvalidStateError — and rejections (rapid navigation aborts)
        // are expected, so they are swallowed.
        if (typeof doc.startViewTransition === 'function' && !document.hidden) {
          const transition = doc.startViewTransition(() => undefined);
          transition.updateCallbackDone.catch(() => undefined);
          transition.finished.catch(() => undefined);
        }
        document.body.classList.remove('page-enter');
        // force reflow so the same class re-triggers on route change
        void document.body.offsetWidth;
        document.body.classList.add('page-enter');
        setTimeout(() => { document.body.classList.remove('page-enter'); }, 400);
      });
  }

  /**
   * Nothing in the app is draggable except table column headers (T6-17):
   * kill every native drag (links, images, SVG icons, selected text) at the
   * source. Reordering a table column starts on the header cell, which
   * PrimeNG drives itself and which lives outside this listener's concern.
   */
  @HostListener('document:dragstart', ['$event'])
  onDragStart(event: DragEvent): void {
    const target = event.target as HTMLElement | null;
    if (!target) {
      return;
    }
    if (target.closest('th[pReorderableColumn]')) {
      return; // the one allowed drag: reordering table columns
    }
    event.preventDefault();
  }
}
