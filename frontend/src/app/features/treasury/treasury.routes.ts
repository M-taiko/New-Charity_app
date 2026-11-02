import { Routes } from '@angular/router';

export const TREASURY_ROUTES: Routes = [
  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.TreasuryListPage) },
  { path: 'new', loadComponent: () => import('./pages/new/new.component').then((m) => m.TreasuryFormPage) },
  { path: 'transfer', loadComponent: () => import('./pages/transfer/transfer.component').then((m) => m.TreasuryTransferPage) },
  { path: ':id/edit', loadComponent: () => import('./pages/edit/edit.component').then((m) => m.TreasuryFormPage) },
  { path: ':id', loadComponent: () => import('./pages/detail/detail.component').then((m) => m.TreasuryDetailPage) },
];
