/**
 * Central configuration. The API lives on the same origin under /api/v1 (plan 7.3).
 * The currency label is NOT configured here — its single source is the
 * `common.egp` key in assets/i18n/ar.json, read by the money pipe and
 * stat-tile (T5-05); no other file may carry an Arabic literal.
 */
export const APP_CONFIG = {
  apiBase: '/api/v1',
  notificationsPollMs: 15_000,
  chatPollMs: 5_000,
  dateFormatLocale: 'ar-EG',
  numbersLocale: 'en-EG',
} as const;
