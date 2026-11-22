import type { Routes } from '@angular/router';
import { unsavedChangesGuard } from '@core/guards/unsaved-changes.guard';

export const TREASURY_ROUTES: Routes = [
  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.TreasuryListPage) },
  { canDeactivate: [unsavedChangesGuard], path: 'new', loadComponent: () => import('./pages/new/new.component').then((m) => m.TreasuryFormPage) },
  { canDeactivate: [unsavedChangesGuard], path: 'transfer', loadComponent: () => import('./pages/transfer/transfer.component').then((m) => m.TreasuryTransferPage) },
  { canDeactivate: [unsavedChangesGuard], path: ':id/edit', loadComponent: () => import('./pages/edit/edit.component').then((m) => m.TreasuryFormPage) },
  { path: ':id', loadComponent: () => import('./pages/detail/detail.component').then((m) => m.TreasuryDetailPage) },
];
