import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { AvatarComponent } from '@shared/ui/avatar/avatar.component';

/**
 * Avatar + name; avatars are the only full-radius shapes (plan 10.2).
 * The avatar itself is `app-avatar` (photo, else initials on the primary
 * container) — this used to carry its own inline styles and its own
 * initials-only fallback.
 */
@Component({
  selector: 'app-user-chip',
  standalone: true,
  imports: [AvatarComponent],
  template: `
    <span class="u-flex u-gap-1">
      <app-avatar class="user-chip" [name]="name()" [picture]="picture()" [size]="24" />
      <span class="u-truncate">{{ name() }}</span>
    </span>
  `,
  styles: `
    .user-chip {
      width: 24px;
      height: 24px;
      flex-shrink: 0;
      font-size: 11px;
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UserChipComponent {
  name = input.required<string>();
  picture = input<string | null>(null);
}
