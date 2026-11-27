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
import { IconComponent } from '@shared/ui/icon/icon.component';
import type { ModuleAccent } from '@shared/ui/icon/icon.component';
import { SparklineComponent } from '@shared/ui/sparkline/sparkline.component';

/**
 * Stat tile (plan 11.2): label, hero figure in the display face, companion
 * line. The figure counts up once per session view when motion allows (12.2).
 * T7-09: `accent` + `iconName` render the module chip — each module is
 * recognizable by its accent in tiles as in headers and navigation.
 *
 * R4-04: every tile of a KPI row carries its icon (20px glyph in a soft
 * circle, in the row's module accent) — a row never mixes tiles with and
 * without one. The card reads its own width (the host is its query container)
 * so a narrow tile in a five-up row tightens instead of overflowing.
 */
@Component({
  selector: 'app-stat-tile',
  standalone: true,
  imports: [TranslatePipe, MoneyPipe, IconComponent, SparklineComponent],
  template: `
    <div
      class="stat-tile"
      [class.stat-tile--wide]="wide()"
      [class.stat-tile--inset]="inset()"
      [class.stat-tile--icon-circle]="circle()"
      [class]="toneClass()"
    >
      @if (accent() !== '' && iconName() !== '' && circle()) {
        <span class="stat-tile__circle" [class]="'stat-tile__circle--' + circleTone()">
          <app-icon [name]="iconName()" [size]="20" />
        </span>
      }
      <div class="stat-tile__head">
        <span class="stat-tile__label">{{ label() | translate }}</span>
        <!-- R3-06: the icon renders whenever iconName is set — accent is
             optional (soft neutral circle), not a precondition -->
        @if (iconName() !== '' && !circle()) {
          <span class="stat-tile__icon" [attr.data-chip]="accent() || null">
            <app-icon [name]="iconName()" [chip]="accent()" [size]="20" />
          </span>
        }
        @if (icon()) {
          <ng-content select="[icon]" />
        }
      </div>
      <!-- the figure and the currency unit are two elements: the count-up
           writes into the FIGURE only. It used to set textContent on the
           whole value line, which deleted the "ج.م" unit from every non-zero
           money tile (a zero skips the animation — so only "0.00 ج.م" kept it) -->
      @if (money()) {
        <span class="stat-tile__value" [class.stat-tile__value--hero]="hero()">
          <!-- N-09: the figure's box is sized by the FINAL figure (the hidden
               sizer). The count-up rewrites the number every frame and used to
               change its width with it, nudging the chip beside the tile -->
          <span class="stat-tile__figure">
            <span class="stat-tile__number" #value>{{ displayValue() | money: false }}</span>
            <span class="stat-tile__sizer" aria-hidden="true">{{ finalFigure() | money: false }}</span>
          </span>
          <span class="stat-tile__currency">{{ 'common.egp' | translate }}</span>
        </span>
      } @else {
        <span class="stat-tile__value" [class.stat-tile__value--hero]="hero()">
          <span class="stat-tile__number" #value>{{ displayValue() }}</span>
        </span>
      }
      @if (caption()) {
        <span class="stat-tile__caption">{{ caption() | translate: captionParams() }}</span>
      }
      <!-- N-09: with reserveSpark, the delta line and the sparkline have
           their place before the slow request that fills them arrives — the
           tile is as tall at first paint as it will be -->
      @if (delta() !== null || reserveSpark()) {
        <span
          class="stat-tile__delta"
          [class.stat-tile__delta--reserved]="delta() === null"
          [class.stat-tile__delta--up]="(delta() ?? 0) >= 0"
          [class.stat-tile__delta--down]="(delta() ?? 0) < 0"
          [attr.aria-hidden]="delta() === null || null"
        >
          {{ (delta() ?? 0) >= 0 ? '▲' : '▼' }} {{ abs(delta() ?? 0) }}%
        </span>
      }
      @if (spark().length > 1 || reserveSpark()) {
        <span class="stat-tile__spark" [class.stat-tile__spark--reserved]="spark().length <= 1">
          <app-sparkline [values]="spark().length > 1 ? spark() : noSpark" [tone]="(delta() ?? 0) >= 0 ? 'up' : 'down'" />
        </span>
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
  /** interpolation params for the caption key (e.g. { n: 17 }) */
  captionParams = input<Record<string, unknown> | null>(null);
  hint = input('');
  icon = input(false);
  /** module key (T7-09) — renders the icon as a chip in the module accent */
  accent = input<ModuleAccent | ''>('');
  /** Tabler icon name shown as the accent chip (T7-09) */
  iconName = input('');
  /**
   * Colours the VALUE — and only when the figure is not zero. A tone carries
   * meaning (incoming green, outgoing red); a coloured `0.00` carries none.
   * It no longer adds a 4px top bar: a KPI row has one shape, and four
   * different coloured bars over one row said nothing (R3-06 / R4-04).
   */
  tone = input<'' | 'success' | 'warning' | 'danger' | 'info' | 'primary'>('');
  /** RD-3.2 pattern 3 — circular status icon chip at the card's far corner */
  circle = input(false);
  /** RD-4.10 — tinted mini-card: tint background + tinted border (custody detail) */
  tinted = input(false);
  /** D-05: percent change vs the previous period — renders the ▲/▼ delta chip */
  delta = input<number | null>(null);
  /** D-05: sparkline values (oldest first) rendered as the card's bottom area */
  spark = input<number[]>([]);
  /**
   * N-09: the tile WILL get a delta and a sparkline, from a request that is
   * still running — reserve their two rows now (invisible) so the tile keeps
   * one height from its first frame. Off by default: a tile that never gets
   * a sparkline must not carry two empty rows.
   */
  reserveSpark = input(false);

  /** a flat, hidden stand-in for a sparkline that has not arrived */
  protected readonly noSpark: number[] = [0, 0];

  /** the final figure the count-up ends on — named for the template, where `value` is the #value element */
  protected readonly finalFigure = computed(() => this.value());

  abs(n: number): number {
    return Math.abs(n);
  }

  /** the figure is exactly zero ("0", "0.00") — a tone on it would be decoration */
  private zero = computed(() => {
    const raw = this.value();
    return (typeof raw === 'string' ? Number.parseFloat(raw) : raw) === 0;
  });

  /** the icon circle follows the tone when set, else the module accent */
  circleTone(): 'success' | 'warning' | 'danger' | 'info' | 'primary' {
    return this.tone() || 'primary';
  }

  /**
   * tinted → `stat-tile--tinted stat-tile--tint-<tone>` (the custody-detail
   * mini-cards, unchanged); plain → `stat-tile--accent-<tone>`, which colours
   * the value only, and not on a zero figure.
   */
  toneClass = computed(() => {
    const tone = this.tone();
    if (!tone) {
      return '';
    }
    if (this.tinted()) {
      return `stat-tile--tinted stat-tile--tint-${tone}`;
    }
    return this.zero() ? '' : `stat-tile--accent-${tone}`;
  });

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
    if (level === 'off' || !this.parse(this.value())) {
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
