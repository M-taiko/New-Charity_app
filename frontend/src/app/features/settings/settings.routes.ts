import type { Routes } from '@angular/router';

export const SETTINGS_ROUTES: Routes = [
  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.SettingsPage) },
];
