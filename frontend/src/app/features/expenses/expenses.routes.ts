import { Routes } from '@angular/router';

export const EXPENSES_ROUTES: Routes = [
  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.ExpenseListPage) },
  { path: 'new', loadComponent: () => import('./pages/new/new.component').then((m) => m.ExpenseFormPage) },
  { path: 'mine', loadComponent: () => import('./pages/mine/mine.component').then((m) => m.MyExpensesPage) },
  { path: 'edit-requests', loadComponent: () => import('./pages/edit-requests/edit-requests.component').then((m) => m.EditRequestsPage) },
  { path: 'edit-requests/:id', loadComponent: () => import('./pages/edit-request-detail/edit-request-detail.component').then((m) => m.EditRequestDetailPage) },
  { path: ':id/edit-request', loadComponent: () => import('./pages/edit-request/edit-request.component').then((m) => m.EditRequestFormPage) },
  { path: ':id/edit', loadComponent: () => import('./pages/edit/edit.component').then((m) => m.ExpenseFormPage) },
  { path: ':id', loadComponent: () => import('./pages/detail/detail.component').then((m) => m.ExpenseDetailPage) },
];
