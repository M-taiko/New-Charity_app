import type { Routes } from '@angular/router';

export const PROCUREMENT_ROUTES: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'purchase' },
  { path: 'purchase', loadComponent: () => import('./pages/purchase-list/purchase-list.component').then((m) => m.PurchaseListPage) },
  { path: 'purchase/new', loadComponent: () => import('./pages/purchase-new/purchase-new.component').then((m) => m.PurchaseFormPage) },
  { path: 'purchase/:id', loadComponent: () => import('./pages/purchase-detail/purchase-detail.component').then((m) => m.PurchaseDetailPage) },
  { path: 'maintenance', loadComponent: () => import('./pages/maintenance-list/maintenance-list.component').then((m) => m.MaintenanceListPage) },
  { path: 'maintenance/new', loadComponent: () => import('./pages/maintenance-new/maintenance-new.component').then((m) => m.MaintenanceFormPage) },
  { path: 'maintenance/:id', loadComponent: () => import('./pages/maintenance-detail/maintenance-detail.component').then((m) => m.MaintenanceDetailPage) },
  { path: 'suppliers', loadComponent: () => import('./pages/suppliers/suppliers.component').then((m) => m.SuppliersPage) },
];
