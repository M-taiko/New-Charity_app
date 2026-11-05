import { AutoFocus } from 'primeng/autofocus';

/**
 * PrimeNG 20.4 quirk (console noise): Select and DatePicker declare their
 * `autofocus` input as bare `autofocus;` — the default is `undefined`, not
 * `false`. The internal AutoFocus directive only removes the native
 * `autofocus` attribute when the value is EXACTLY false, so every internal
 * input (the paginator's page-size select, datepicker inputs, …) carries
 * `autofocus` and Chrome logs "Autofocus processing was blocked because a
 * document already has a focused element" on every page with those controls.
 *
 * This patch normalises `undefined` to `false` once per instance, so the
 * directive takes its own intended false path (attribute removed, no focus
 * steal). Explicit `[autofocus]="true"` usages keep working.
 */
interface AutofocusHost {
  autofocus?: unknown;
}

export function fixPrimeNgAutofocusDefault(): void {
  // read via a descriptor and re-invoke with the live instance — the method
  // must keep its own `this` (host element, focused flag)
  const descriptor = Object.getOwnPropertyDescriptor(AutoFocus.prototype, 'onAfterContentChecked');
  const original = descriptor?.value as ((this: AutofocusHost) => void) | undefined;

  AutoFocus.prototype.onAfterContentChecked = function (this: AutofocusHost): void {
    if (this.autofocus === undefined) {
      this.autofocus = false;
    }
    original?.call(this);
  };
}
