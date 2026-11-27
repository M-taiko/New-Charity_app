import { Directive, ElementRef, inject } from '@angular/core';
import { NgForm } from '@angular/forms';

/**
 * D-09/P-10 (R2): forms never submit silently and their button never looks
 * dead. Attach to the <form> (`#f="ngForm" appValidSubmit #vs="appValidSubmit"`)
 * and guard the handler in the template:
 *
 *   <form (ngSubmit)="vs.validate() && save()" #f="ngForm" appValidSubmit #vs="appValidSubmit">
 *
 * `validate()` returns true when the form may submit; otherwise it marks
 * every field touched (inline errors appear) and focuses the first invalid
 * control. The submit button then only disables while the request is in
 * flight — not up front.
 */
@Directive({
  selector: '[appValidSubmit]',
  exportAs: 'appValidSubmit',
})
export class ValidSubmitDirective {
  private readonly form = inject(NgForm);
  private readonly host = inject<ElementRef<HTMLFormElement>>(ElementRef);

  validate(): boolean {
    if (!this.form.valid) {
      this.form.control.markAllAsTouched();
      const first = this.host.nativeElement.querySelector<HTMLElement>('.ng-invalid:not(form)');
      first?.focus();
      return false;
    }
    return true;
  }
}
