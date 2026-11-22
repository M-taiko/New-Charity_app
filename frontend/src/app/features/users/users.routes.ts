import type { Routes } from '@angular/router';
import { unsavedChangesGuard } from '@core/guards/unsaved-changes.guard';

export const USERS_ROUTES: Routes = [
  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.UserListPage) },
  { canDeactivate: [unsavedChangesGuard], path: 'new', loadComponent: () => import('./pages/new/new.component').then((m) => m.UserFormPage) },
  { canDeactivate: [unsavedChangesGuard], path: ':id/edit', loadComponent: () => import('./pages/edit/edit.component').then((m) => m.UserFormPage) },
  { path: ':id', loadComponent: () => import('./pages/detail/detail.component').then((m) => m.UserDetailPage) },
];
