import { Routes } from '@angular/router';

export const TASKS_ROUTES: Routes = [
  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.TaskListPage) },
  { path: 'new', loadComponent: () => import('./pages/new/new.component').then((m) => m.TaskFormPage) },
  { path: ':id', loadComponent: () => import('./pages/detail/detail.component').then((m) => m.TaskDetailPage) },
];
