import type { Routes } from '@angular/router';

export const PROFILE_ROUTES: Routes = [
  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.ProfilePage) },
];
