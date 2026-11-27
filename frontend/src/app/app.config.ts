import type { ApplicationConfig} from '@angular/core';
import { APP_INITIALIZER, inject } from '@angular/core';
import { provideZoneChangeDetection, LOCALE_ID } from '@angular/core';
import { registerLocaleData } from '@angular/common';
import localeArEG from '@angular/common/locales/ar-EG';

// without this, any built-in pipe (DatePipe…) throws NG0701 and aborts rendering
registerLocaleData(localeArEG, 'ar-EG');
import {
  PreloadAllModules,
  provideRouter,
  withComponentInputBinding,
  withPreloading,
  withViewTransitions,
} from '@angular/router';
import { provideHttpClient, withInterceptors, withXsrfConfiguration } from '@angular/common/http';
import { provideAnimations } from '@angular/platform-browser/animations';
import { providePrimeNG } from 'primeng/config';
import { TranslateService } from '@ngx-translate/core';
import { provideTranslateService } from '@ngx-translate/core';
import { provideTranslateHttpLoader } from '@ngx-translate/http-loader';
import { firstValueFrom } from 'rxjs';
import { routes } from './app.routes';
import { apiUrlInterceptor, errorInterceptor, progressInterceptor } from '@core/http/interceptors';
import { singleFlightInterceptor } from '@core/http/single-flight';
import { brandPreset } from '@core/theme/brand-preset';
import { fixPrimeNgAutofocusDefault } from '@core/primeng/autofocus-default';
import { MotionService } from '@core/motion/motion.service';

// run once before any component renders (see autofocus-default.ts)
fixPrimeNgAutofocusDefault();

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
    provideRouter(
      routes,
      withComponentInputBinding(),
      // Every route is lazy. Without preloading, the FIRST visit to a page
      // emptied the outlet while its chunk downloaded — a blank flash. All
      // route chunks now load in the background right after startup.
      withPreloading(PreloadAllModules),
      // The optional cross-fade is the router's own transition: it keeps the
      // OLD page visible until the new route has rendered, so it never fades
      // through a blank. Only in the "full" motion level; skipped on hidden
      // tabs (a transition there rejects with InvalidStateError).
      withViewTransitions({
        onViewTransitionCreated: ({ transition }) => {
          if (document.hidden || inject(MotionService).level() !== 'full') {
            transition.skipTransition();
          }
        },
      }),
    ),
    provideAnimations(),
    provideHttpClient(
      withXsrfConfiguration({ cookieName: 'XSRF-TOKEN', headerName: 'X-XSRF-TOKEN' }),
      // singleFlight sits after the URL prefix (it keys on the full URL) and
      // before errorInterceptor, so that one's 419 re-send is a single request
      withInterceptors([apiUrlInterceptor, singleFlightInterceptor, errorInterceptor, progressInterceptor]),
    ),
    provideTranslateService({ lang: 'ar' }),
    provideTranslateHttpLoader({ prefix: './assets/i18n/', suffix: '.json' }),
    {
      provide: APP_INITIALIZER,
      useFactory: initializeApp,
      multi: true,
    },
    providePrimeNG({
      // Global overlay default (T6-15): every dropdown panel attaches to <body>
      // so it never clips inside scroll containers. Components may override.
      overlayAppendTo: 'body',
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
