import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';

/**
 * The one form section wrapper (T4-23): titled card with the responsive
 * field grid — pages project <app-field> children.
 */
@Component({
  selector: 'app-form-section',
  standalone: true,
  imports: [TranslatePipe],
  template: `
    <section class="form-section">
      @if (title() !== '') {
        <h2 class="form-section__title">{{ title() | translate }}</h2>
      }
      <div class="form-section__grid">
        <ng-content />
      </div>
    </section>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FormSectionComponent {
  /** i18n key */
  title = input('');
}
