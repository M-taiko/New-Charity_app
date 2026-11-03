import type { Routes } from '@angular/router';

export const REPORTS_ROUTES: Routes = [
  { path: '', loadComponent: () => import('./pages/dashboard/dashboard.component').then((m) => m.ReportsDashboardPage) },
  { path: 'researchers', loadComponent: () => import('./pages/researchers/researchers.component').then((m) => m.ResearchersPage) },
  { path: 'case-expenses', loadComponent: () => import('./pages/case-expenses/case-expenses.component').then((m) => m.CaseExpensesPage) },
  { path: 'agents-balance', loadComponent: () => import('./pages/agents-balance/agents-balance.component').then((m) => m.AgentsBalancePage) },
  { path: 'items', loadComponent: () => import('./pages/items/items.component').then((m) => m.ItemsReportPage) },
  { path: 'directions', loadComponent: () => import('./pages/directions/directions.component').then((m) => m.DirectionsReportPage) },
  { path: 'governorates', loadComponent: () => import('./pages/governorates/governorates.component').then((m) => m.GovernoratesPage) },
  { path: 'reconciliation', loadComponent: () => import('./pages/reconciliation/reconciliation.component').then((m) => m.ReconciliationPage) },
];
