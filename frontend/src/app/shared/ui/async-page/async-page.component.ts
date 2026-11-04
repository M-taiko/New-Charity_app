import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { ErrorStateComponent } from '@shared/ui/error-state/error-state.component';
import { PageSkeletonComponent } from '@shared/ui/page-skeleton/page-skeleton.component';

/**
 * The minimal structural shape of `resource()`'s return value — accepts any
 * `ResourceRef<T>` regardless of T (plain function members avoid the
 * WritableSignal variance trap; `hasValue` is optional so hand-built adapters
 * exposing only `value()` still work).
 *
 * IMPORTANT: `value()` THROWS (ResourceValueError) while the resource is in
 * its error state, so this wrapper never touches `value()` directly — it
 * drives all branches off `error()`, `isLoading()` and `hasValue()`.
 */
export interface LoadableResource {
  readonly error: () => unknown;
  readonly isLoading: () => boolean;
  hasValue?(): boolean;
  readonly value?: () => unknown;
  reload(): unknown;
}

/**
 * The page-level state machine (T4-20): error → skeleton → empty → content.
 * Pages stop repeating `@if (data.value(); as d)` and no page is ever blank:
 *
 *   <app-async-page [resource]="data" variant="report" [empty]="isEmpty">
 *     …content (own @if (data.value(); as d) stays inside)…
 *     <div empty><app-empty-state … /></div>
 *   </app-async-page>
 *
 * `[empty]` takes a FUNCTION REFERENCE (no call parens) so the emptiness
 * test — which usually reads `value()` — is only evaluated here, lazily,
 * after the error and loading states have ruled themselves out, and inside a
 * try/catch so a throwing predicate degrades to "not empty".
 * The page header stays OUTSIDE this wrapper so the title never disappears.
 */
@Component({
  selector: 'app-async-page',
  standalone: true,
  imports: [ErrorStateComponent, PageSkeletonComponent],
  template: `
    @if (failed()) {
      <app-error-state [error]="resource().error()" [backLink]="backLink()" (retry)="resource().reload()" />
    } @else if (loading()) {
      <app-page-skeleton [variant]="variant()" />
    } @else if (showEmpty()) {
      <ng-content select="[empty]" />
    } @else {
      <ng-content />
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AsyncPageComponent {
  resource = input.required<LoadableResource>();
  variant = input<'list' | 'detail' | 'report' | 'form'>('list');
  /** the page's emptiness test — pass the method reference: [empty]="isEmpty" */
  empty = input<boolean | (() => boolean)>(false);
  /** optional back link handed to the error state (e.g. the parent list) */
  backLink = input<readonly unknown[] | null>(null);

  /** a failed load with nothing to show (value() would throw here — never read it) */
  readonly failed = computed(() => this.resource().error() !== undefined);

  /** first load without a value yet — keeps stale content during reloads */
  readonly loading = computed(() =>
    !this.failed() && this.resource().isLoading() && !this.readValueGuarded());

  /** empty slot only for a loaded, non-empty-flagged value */
  readonly showEmpty = computed(() => {
    if (this.failed() || this.loading() || !this.readValueGuarded()) {
      return false;
    }
    const predicate = this.empty();
    try {
      return typeof predicate === 'function' ? predicate() : Boolean(predicate);
    } catch {
      return false;
    }
  });

  /** hasValue() when present; falls back to a guarded value() read (throws in error state) */
  private readValueGuarded(): boolean {
    const resource = this.resource() as LoadableResource & { hasValue?: () => boolean };
    if (typeof resource.hasValue === 'function') {
      return resource.hasValue();
    }
    try {
      return resource.value !== undefined && resource.value() !== undefined;
    } catch {
      return false;
    }
  }
}
