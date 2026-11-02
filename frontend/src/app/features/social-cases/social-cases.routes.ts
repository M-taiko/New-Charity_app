import { Routes } from '@angular/router';

export const CASES_ROUTES: Routes = [
  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.CaseListPage) },
  { path: 'new', loadComponent: () => import('./pages/new/new.component').then((m) => m.CaseFormPage) },
  { path: 'mine', loadComponent: () => import('./pages/mine/mine.component').then((m) => m.MyCasesPage) },
  { path: ':id/edit', loadComponent: () => import('./pages/edit/edit.component').then((m) => m.CaseFormPage) },
  { path: ':id', loadComponent: () => import('./pages/detail/detail.component').then((m) => m.CaseDetailPage) },
];
