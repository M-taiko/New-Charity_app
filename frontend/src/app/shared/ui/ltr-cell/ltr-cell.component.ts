import { ChangeDetectionStrategy, Component, input } from '@angular/core';

/**
 * An LTR value — an e-mail address, a Latin identifier — inside an RTL table
 * cell (R4-03). Left as bare cell text the browser ellipsized such a value at
 * its START: an address longer than its column lost its first letters
 * (`…iryasser200599@gmail.com`). Isolated and laid out LTR, the value
 * truncates at its END instead, stays aligned under its header (the cell's
 * start edge), and carries its full text in the `title` tooltip.
 */
@Component({
  selector: 'app-ltr-cell',
  standalone: true,
  template: `<span class="dt-ltr" [title]="value()">{{ value() }}</span>`,
  styles: `
    .dt-ltr {
      display: block;
      direction: ltr;
      unicode-bidi: isolate;
      text-align: end;
      overflow: hidden;
      text-overflow: ellipsis;
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LtrCellComponent {
  value = input.required<string>();
}
