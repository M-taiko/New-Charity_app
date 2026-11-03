import { Injectable, inject } from '@angular/core';
import { Title } from '@angular/platform-browser';
import type { RouterStateSnapshot} from '@angular/router';
import { TitleStrategy } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';

/** Consistent tab titles: "الصفحة — مؤسسة نجيب سليم الخيرية" (plan 19.4). */
@Injectable()
export class AppTitleStrategy extends TitleStrategy {
  private title = inject(Title);
  private translate = inject(TranslateService);

  override updateTitle(snapshot: RouterStateSnapshot): void {
    const routeTitle = this.buildTitle(snapshot);
    if (!routeTitle) {
      this.title.setTitle('مؤسسة نجيب سليم الخيرية');
      return;
    }
    const translated = this.translate.instant(routeTitle);
    this.title.setTitle(translated !== routeTitle ? `${translated} — مؤسسة نجيب سليم الخيرية` : 'مؤسسة نجيب سليم الخيرية');
  }
}
