import type { ApplicationConfig} from '@angular/core';
import { APP_INITIALIZER, inject } from '@angular/core';
import { provideZoneChangeDetection, LOCALE_ID } from '@angular/core';
import { registerLocaleData } from '@angular/common';
import localeArEG from '@angular/common/locales/ar-EG';

// without this, any built-in pipe (DatePipe…) throws NG0701 and aborts rendering
registerLocaleData(localeArEG, 'ar-EG');
import { provideRouter, withComponentInputBinding } from '@angular/router';
import { provideHttpClient, withInterceptors, withXsrfConfiguration } from '@angular/common/http';
import { provideAnimations } from '@angular/platform-browser/animations';
import { providePrimeNG } from 'primeng/config';
import { TranslateService } from '@ngx-translate/core';
import { provideTranslateService } from '@ngx-translate/core';
import { provideTranslateHttpLoader } from '@ngx-translate/http-loader';
import { firstValueFrom } from 'rxjs';
import { routes } from './app.routes';
import { apiUrlInterceptor, errorInterceptor, progressInterceptor } from '@core/http/interceptors';
import { brandPreset } from '@core/theme/brand-preset';

/**
 * Blocks Angular bootstrap until both the Arabic translations AND the web
 * fonts are fully loaded. No page renders with raw i18n keys or fallback
 * fonts — the user sees a brief loader then the complete UI.
 */
export function initializeApp(): () => Promise<void> {
  const translate = inject(TranslateService);

  return () => {
    const translationsReady = firstValueFrom(translate.use('ar'));
    const fontsReady = document.fonts.ready;

    return Promise.all([translationsReady, fontsReady])
      .then(() => undefined)
      .catch(() => undefined); // never block the app on a font failure
  };
}

export const appConfig: ApplicationConfig = {
  providers: [
    provideZoneChangeDetection({ eventCoalescing: true }),
    provideRouter(routes, withComponentInputBinding()),
    provideAnimations(),
    provideHttpClient(
      withXsrfConfiguration({ cookieName: 'XSRF-TOKEN', headerName: 'X-XSRF-TOKEN' }),
      withInterceptors([apiUrlInterceptor, errorInterceptor, progressInterceptor]),
    ),
    provideTranslateService({ lang: 'ar' }),
    provideTranslateHttpLoader({ prefix: './assets/i18n/', suffix: '.json' }),
    {
      provide: APP_INITIALIZER,
      useFactory: initializeApp,
      multi: true,
    },
    providePrimeNG({
      theme: {
        preset: brandPreset,
        options: {
          darkModeSelector: "[data-theme='dark']",
          cssLayer: { name: 'primeng', order: 'base, primeng, blocks, utilities' },
        },
      },
    }),
    { provide: LOCALE_ID, useValue: 'ar-EG' },
  ],
};
