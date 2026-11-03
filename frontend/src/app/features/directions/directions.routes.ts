import type { Routes } from '@angular/router';

export const DIRECTIONS_ROUTES: Routes = [
  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.DirectionListPage) },
];
