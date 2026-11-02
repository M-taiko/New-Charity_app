import { Directive, Input, TemplateRef, ViewContainerRef, inject } from '@angular/core';
import { AuthService } from '@core/auth/auth.service';

/**
 * Shows or hides content by permission or ability (plan 16.2).
 * Usage: *can="'may_manage_treasury'" — the API remains the authority.
 */
@Directive({ selector: '[can]' })
export class CanDirective {
  private templateRef = inject<TemplateRef<unknown>>(TemplateRef);
  private viewContainer = inject(ViewContainerRef);
  private auth = inject(AuthService);

  @Input({ required: true }) set can(ability: string | string[]) {
    this.viewContainer.clear();
    const abilities = Array.isArray(ability) ? ability : [ability];
    if (abilities.some((a) => this.auth.can(a))) {
      this.viewContainer.createEmbeddedView(this.templateRef);
    }
  }
}
