import type { OnDestroy , OnInit } from '@angular/core';
import { ChangeDetectionStrategy, Component, inject, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { ToggleSwitchModule } from 'primeng/toggleswitch';
import { LocalStorageService } from '@core/storage/local-storage.service';

/**
 * The auto-refresh switch (Task6 T6-02): "enable for live data" — reloads the
 * host table every 30 s while the browser tab is visible, pauses when hidden,
 * and remembers the choice per user (localStorage key `auto-refresh.<key>`).
 * Emits `refresh` on every tick; the page reloads its own resource.
 */
@Component({
  selector: 'app-auto-refresh',
  standalone: true,
  imports: [FormsModule, TranslatePipe, ToggleSwitchModule],
  template: `
    <label class="auto-refresh">
      <p-toggleswitch [ngModel]="enabled()" (ngModelChange)="setEnabled($event)" name="auto-refresh-{{ key() }}"
                      [ariaLabel]="'common.auto_refresh' | translate" />
      <span class="auto-refresh__label">{{ 'common.auto_refresh' | translate }}</span>
    </label>
  `,
  styles: `
    :host {
      display: inline-flex;
    }
    .auto-refresh {
      display: inline-flex;
      align-items: center;
      gap: var(--space-2);
      cursor: pointer;
    }
    .auto-refresh__label {
      font-size: var(--text-xs);
      color: var(--color-text-muted);
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AutoRefreshComponent implements OnDestroy, OnInit {
  /** per-page storage key, e.g. "tracking" */
  key = input.required<string>();
  /** refresh interval in ms (default 30 s) */
  intervalMs = input(30_000);

  enabled = signal(false);
  refresh = output();

  private storage = inject(LocalStorageService);
  private timer: ReturnType<typeof setInterval> | null = null;
  private readonly visibilityHandler = (): void => {
    if (document.hidden) {
      this.pause();
    } else if (this.enabled()) {
      this.resume();
    }
  };

  constructor() {
    document.addEventListener('visibilitychange', this.visibilityHandler);
  }

  ngOnInit(): void {
    this.enabled.set(this.storage.get(`auto-refresh.${this.key()}`, false));
    if (this.enabled()) {
      this.resume();
    }
  }

  setEnabled(next: boolean): void {
    this.enabled.set(next);
    this.storage.set(`auto-refresh.${this.key()}`, next);
    if (next) {
      this.resume();
    } else {
      this.pause();
    }
  }

  private resume(): void {
    if (this.timer !== null) {
      return;
    }
    this.timer = setInterval(() => { this.refresh.emit(); }, this.intervalMs());
  }

  private pause(): void {
    if (this.timer !== null) {
      clearInterval(this.timer);
      this.timer = null;
    }
  }

  ngOnDestroy(): void {
    this.pause();
    document.removeEventListener('visibilitychange', this.visibilityHandler);
  }
}
