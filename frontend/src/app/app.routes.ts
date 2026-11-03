import type { Routes } from '@angular/router';
import { authGuard, guestGuard } from '@core/auth/auth.guard';

export const routes: Routes = [
  {
    path: 'login',
    canActivate: [guestGuard],
    loadChildren: () => import('@features/auth/auth.routes').then((m) => m.AUTH_ROUTES),
  },
  {
    path: '',
    canActivate: [authGuard],
    loadComponent: () => import('@layout/shell/shell.component').then((m) => m.ShellComponent),
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
      {
        path: 'dashboard',
        loadChildren: () => import('@features/dashboard/dashboard.routes').then((m) => m.DASHBOARD_ROUTES),
      },
      {
        path: 'treasury',
        loadChildren: () => import('@features/treasury/treasury.routes').then((m) => m.TREASURY_ROUTES),
      },
      {
        path: 'custodies',
        loadChildren: () => import('@features/custodies/custodies.routes').then((m) => m.CUSTODIES_ROUTES),
      },
      {
        path: 'transfers',
        loadChildren: () => import('@features/transfers/transfers.routes').then((m) => m.TRANSFERS_ROUTES),
      },
      {
        path: 'expenses',
        loadChildren: () => import('@features/expenses/expenses.routes').then((m) => m.EXPENSES_ROUTES),
      },
      {
        path: 'directions',
        loadChildren: () => import('@features/directions/directions.routes').then((m) => m.DIRECTIONS_ROUTES),
      },
      {
        path: 'reports',
        loadChildren: () => import('@features/reports/reports.routes').then((m) => m.REPORTS_ROUTES),
      },
      {
        path: 'cases',
        loadChildren: () => import('@features/social-cases/social-cases.routes').then((m) => m.CASES_ROUTES),
      },
      {
        path: 'social-cases',
        loadChildren: () => import('@features/social-cases/social-cases.routes').then((m) => m.CASES_ROUTES),
      },
      { path: 'hr', loadChildren: () => import('@features/hr/hr.routes').then((m) => m.HR_ROUTES) },
      {
        path: 'procurement',
        loadChildren: () => import('@features/procurement/procurement.routes').then((m) => m.PROCUREMENT_ROUTES),
      },
      { path: 'tasks', loadChildren: () => import('@features/tasks/tasks.routes').then((m) => m.TASKS_ROUTES) },
      { path: 'chat', loadChildren: () => import('@features/chat/chat.routes').then((m) => m.CHAT_ROUTES) },
      {
        path: 'notifications',
        loadChildren: () => import('@features/notifications/notifications.routes').then((m) => m.NOTIFICATIONS_ROUTES),
      },
      {
        path: 'broadcasts',
        loadChildren: () => import('@features/broadcasts/broadcasts.routes').then((m) => m.BROADCASTS_ROUTES),
      },
      {
        path: 'activity',
        loadChildren: () => import('@features/activity/activity.routes').then((m) => m.ACTIVITY_ROUTES),
      },
      { path: 'users', loadChildren: () => import('@features/users/users.routes').then((m) => m.USERS_ROUTES) },
      {
        path: 'settings',
        loadChildren: () => import('@features/settings/settings.routes').then((m) => m.SETTINGS_ROUTES),
      },
      {
        path: 'profile',
        loadChildren: () => import('@features/profile/profile.routes').then((m) => m.PROFILE_ROUTES),
      },
    ],
  },
  // Error pages (plan 20)
  { path: 'forbidden', loadComponent: () => import('@features/errors/error-pages').then((m) => m.ForbiddenComponent) },
  { path: 'not-found', loadComponent: () => import('@features/errors/error-pages').then((m) => m.NotFoundComponent) },
  { path: '**', redirectTo: 'not-found' },
];
