import type { Routes } from '@angular/router';

export const USERS_ROUTES: Routes = [
  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.UserListPage) },
  { path: 'new', loadComponent: () => import('./pages/new/new.component').then((m) => m.UserFormPage) },
  { path: ':id/edit', loadComponent: () => import('./pages/edit/edit.component').then((m) => m.UserFormPage) },
  { path: ':id', loadComponent: () => import('./pages/detail/detail.component').then((m) => m.UserDetailPage) },
];
