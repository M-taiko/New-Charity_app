import type { Routes } from '@angular/router';
import { unsavedChangesGuard } from '@core/guards/unsaved-changes.guard';

export const TRANSFERS_ROUTES: Routes = [
  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.TransferListPage) },
  { canDeactivate: [unsavedChangesGuard], path: 'new', loadComponent: () => import('./pages/new/new.component').then((m) => m.TransferFormPage) },
  { path: ':id', loadComponent: () => import('./pages/detail/detail.component').then((m) => m.TransferDetailPage) },
];
