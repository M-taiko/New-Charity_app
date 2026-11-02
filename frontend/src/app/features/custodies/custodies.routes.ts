import { Routes } from '@angular/router';

export const CUSTODIES_ROUTES: Routes = [
  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.CustodyListPage) },
  { path: 'new', loadComponent: () => import('./pages/new/new.component').then((m) => m.CustodyFormPage) },
  { path: 'mine', loadComponent: () => import('./pages/mine/mine.component').then((m) => m.MyCustodiesPage) },
  { path: 'mine/movements', loadComponent: () => import('./pages/mine-movements/mine-movements.component').then((m) => m.MyMovementsPage) },
  { path: 'tracking', loadComponent: () => import('./pages/tracking/tracking.component').then((m) => m.TrackingPage) },
  { path: 'movements', loadComponent: () => import('./pages/movements/movements.component').then((m) => m.MovementsPage) },
  { path: 'return-requests', loadComponent: () => import('./pages/return-requests/return-requests.component').then((m) => m.ReturnRequestsPage) },
  { path: ':id/edit', loadComponent: () => import('./pages/edit/edit.component').then((m) => m.CustodyEditPage) },
  { path: ':id', loadComponent: () => import('./pages/detail/detail.component').then((m) => m.CustodyDetailPage) },
];
