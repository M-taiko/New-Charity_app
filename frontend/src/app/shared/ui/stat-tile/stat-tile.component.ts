import type {
  AfterViewInit,
  ElementRef} from '@angular/core';
import {
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  input,
  signal,
  viewChild,
} from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { MotionService } from '@core/motion/motion.service';

/**
 * Stat tile (plan 11.2): label, hero figure in the display face, companion
 * line. The figure counts up once per session view when motion allows (12.2).
 */
@Component({
  selector: 'app-stat-tile',
  standalone: true,
  imports: [TranslatePipe, MoneyPipe],
  template: `
    <div class="stat-tile" [class.stat-tile--wide]="wide()" [class.stat-tile--inset]="inset()">
      <span class="stat-tile__label">
        @if (icon()) {
          <ng-content select="[icon]" />
        }
        {{ label() | translate }}
      </span>
      @if (money()) {
        <span class="stat-tile__value" [class.stat-tile__value--hero]="hero()" #value
          >{{ displayValue() | money: false }}<span class="stat-tile__currency"> {{ 'common.egp' | translate }}</span></span
        >
      } @else {
        <span class="stat-tile__value" [class.stat-tile__value--hero]="hero()">{{ displayValue() }}</span>
      }
      @if (caption()) {
        <span class="stat-tile__caption">{{ caption() | translate }}</span>
      }
      @if (hint()) {
        <span class="stat-tile__caption u-bidi">{{ hint() }}</span>
      }
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class StatTileComponent implements AfterViewInit {
  label = input.required<string>();
  value = input<string | number>(0);
  money = input(false);
  hero = input(false);
  wide = input(false);
  inset = input(false);
  caption = input('');
  hint = input('');
  icon = input(false);

  private motion = inject(MotionService);
  private valueEl = viewChild<ElementRef<HTMLElement>>('value');
  private counted = signal(false);

  displayValue = computed(() => this.value());

  ngAfterViewInit(): void {
    if (!this.money() || this.counted()) {
      return;
    }
    const level = this.motion.level();
    const el = this.valueEl()?.nativeElement;
    if (!el) {
      return;
    }
    if (level === 'off' || level === 'lite' || !this.parse(this.value())) {
      return;
    }
    this.counted.set(true);
    this.countUp(el, this.parse(this.value())!);
  }

  private parse(v: string | number): number | null {
    const n = typeof v === 'string' ? parseFloat(v) : v;
    return isFinite(n) ? n : null;
  }

  /** 600ms one-shot count-up, GSAP-free (transform/opacity-only budget kept for elsewhere). */
  private countUp(el: HTMLElement, target: number): void {
    const start = performance.now();
    const dur = 600;
    const step = (now: number) => {
      const t = Math.min((now - start) / dur, 1);
      const eased = 1 - Math.pow(1 - t, 3);
      el.textContent = (target * eased).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
      if (t < 1) {
        requestAnimationFrame(step);
      }
    };
    requestAnimationFrame(step);
  }
}
