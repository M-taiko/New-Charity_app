import type { Routes } from '@angular/router';

export const TRANSFERS_ROUTES: Routes = [
  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.TransferListPage) },
  { path: 'new', loadComponent: () => import('./pages/new/new.component').then((m) => m.TransferFormPage) },
  { path: ':id', loadComponent: () => import('./pages/detail/detail.component').then((m) => m.TransferDetailPage) },
];
