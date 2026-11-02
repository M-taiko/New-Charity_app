import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

/** Avatar initials + name; avatars are the only full-radius shapes (plan 10.2). */
@Component({
  selector: 'app-user-chip',
  standalone: true,
  template: `
    <span class="u-flex u-gap-1">
      @if (picture()) {
        <img
          [src]="picture()"
          [alt]="name()"
          style="width: 24px; height: 24px; border-radius: var(--radius-full); object-fit: cover"
        />
      } @else {
        <span
          style="
            width: 24px;
            height: 24px;
            border-radius: var(--radius-full);
            background: var(--color-primary-soft);
            color: var(--color-primary);
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
          "
          [attr.aria-hidden]="true"
        >
          {{ initials() }}
        </span>
      }
      <span class="u-truncate">{{ name() }}</span>
    </span>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UserChipComponent {
  name = input.required<string>();
  picture = input<string | null>(null);

  initials = computed(() =>
    this.name()
      .split(/\s+/)
      .slice(0, 2)
      .map((part) => part.charAt(0))
      .join(''),
  );
}
