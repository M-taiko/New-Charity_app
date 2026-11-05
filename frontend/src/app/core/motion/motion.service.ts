import { Injectable, inject, signal } from '@angular/core';
import { PrimeNG } from 'primeng/config';

export type MotionLevel = 'full' | 'lite' | 'off';

/**
 * Adaptive motion (plan 12.4 + Task6 T6-50): ONE source of truth for every
 * animation decision.
 *
 * - **off** — completely static: no CSS animations/transitions at all (removed,
 *   not shortened), no count-up, Chart.js `animation:false`, ripple off, no
 *   skeleton shimmer, no smooth scrolling, no view transitions.
 * - **lite** — exactly the old Full: money count-up on tiles, Chart.js
 *   entrance (350 ms), the standard hover/focus/color transitions, shimmer.
 * - **full** — lite plus the rich layer: page-enter transitions, card/tile
 *   reveals, overlay entrance animations, micro-interactions (button press,
 *   hover lift, animated tab indicator), staggered chart entrances.
 *
 * The level lands on `<html data-motion>` (CSS hooks) and `--motion-scale`
 * (0/1/2 for JS). prefers-reduced-motion defaults NEW users to off; a saved
 * user choice always wins.
 */
@Injectable({ providedIn: 'root' })
export class MotionService {
  readonly level = signal<MotionLevel>(this.detect());

  private primeNg = inject(PrimeNG);

  constructor() {
    this.apply(this.level());
  }

  set(level: MotionLevel): void {
    localStorage.setItem('naguib-selim.motion', level);
    this.apply(level);
    this.level.set(level);
  }

  private apply(level: MotionLevel): void {
    document.documentElement.setAttribute('data-motion', level);
    document.documentElement.style.setProperty('--motion-scale', level === 'off' ? '0' : level === 'lite' ? '1' : '2');
    // ripple exists only in the rich mode (T6-50: Off is completely static)
    this.primeNg.ripple.set(level === 'full');
  }

  private detect(): MotionLevel {
    const saved = localStorage.getItem('naguib-selim.motion');
    if (saved === 'off' || saved === 'lite' || saved === 'full') {
      return saved;
    }
    // reduced-motion users start with everything off — their explicit choice wins
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      return 'off';
    }
    // Weak-device probe: few cores or little memory → lite (the old Full)
    const nav = navigator as Navigator & { deviceMemory?: number };
    const cores = nav.hardwareConcurrency;
    const memory = nav.deviceMemory ?? 4;
    if (cores <= 2 || memory <= 2) {
      return 'lite';
    }
    return 'full';
  }
}
