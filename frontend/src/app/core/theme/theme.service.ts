import { Injectable, signal } from '@angular/core';

type Theme = 'light' | 'dark';

/**
 * Light/dark switching (plan 13). index.html applies the class before first
 * paint; this service owns changes after boot. Theme change is instant —
 * no animation, by design.
 */
@Injectable({ providedIn: 'root' })
export class ThemeService {
  readonly theme = signal<Theme>(this.initial());

  constructor() {
    this.apply(this.theme());
  }

  toggle(): void {
    this.set(this.theme() === 'light' ? 'dark' : 'light');
  }

  set(theme: Theme): void {
    localStorage.setItem('naguib-selim.theme', theme);
    this.apply(theme);
    this.theme.set(theme);
  }

  private apply(theme: Theme): void {
    document.documentElement.setAttribute('data-theme', theme);
    const meta = document.querySelector('meta[name="theme-color"]');
    meta?.setAttribute('content', theme === 'dark' ? '#4F2F1D' : '#F7F3EF');
  }

  private initial(): Theme {
    const saved = localStorage.getItem('naguib-selim.theme');
    if (saved === 'light' || saved === 'dark') {
      return saved;
    }
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
}
