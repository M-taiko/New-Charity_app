import { Injectable, signal } from '@angular/core';

export type MotionLevel = 'full' | 'lite' | 'off';

/**
 * Adaptive motion (plan 12.4): the root data-motion attribute carries the level.
 * GSAP effects and count-ups read this before running. The user's explicit
 * choice always wins over the device probe.
 */
@Injectable({ providedIn: 'root' })
export class MotionService {
  readonly level = signal<MotionLevel>(this.detect());

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
  }

  private detect(): MotionLevel {
    const saved = localStorage.getItem('naguib-selim.motion');
    if (saved === 'off' || saved === 'lite' || saved === 'full') {
      return saved;
    }
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      return 'off';
    }
    // Weak-device probe: few cores or little memory → lite (fades only)
    const nav = navigator as Navigator & { deviceMemory?: number };
    const cores = nav.hardwareConcurrency;
    const memory = nav.deviceMemory ?? 4;
    if (cores <= 2 || memory <= 2) {
      return 'lite';
    }
    return 'full';
  }
}
