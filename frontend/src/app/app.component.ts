import { ChangeDetectionStrategy, Component, HostListener } from '@angular/core';
import { RouterOutlet } from '@angular/router';

/**
 * Route changes are an INSTANT SPA swap — the shell (sidebar, top bar) never
 * disappears and the new page's content replaces the old one in place.
 *
 * The previous version re-animated the whole <body> from `opacity: 0` on
 * every navigation and fired a View Transition whose callback did nothing,
 * so it cross-faded to an empty outlet: the page went fully blank and faded
 * back in on every click. Route chunks are now preloaded
 * (`withPreloading(PreloadAllModules)`) and the optional "full motion"
 * cross-fade is the router's own `withViewTransitions()`, which keeps the
 * OLD page on screen until the new route has actually rendered.
 */
@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet],
  template: `<router-outlet />`,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AppComponent {
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
