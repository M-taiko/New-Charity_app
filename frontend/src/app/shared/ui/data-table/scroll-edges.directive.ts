import { Directive, ElementRef, type OnDestroy, afterNextRender, inject } from '@angular/core';

/**
 * R5-03: tells a table card which of its edges still has columns behind it.
 *
 * A table wider than its card scrolls sideways inside the card. That is easy
 * to miss: nothing on screen says the status column or the actions are there,
 * a column further to the side. This directive watches the scroll box and
 * writes what it sees onto the card:
 *
 *   data-scroll        "true"  the table is wider than its card
 *   data-scroll-start  "true"  there is more toward the start edge
 *   data-scroll-end    "true"  there is more toward the end edge
 *
 * The stylesheet turns those into an edge fade, a one-line hint on phones and
 * a pinned first column. It also places the two fade elements over the
 * table's own box — not the toolbar above it, not the pager below it.
 */
@Directive({ selector: '[appScrollEdges]' })
export class ScrollEdgesDirective implements OnDestroy {
  private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);

  private target: HTMLElement | null = null;
  private fadeEnd: HTMLElement | null = null;
  private fadeStart: HTMLElement | null = null;
  private readonly resize: ResizeObserver | null = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(() => {
    this.update();
  });

  constructor() {
    afterNextRender(() => {
      const card = this.host.nativeElement;
      this.fadeEnd = card.appendChild(document.createElement('span'));
      this.fadeEnd.className = 'ledger-table__fade ledger-table__fade--end';
      this.fadeStart = card.appendChild(document.createElement('span'));
      this.fadeStart.className = 'ledger-table__fade ledger-table__fade--start';
      this.resize?.observe(card);
      this.update();
    });
  }

  /** read the scroll box and write the edge state (all reads first, then the writes) */
  private update = (): void => {
    const card = this.host.nativeElement;
    if (!this.target?.isConnected) {
      this.target = card.querySelector<HTMLElement>('.p-datatable-table-container');
      if (this.target) {
        this.target.addEventListener('scroll', this.update, { passive: true });
        this.resize?.observe(this.target);
        const table = this.target.querySelector('table');
        if (table) {
          this.resize?.observe(table);
        }
      }
    }
    const target = this.target;
    if (!target || !this.fadeEnd || !this.fadeStart) {
      return;
    }

    const max = target.scrollWidth - target.clientWidth;
    const fromStart = Math.abs(target.scrollLeft);
    const scrolls = max > 1;
    card.dataset.scroll = String(scrolls);
    card.dataset.scrollStart = String(scrolls && fromStart > 1);
    card.dataset.scrollEnd = String(scrolls && fromStart < max - 1);

    const cardBox = card.getBoundingClientRect();
    const box = target.getBoundingClientRect();
    const top = `${Math.round(box.top - cardBox.top)}px`;
    this.fadeEnd.style.top = top;
    this.fadeStart.style.top = top;
    this.fadeEnd.style.height = `${Math.round(box.height)}px`;
    this.fadeStart.style.height = `${Math.round(box.height)}px`;
    // the start-edge fade must not sit on the pinned first column: that
    // column does not move, so the fade starts after it. Measured on the
    // first header cell that RENDERS: on a selectable table the selection
    // cell comes first and is hidden on a phone — 0px wide, which put the
    // fade back on top of the pinned column, where its checkbox is
    const pinned = scrolls
      ? [...target.querySelectorAll<HTMLElement>('thead th')].find((cell) => getComputedStyle(cell).display !== 'none') ?? null
      : null;
    this.fadeStart.style.insetInlineStart = pinned ? `${Math.round(pinned.getBoundingClientRect().width)}px` : '0px';
  };

  ngOnDestroy(): void {
    this.resize?.disconnect();
    this.target?.removeEventListener('scroll', this.update);
    this.fadeEnd?.remove();
    this.fadeStart?.remove();
  }
}
