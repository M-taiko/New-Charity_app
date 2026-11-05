import type { AfterViewInit, OnDestroy} from '@angular/core';
import { Directive, ElementRef, inject } from '@angular/core';
import autoAnimate from '@formkit/auto-animate';
import { MotionService } from '@core/motion/motion.service';

/**
 * FLIP animation for add/remove/reorder of list items (Task6 T6-50.4) via
 * @formkit/auto-animate. Attach to the container: `<ul appFlip>` — children
 * entering/leaving/moving animate automatically. No-op in Off (and Lite keeps
 * only the standard transitions, per the level table).
 */
@Directive({ selector: '[appFlip]' })
export class FlipDirective implements AfterViewInit, OnDestroy {
  private host = inject<ElementRef<HTMLElement>>(ElementRef);
  private motion = inject(MotionService);
  private dispose: (() => void) | null = null;
  private observer: MutationObserver | null = null;

  ngAfterViewInit(): void {
    if (this.motion.level() !== 'full') {
      return;
    }
    this.dispose = autoAnimate(this.host.nativeElement, { duration: 220 });
  }

  ngOnDestroy(): void {
    this.dispose?.();
    this.observer?.disconnect();
  }
}
