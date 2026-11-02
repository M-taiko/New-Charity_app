import { Routes } from '@angular/router';

export const ACTIVITY_ROUTES: Routes = [
  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.ActivityListPage) },
  { path: 'mine', loadComponent: () => import('./pages/mine/mine.component').then((m) => m.MyActivityPage) },
];
