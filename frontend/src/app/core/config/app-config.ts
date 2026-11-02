/** Central configuration. The API lives on the same origin under /api/v1 (plan 7.3). */
export const APP_CONFIG = {
  apiBase: '/api/v1',
  notificationsPollMs: 15_000,
  chatPollMs: 5_000,
  dateFormatLocale: 'ar-EG',
  numbersLocale: 'en-EG',
  currencySuffix: 'ج.م',
} as const;
