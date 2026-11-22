import type { Routes } from '@angular/router';
import { unsavedChangesGuard } from '@core/guards/unsaved-changes.guard';

export const TASKS_ROUTES: Routes = [
  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.TaskListPage) },
  { canDeactivate: [unsavedChangesGuard], path: 'new', loadComponent: () => import('./pages/new/new.component').then((m) => m.TaskFormPage) },
  { path: ':id', loadComponent: () => import('./pages/detail/detail.component').then((m) => m.TaskDetailPage) },
];
