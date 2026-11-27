import type { OnDestroy, OnInit} from '@angular/core';
import { Directive, ElementRef, computed, effect, inject, input, numberAttribute } from '@angular/core';
import { MotionService } from '@core/motion/motion.service';

/*
 * The motion directives (Task6 T6-50). Every one of them is a no-op unless
 * the motion level allows it — Off never runs a single frame, Lite runs the
 * count-up, Full adds reveals, staggers and list FLIP animations.
 * Transform/opacity only; `will-change` only during the animation.
 */

/**
 * Page enter reveal — RETIRED. It started every page (and its staggered
 * children) at `opacity: 0` and faded them in, which read as a blank flash
 * on every route change. Route swaps are instant now; the optional
 * cross-fade is the router's `withViewTransitions` (old → new, never blank).
 * The selector stays so the existing `[appReveal]` attributes keep working.
 */
@Directive({ selector: '[appReveal]' })
export class RevealDirective implements OnInit {
  /** page type (kept for template compatibility — no visual effect) */
  variant = input<'list' | 'detail' | 'form'>('list');
  /** stagger direct children (kept for template compatibility — no visual effect) */
  stagger = input(0, { transform: numberAttribute });
  /** ms between staggered children (kept for template compatibility) */
  step = input(48);

  private motion = inject(MotionService);

  ngOnInit(): void {
    void this.motion.level();
    void this.variant();
    void this.stagger();
    void this.step();
  }
}

/** Card/tile reveal on scroll into view — once per page via IntersectionObserver (Full). */
@Directive({ selector: '[appCardReveal]' })
export class CardRevealDirective implements OnInit, OnDestroy {
  private host = inject<ElementRef<HTMLElement>>(ElementRef);
  private motion = inject(MotionService);
  private observer: IntersectionObserver | null = null;

  ngOnInit(): void {
    if (this.motion.level() !== 'full' || typeof IntersectionObserver === 'undefined') {
      return;
    }
    const el = this.host.nativeElement;
    el.style.opacity = '0';
    this.observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            const target = entry.target as HTMLElement;
            target.classList.add('reveal-item');
            target.style.opacity = '';
            this.observer?.unobserve(target);
          }
        }
      },
      { threshold: 0.08 },
    );
    this.observer.observe(el);
  }

  ngOnDestroy(): void {
    this.observer?.disconnect();
  }
}

/** Staggers the first table rows on render (Full, up to 12 rows). */
@Directive({ selector: '[appStagger]' })
export class StaggerDirective implements OnInit {
  /** max rows that animate (12 per spec) */
  limit = input(12);

  private host = inject<ElementRef<HTMLElement>>(ElementRef);
  private motion = inject(MotionService);

  ngOnInit(): void {
    if (this.motion.level() !== 'full') {
      return;
    }
    const rows = this.host.nativeElement.querySelectorAll('tbody tr');
    rows.forEach((row, i) => {
      if (i >= this.limit()) {
        return;
      }
      (row as HTMLElement).classList.add('stagger-row');
      (row as HTMLElement).style.animationDelay = `${i * 30}ms`;
    });
  }
}

/**
 * Count-up for numeric tiles and balances (Lite + Full — the old Full
 * behavior). Off renders the final value immediately. The value is observed:
 * a refresh that changes the number ticks up from the old value again.
 */
@Directive({ selector: '[appCountUp]' })
export class CountUpDirective implements OnDestroy {
  appCountUp = input<number | string>(0);
  /** total duration ms */
  duration = input(600);

  private host = inject<ElementRef<HTMLElement>>(ElementRef);
  private motion = inject(MotionService);
  private raf = 0;
  private last = 0;

  private readonly enabled = computed(() => this.motion.level() !== 'off');

  constructor() {
    effect(() => {
      const value = this.appCountUp();
      if (!this.enabled()) {
        this.host.nativeElement.textContent = this.format(this.parse(value));
        return;
      }
      this.tick(this.last, this.parse(value));
    });
  }

  private parse(v: number | string): number {
    const n = typeof v === 'string' ? Number.parseFloat(v) : v;
    return Number.isFinite(n) ? n : 0;
  }

  private format(n: number): string {
    return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  private tick(from: number, to: number): void {
    cancelAnimationFrame(this.raf);
    if (from === to) {
      this.host.nativeElement.textContent = this.format(to);
      this.last = to;
      return;
    }
    const start = performance.now();
    const dur = this.duration();
    const step = (now: number) => {
      const t = Math.min((now - start) / dur, 1);
      const eased = 1 - Math.pow(1 - t, 3);
      const current = from + (to - from) * eased;
      this.host.nativeElement.textContent = this.format(current);
      if (t < 1) {
        this.raf = requestAnimationFrame(step);
      } else {
        this.last = to;
      }
    };
    this.raf = requestAnimationFrame(step);
  }

  ngOnDestroy(): void {
    cancelAnimationFrame(this.raf);
  }
}
