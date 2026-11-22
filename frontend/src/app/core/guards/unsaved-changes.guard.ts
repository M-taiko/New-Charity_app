import type { CanDeactivateFn } from '@angular/router';
import { inject } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';

/**
 * Forms with unsaved state implement this to warn before leaving (UX-6.4).
 * Components without the method pass through untouched — the guard can be
 * attached to every form route safely.
 */
export interface HasUnsavedChanges {
  hasUnsavedChanges?: () => boolean;
}

/**
 * UX-6.4: one shared deactivation guard for every form route — warns via
 * confirm() when the component reports dirty state. Browser-refresh closes
 * are covered separately by beforeunload inside dirty components.
 */
export const unsavedChangesGuard: CanDeactivateFn<HasUnsavedChanges> = (component) => {
  if (!component?.hasUnsavedChanges || !component.hasUnsavedChanges()) {
    return true;
  }
  const translate = inject(TranslateService);
  // native confirm — the Angular dialog needs a component ref we don't have here
  // eslint-disable-next-line no-alert
  return window.confirm(translate.instant('common.unsaved_changes'));
};
