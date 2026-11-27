import { AutoFocus } from 'primeng/autofocus';

/**
 * PrimeNG 20.4 quirk (B-06): `<p-button>` binds
 * `[pAutoFocus]="autofocus || buttonProps?.autofocus"`, which hands the
 * AutoFocus directive `undefined` instead of its `false` default. The
 * directive then runs its `else` branch on EVERY change-detection pass:
 *
 *     if (this.autofocus === false) removeAttribute('autofocus');
 *     else                         setAttribute('autofocus', true);
 *
 * so every PrimeNG button carries a live `autofocus` attribute. Chromium
 * focuses such an element at its next rendering opportunity.
 *
 * The earlier guard stripped the attribute in a MutationObserver microtask
 * and blurred the element if it had been focused. Focus was returned to the
 * page — but only AFTER the browser had scrolled the button into view. On a
 * phone, where the button sits below the fold, the page therefore opened
 * halfway down (/expenses by 523px, /dashboard by 1,106px at 360px) with the
 * button's tooltip left floating; the focus gate passed because it only
 * looked at `document.activeElement` afterwards.
 *
 * So the attribute is now stopped BEFORE it reaches the DOM: the directive
 * may write it as often as it likes, the browser never sees it, nothing is
 * focused, nothing scrolls. `data-keep-autofocus` opts back in (the app never
 * uses native autofocus intentionally).
 */
interface AutofocusHost {
  autofocus?: unknown;
}

const KEEPER = 'data-keep-autofocus';

export function fixPrimeNgAutofocusDefault(): void {
  if (typeof Element === 'undefined' || typeof document === 'undefined') {
    return;
  }

  // 1) the guard that holds in every build: synchronous, and independent of
  //    any method name a minifier may rename
  const setAttribute = Reflect.get(Element.prototype, 'setAttribute') as (this: Element, name: string, value: string) => void;
  Element.prototype.setAttribute = function (this: Element, name: string, value: string): void {
    if (name === 'autofocus' && !this.hasAttribute(KEEPER)) {
      return;
    }
    setAttribute.call(this, name, value);
  };
  document.querySelectorAll(`[autofocus]:not([${KEEPER}])`).forEach((el) => {
    el.removeAttribute('autofocus');
  });

  // 2) prototype normalise — dev/unminified builds only: esbuild renames the
  //    lifecycle method in production, so the lookup finds nothing there and
  //    guard 1 is what stands
  const descriptor = Object.getOwnPropertyDescriptor(AutoFocus.prototype, 'onAfterContentChecked');
  const original = descriptor?.value as ((this: AutofocusHost) => void) | undefined;
  if (original) {
    AutoFocus.prototype.onAfterContentChecked = function (this: AutofocusHost): void {
      if (this.autofocus === undefined) {
        this.autofocus = false;
      }
      original.call(this);
    };
  }

  // 3) last line of defence: an `autofocus` written WITHOUT `setAttribute`
  //    (reflected through the IDL property) is still removed. Only the nodes
  //    a record touches are inspected — this observer sees every mutation.
  if (typeof MutationObserver === 'undefined') {
    return;
  }
  const strip = (el: Element): void => {
    if (!el.hasAttribute('autofocus') || el.hasAttribute(KEEPER)) {
      return;
    }
    el.removeAttribute('autofocus');
    if (el instanceof HTMLElement && document.activeElement === el) {
      el.blur();
    }
  };
  const stripTree = (root: Element): void => {
    strip(root);
    root.querySelectorAll(`[autofocus]:not([${KEEPER}])`).forEach(strip);
  };
  stripTree(document.documentElement);
  new MutationObserver((records) => {
    for (const record of records) {
      if (record.type === 'attributes') {
        stripTree(record.target as Element);
        continue;
      }
      for (const node of record.addedNodes) {
        if (node instanceof Element) {
          stripTree(node);
        }
      }
    }
  }).observe(document.documentElement, { childList: true, subtree: true, attributeFilter: ['autofocus'] });
}
