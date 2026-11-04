import { Injectable, inject } from '@angular/core';
import { Title } from '@angular/platform-browser';
import type { RouterStateSnapshot} from '@angular/router';
import { TitleStrategy } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';

/** Consistent tab titles: "الصفحة — المؤسسة" (plan 19.4; org name via i18n, T4-30). */
@Injectable()
export class AppTitleStrategy extends TitleStrategy {
  private title = inject(Title);
  private translate = inject(TranslateService);

  override updateTitle(snapshot: RouterStateSnapshot): void {
    const org = this.translate.instant('common.org_name');
    const routeTitle = this.buildTitle(snapshot);
    if (!routeTitle) {
      this.title.setTitle(org);
      return;
    }
    const translated = this.translate.instant(routeTitle);
    this.title.setTitle(translated !== routeTitle ? `${translated} — ${org}` : org);
  }
}
