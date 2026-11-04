import type { PipeTransform } from '@angular/core';
import { Injector, Pipe, inject } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';

/**
 * Money formatting (T23): thousands separator, Western digits, "1,234.50 ج.م".
 * Input is the API's two-decimal string (never recomputed, plan 25.2).
 * The currency label is the `common.egp` translation (T4-30 — no literal);
 * translations are guaranteed loaded before first render by the app
 * initializer, so instant() is safe here.
 */
@Pipe({ name: 'money' })
export class MoneyPipe implements PipeTransform {
  private injector = inject(Injector);

  // Lazy: constructing TranslateService eagerly would close the DI cycle
  // HttpClient -> interceptor -> (anything) -> TranslateService -> loader -> HttpClient.
  private get currency(): string {
    const translate = this.injector.get(TranslateService, null, { optional: true });
    const key = 'common.egp';
    const label = translate ? translate.instant(key) : key;
    return label !== key ? label : '';
  }

  transform(value: string | number | null | undefined, withCurrency = true): string {
    const num = typeof value === 'string' ? parseFloat(value) : (typeof value === 'number' ? value : 0);
    const currency = this.currency;
    if (!isFinite(num)) {
      return withCurrency ? `0.00 ${currency}`.trim() : '0.00';
    }
    const formatted = num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return withCurrency ? `${formatted} ${currency}`.trim() : formatted;
  }
}
