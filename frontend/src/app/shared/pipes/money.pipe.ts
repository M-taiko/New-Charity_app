import { PipeTransform } from '@angular/core';
import { Pipe } from '@angular/core';

/**
 * Money formatting (T23): thousands separator, Western digits, "1,234.50 ج.م".
 * Input is the API's two-decimal string (never recomputed, plan 25.2).
 */
@Pipe({ name: 'money' })
export class MoneyPipe implements PipeTransform {
  transform(value: string | number | null | undefined, withCurrency = true): string {
    const num = typeof value === 'string' ? parseFloat(value) : (typeof value === 'number' ? value : 0);
    if (!isFinite(num)) {
      return withCurrency ? `0.00 ج.م` : '0.00';
    }
    const formatted = num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return withCurrency ? `${formatted} ج.م` : formatted;
  }
}
