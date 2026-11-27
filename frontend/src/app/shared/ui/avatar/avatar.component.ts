import { ChangeDetectionStrategy, Component, computed, input, signal } from '@angular/core';

/**
 * A round avatar: the photo when there is a usable one, otherwise the initials
 * on the primary container.
 *
 * "Usable" means present, loaded, and at least half the size it is shown at.
 * Both failure modes are real: a stored profile picture was a 1×1 PNG (a test
 * upload), which `object-fit: cover` stretched into a solid green disc on
 * every page (R4-07), and a picture whose file was removed 404s. The photo is
 * kept invisible until it has been measured, so a bad one never flashes.
 *
 * The avatar is always decorative (`aria-hidden`): the name sits right beside
 * it, and the owning control carries its own accessible name.
 */
@Component({
  selector: 'app-avatar',
  standalone: true,
  template: `
    <span class="avatar" aria-hidden="true">
      <span class="avatar__initials">{{ initials() }}</span>
      @if (picture(); as src) {
        <img
          class="avatar__photo"
          [class.avatar__photo--unverified]="!photoVerified()"
          [src]="src"
          alt=""
          (load)="onLoad($event)"
          (error)="onError()"
        />
      }
    </span>
  `,
  styles: `
    .avatar {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      height: 100%;
      border-radius: var(--radius-full);
      overflow: hidden;
      background: var(--color-primary-soft);
      color: var(--color-on-primary-soft);
      font-weight: 700;
      line-height: 1;
      user-select: none;
    }

    .avatar__photo {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    /* measured first, shown second */
    .avatar__photo--unverified {
      visibility: hidden;
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AvatarComponent {
  name = input.required<string>();
  /** photo URL; null (or a photo that cannot be used) → initials */
  picture = input<string | null>(null);
  /** the side the avatar is shown at, in px — decides what counts as too small to be a photo */
  size = input(36);

  /** the URL of the photo that was verified; anything else is not shown */
  private verifiedUrl = signal<string | null>(null);

  /** a new URL is never "already verified": comparing against the input resets itself */
  readonly photoVerified = computed(() => this.picture() !== null && this.verifiedUrl() === this.picture());

  readonly initials = computed(() =>
    (this.name() ?? '')
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map((part) => part.charAt(0))
      .join(''),
  );

  onLoad(event: Event): void {
    const img = event.target as HTMLImageElement;
    const smallest = this.size() / 2;
    this.verifiedUrl.set(img.naturalWidth >= smallest && img.naturalHeight >= smallest ? this.picture() : null);
  }

  onError(): void {
    this.verifiedUrl.set(null);
  }
}
