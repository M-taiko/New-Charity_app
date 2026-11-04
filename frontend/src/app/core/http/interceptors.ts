import type { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, finalize, retry, throwError, timeout } from 'rxjs';
import { APP_CONFIG } from '@core/config/app-config';
import { ToastService } from '@core/errors/toast.service';
import { offline } from '@core/http/progress-state';

/** Prefixes every request with the /api/v1 base (absolute URLs untouched). */
export const apiUrlInterceptor: HttpInterceptorFn = (req, next) => {
  const url = req.url;
  // assets (./assets/..., assets/..., /assets/...) and absolute URLs pass through
  const isAsset = url.startsWith('.') || url.startsWith('assets/') || url.startsWith('/assets');
  const isAbsolute = /^https?:\/\//.test(url);
  const isApiPrefixed = url.startsWith('/api');
  if (isAsset || isAbsolute || isApiPrefixed) {
    return next(req);
  }
  // everything else is an API call: prefix with /api/v1 keeping one slash
  const path = url.replace(/^\/+/, '');
  return next(req.clone({ url: `${APP_CONFIG.apiBase}/${path}`, withCredentials: true }));
};

/**
 * Central error handling (plan 18): 401 → login and back, 403 → forbidden page,
 * 404 → not-found, 419 → one retry after refresh, 5xx/network → toast + offline flag.
 * 422 field errors pass through to the calling form untouched.
 */
export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  const toast = inject(ToastService);

  return next(req).pipe(
    timeout(60_000),
    retry({ count: 1, delay: 250 }),
    catchError((err: HttpErrorResponse) => {
      // 419: CSRF expired — the cookie refreshes on this failed call, retry once.
      if (err.status === 419 && !req.headers.get('X-Retry-After-419')) {
        return next(req.clone({ headers: req.headers.set('X-Retry-After-419', '1') }));
      }
      if (err.status === 401 && !req.url.includes('/auth/login')) {
        const target = router.url;
        if (target !== '/login') {
          void router.navigate(['/login'], { queryParams: { returnUrl: target } });
        }
        return throwError(() => err);
      }
      if (err.status === 403) {
        void router.navigate(['/forbidden']);
        return throwError(() => err);
      }
      if (err.status === 404 && !req.url.includes('/api/')) {
        void router.navigate(['/not-found']);
        return throwError(() => err);
      }
      if (err.status === 0) {
        offline.set(true);
        toast.error('errors.network');
      } else if (err.status >= 500) {
        toast.error('errors.500');
      }
      return throwError(() => err);
    }),
  );
};

/** Thin global progress line (plan 18). */
export const progressInterceptor: HttpInterceptorFn = (req, next) => {
  if (req.method === 'GET') {
    return next(req);
  }
  return next(req).pipe(finalize(() => void 0));
};
