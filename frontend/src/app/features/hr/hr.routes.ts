import { Routes } from '@angular/router';

export const HR_ROUTES: Routes = [
  { path: '', loadComponent: () => import('./pages/dashboard/dashboard.component').then((m) => m.HrDashboardPage) },
  { path: 'employees', loadComponent: () => import('./pages/employees/employees.component').then((m) => m.EmployeesPage) },
  { path: 'employees/new', loadComponent: () => import('./pages/employee-form/employee-form.component').then((m) => m.EmployeeFormPage) },
  { path: 'employees/:id/edit', loadComponent: () => import('./pages/employee-form/employee-form.component').then((m) => m.EmployeeFormPage) },
  { path: 'employees/:id/salaries', loadComponent: () => import('./pages/salary-history/salary-history.component').then((m) => m.SalaryHistoryPage) },
  { path: 'attendance', loadComponent: () => import('./pages/attendance/attendance.component').then((m) => m.AttendancePage) },
  { path: 'kpi', loadComponent: () => import('./pages/kpi/kpi.component').then((m) => m.KpiPage) },
  { path: 'salaries', loadComponent: () => import('./pages/salaries/salaries.component').then((m) => m.SalariesPage) },
  { path: 'salaries/:id/edit', loadComponent: () => import('./pages/salary-edit/salary-edit.component').then((m) => m.SalaryEditPage) },
  { path: 'salaries/:id', loadComponent: () => import('./pages/salary-detail/salary-detail.component').then((m) => m.SalaryDetailPage) },
];
