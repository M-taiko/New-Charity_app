import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';

/** Fixed-track layouts: auto packs 2-3 fields per row by width; 2/3 pin the
 *  column count so sibling fields ALWAYS share one row (transfer form etc.) */
export type FormSectionColumns = 'auto' | '2' | '3';

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
      <div class="form-section__grid" [class]="gridClass()">
        <ng-content />
      </div>
    </section>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FormSectionComponent {
  /** i18n key */
  title = input('');
  /** grid layout: auto (width-driven) or a fixed 2/3 tracks */
  columns = input<FormSectionColumns>('auto');

  gridClass = computed(() => (this.columns() === 'auto' ? '' : `form-section__grid--${this.columns()}`));
}
