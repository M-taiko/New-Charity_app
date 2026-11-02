import { Routes } from '@angular/router';

export const BROADCASTS_ROUTES: Routes = [
  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.BroadcastsPage) },
];
