# Generates feature skeleton files (routes + pages) so the app compiles end to
# end. Pages get real structure immediately; detailed logic lands per feature.
import os

BASE = os.path.join(os.path.dirname(__file__), '..', 'src', 'app', 'features')

FEATURES = {
    'dashboard': {
        'const': 'DASHBOARD_ROUTES',
        'pages': [('dashboard', 'DashboardPage')],
    },
    'treasury': {
        'const': 'TREASURY_ROUTES',
        'pages': [
            ('list', 'TreasuryListPage'), ('new', 'TreasuryFormPage'),
            ('detail', 'TreasuryDetailPage'), ('edit', 'TreasuryFormPage'),
            ('transfer', 'TreasuryTransferPage'),
        ],
        'routes': [
            "  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.TreasuryListPage) },",
            "  { path: 'new', loadComponent: () => import('./pages/new/new.component').then((m) => m.TreasuryFormPage) },",
            "  { path: 'transfer', loadComponent: () => import('./pages/transfer/transfer.component').then((m) => m.TreasuryTransferPage) },",
            "  { path: ':id/edit', loadComponent: () => import('./pages/edit/edit.component').then((m) => m.TreasuryFormPage) },",
            "  { path: ':id', loadComponent: () => import('./pages/detail/detail.component').then((m) => m.TreasuryDetailPage) },",
        ],
    },
    'custodies': {
        'const': 'CUSTODIES_ROUTES',
        'pages': [
            ('list', 'CustodyListPage'), ('new', 'CustodyFormPage'),
            ('detail', 'CustodyDetailPage'), ('edit', 'CustodyFormPage'),
            ('mine', 'MyCustodiesPage'), ('tracking', 'TrackingPage'),
            ('movements', 'MovementsPage'), ('mine-movements', 'MyMovementsPage'),
            ('return-requests', 'ReturnRequestsPage'),
        ],
        'routes': [
            "  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.CustodyListPage) },",
            "  { path: 'new', loadComponent: () => import('./pages/new/new.component').then((m) => m.CustodyFormPage) },",
            "  { path: 'mine', loadComponent: () => import('./pages/mine/mine.component').then((m) => m.MyCustodiesPage) },",
            "  { path: 'mine/movements', loadComponent: () => import('./pages/mine-movements/mine-movements.component').then((m) => m.MyMovementsPage) },",
            "  { path: 'tracking', loadComponent: () => import('./pages/tracking/tracking.component').then((m) => m.TrackingPage) },",
            "  { path: 'movements', loadComponent: () => import('./pages/movements/movements.component').then((m) => m.MovementsPage) },",
            "  { path: 'return-requests', loadComponent: () => import('./pages/return-requests/return-requests.component').then((m) => m.ReturnRequestsPage) },",
            "  { path: ':id/edit', loadComponent: () => import('./pages/edit/edit.component').then((m) => m.CustodyFormPage) },",
            "  { path: ':id', loadComponent: () => import('./pages/detail/detail.component').then((m) => m.CustodyDetailPage) },",
        ],
    },
    'transfers': {
        'const': 'TRANSFERS_ROUTES',
        'pages': [('list', 'TransferListPage'), ('new', 'TransferFormPage'), ('detail', 'TransferDetailPage')],
        'routes': [
            "  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.TransferListPage) },",
            "  { path: 'new', loadComponent: () => import('./pages/new/new.component').then((m) => m.TransferFormPage) },",
            "  { path: ':id', loadComponent: () => import('./pages/detail/detail.component').then((m) => m.TransferDetailPage) },",
        ],
    },
    'expenses': {
        'const': 'EXPENSES_ROUTES',
        'pages': [
            ('list', 'ExpenseListPage'), ('new', 'ExpenseFormPage'),
            ('detail', 'ExpenseDetailPage'), ('edit', 'ExpenseFormPage'),
            ('mine', 'MyExpensesPage'), ('edit-request', 'EditRequestFormPage'),
            ('edit-requests', 'EditRequestsPage'), ('edit-request-detail', 'EditRequestDetailPage'),
        ],
        'routes': [
            "  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.ExpenseListPage) },",
            "  { path: 'new', loadComponent: () => import('./pages/new/new.component').then((m) => m.ExpenseFormPage) },",
            "  { path: 'mine', loadComponent: () => import('./pages/mine/mine.component').then((m) => m.MyExpensesPage) },",
            "  { path: 'edit-requests', loadComponent: () => import('./pages/edit-requests/edit-requests.component').then((m) => m.EditRequestsPage) },",
            "  { path: 'edit-requests/:id', loadComponent: () => import('./pages/edit-request-detail/edit-request-detail.component').then((m) => m.EditRequestDetailPage) },",
            "  { path: ':id/edit-request', loadComponent: () => import('./pages/edit-request/edit-request.component').then((m) => m.EditRequestFormPage) },",
            "  { path: ':id/edit', loadComponent: () => import('./pages/edit/edit.component').then((m) => m.ExpenseFormPage) },",
            "  { path: ':id', loadComponent: () => import('./pages/detail/detail.component').then((m) => m.ExpenseDetailPage) },",
        ],
    },
    'directions': {
        'const': 'DIRECTIONS_ROUTES',
        'pages': [('list', 'DirectionListPage')],
        'routes': [
            "  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.DirectionListPage) },",
        ],
    },
    'reports': {
        'const': 'REPORTS_ROUTES',
        'pages': [
            ('dashboard', 'ReportsDashboardPage'), ('researchers', 'ResearchersPage'),
            ('case-expenses', 'CaseExpensesPage'), ('agents-balance', 'AgentsBalancePage'),
            ('items', 'ItemsReportPage'), ('directions', 'DirectionsReportPage'),
            ('reconciliation', 'ReconciliationPage'), ('governorates', 'GovernoratesPage'),
        ],
        'routes': [
            "  { path: '', loadComponent: () => import('./pages/dashboard/dashboard.component').then((m) => m.ReportsDashboardPage) },",
            "  { path: 'researchers', loadComponent: () => import('./pages/researchers/researchers.component').then((m) => m.ResearchersPage) },",
            "  { path: 'case-expenses', loadComponent: () => import('./pages/case-expenses/case-expenses.component').then((m) => m.CaseExpensesPage) },",
            "  { path: 'agents-balance', loadComponent: () => import('./pages/agents-balance/agents-balance.component').then((m) => m.AgentsBalancePage) },",
            "  { path: 'items', loadComponent: () => import('./pages/items/items.component').then((m) => m.ItemsReportPage) },",
            "  { path: 'directions', loadComponent: () => import('./pages/directions/directions.component').then((m) => m.DirectionsReportPage) },",
            "  { path: 'governorates', loadComponent: () => import('./pages/governorates/governorates.component').then((m) => m.GovernoratesPage) },",
            "  { path: 'reconciliation', loadComponent: () => import('./pages/reconciliation/reconciliation.component').then((m) => m.ReconciliationPage) },",
        ],
    },
    'social-cases': {
        'const': 'CASES_ROUTES',
        'pages': [('list', 'CaseListPage'), ('new', 'CaseFormPage'), ('detail', 'CaseDetailPage'), ('edit', 'CaseFormPage'), ('mine', 'MyCasesPage')],
        'routes': [
            "  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.CaseListPage) },",
            "  { path: 'new', loadComponent: () => import('./pages/new/new.component').then((m) => m.CaseFormPage) },",
            "  { path: 'mine', loadComponent: () => import('./pages/mine/mine.component').then((m) => m.MyCasesPage) },",
            "  { path: ':id/edit', loadComponent: () => import('./pages/edit/edit.component').then((m) => m.CaseFormPage) },",
            "  { path: ':id', loadComponent: () => import('./pages/detail/detail.component').then((m) => m.CaseDetailPage) },",
        ],
    },
    'hr': {
        'const': 'HR_ROUTES',
        'pages': [
            ('dashboard', 'HrDashboardPage'), ('employees', 'EmployeesPage'),
            ('employee-form', 'EmployeeFormPage'), ('attendance', 'AttendancePage'),
            ('kpi', 'KpiPage'), ('salaries', 'SalariesPage'),
            ('salary-detail', 'SalaryDetailPage'), ('salary-edit', 'SalaryEditPage'),
            ('salary-history', 'SalaryHistoryPage'),
        ],
        'routes': [
            "  { path: '', loadComponent: () => import('./pages/dashboard/dashboard.component').then((m) => m.HrDashboardPage) },",
            "  { path: 'employees', loadComponent: () => import('./pages/employees/employees.component').then((m) => m.EmployeesPage) },",
            "  { path: 'employees/new', loadComponent: () => import('./pages/employee-form/employee-form.component').then((m) => m.EmployeeFormPage) },",
            "  { path: 'employees/:id/edit', loadComponent: () => import('./pages/employee-form/employee-form.component').then((m) => m.EmployeeFormPage) },",
            "  { path: 'employees/:id/salaries', loadComponent: () => import('./pages/salary-history/salary-history.component').then((m) => m.SalaryHistoryPage) },",
            "  { path: 'attendance', loadComponent: () => import('./pages/attendance/attendance.component').then((m) => m.AttendancePage) },",
            "  { path: 'kpi', loadComponent: () => import('./pages/kpi/kpi.component').then((m) => m.KpiPage) },",
            "  { path: 'salaries', loadComponent: () => import('./pages/salaries/salaries.component').then((m) => m.SalariesPage) },",
            "  { path: 'salaries/:id/edit', loadComponent: () => import('./pages/salary-edit/salary-edit.component').then((m) => m.SalaryEditPage) },",
            "  { path: 'salaries/:id', loadComponent: () => import('./pages/salary-detail/salary-detail.component').then((m) => m.SalaryDetailPage) },",
        ],
    },
    'procurement': {
        'const': 'PROCUREMENT_ROUTES',
        'pages': [
            ('purchase-list', 'PurchaseListPage'), ('purchase-new', 'PurchaseFormPage'),
            ('purchase-detail', 'PurchaseDetailPage'), ('maintenance-list', 'MaintenanceListPage'),
            ('maintenance-new', 'MaintenanceFormPage'), ('maintenance-detail', 'MaintenanceDetailPage'),
            ('suppliers', 'SuppliersPage'),
        ],
        'routes': [
            "  { path: 'purchase', loadComponent: () => import('./pages/purchase-list/purchase-list.component').then((m) => m.PurchaseListPage) },",
            "  { path: 'purchase/new', loadComponent: () => import('./pages/purchase-new/purchase-new.component').then((m) => m.PurchaseFormPage) },",
            "  { path: 'purchase/:id', loadComponent: () => import('./pages/purchase-detail/purchase-detail.component').then((m) => m.PurchaseDetailPage) },",
            "  { path: 'maintenance', loadComponent: () => import('./pages/maintenance-list/maintenance-list.component').then((m) => m.MaintenanceListPage) },",
            "  { path: 'maintenance/new', loadComponent: () => import('./pages/maintenance-new/maintenance-new.component').then((m) => m.MaintenanceFormPage) },",
            "  { path: 'maintenance/:id', loadComponent: () => import('./pages/maintenance-detail/maintenance-detail.component').then((m) => m.MaintenanceDetailPage) },",
            "  { path: 'suppliers', loadComponent: () => import('./pages/suppliers/suppliers.component').then((m) => m.SuppliersPage) },",
        ],
    },
    'tasks': {
        'const': 'TASKS_ROUTES',
        'pages': [('list', 'TaskListPage'), ('new', 'TaskFormPage'), ('detail', 'TaskDetailPage')],
        'routes': [
            "  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.TaskListPage) },",
            "  { path: 'new', loadComponent: () => import('./pages/new/new.component').then((m) => m.TaskFormPage) },",
            "  { path: ':id', loadComponent: () => import('./pages/detail/detail.component').then((m) => m.TaskDetailPage) },",
        ],
    },
    'chat': {
        'const': 'CHAT_ROUTES',
        'pages': [('chat', 'ChatPage')],
        'routes': [
            "  { path: '', loadComponent: () => import('./pages/chat/chat.component').then((m) => m.ChatPage) },",
        ],
    },
    'notifications': {
        'const': 'NOTIFICATIONS_ROUTES',
        'pages': [('list', 'NotificationsPage')],
        'routes': [
            "  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.NotificationsPage) },",
        ],
    },
    'broadcasts': {
        'const': 'BROADCASTS_ROUTES',
        'pages': [('list', 'BroadcastsPage')],
        'routes': [
            "  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.BroadcastsPage) },",
        ],
    },
    'activity': {
        'const': 'ACTIVITY_ROUTES',
        'pages': [('list', 'ActivityListPage'), ('mine', 'MyActivityPage')],
        'routes': [
            "  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.ActivityListPage) },",
            "  { path: 'mine', loadComponent: () => import('./pages/mine/mine.component').then((m) => m.MyActivityPage) },",
        ],
    },
    'users': {
        'const': 'USERS_ROUTES',
        'pages': [('list', 'UserListPage'), ('new', 'UserFormPage'), ('detail', 'UserDetailPage'), ('edit', 'UserFormPage')],
        'routes': [
            "  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.UserListPage) },",
            "  { path: 'new', loadComponent: () => import('./pages/new/new.component').then((m) => m.UserFormPage) },",
            "  { path: ':id/edit', loadComponent: () => import('./pages/edit/edit.component').then((m) => m.UserFormPage) },",
            "  { path: ':id', loadComponent: () => import('./pages/detail/detail.component').then((m) => m.UserDetailPage) },",
        ],
    },
    'settings': {
        'const': 'SETTINGS_ROUTES',
        'pages': [('list', 'SettingsPage')],
        'routes': [
            "  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.SettingsPage) },",
        ],
    },
    'profile': {
        'const': 'PROFILE_ROUTES',
        'pages': [('list', 'ProfilePage')],
        'routes': [
            "  { path: '', loadComponent: () => import('./pages/list/list.component').then((m) => m.ProfilePage) },",
        ],
    },
}

TITLES = {
    'dashboard': 'dashboard.title', 'treasury': 'treasury.list.title', 'custodies': 'custody.list.title',
    'transfers': 'transfer.list.title', 'expenses': 'expense.list.title', 'directions': 'direction.list.title',
    'reports': 'report.dashboard.title', 'social-cases': 'case.list.title', 'hr': 'hr.dashboard.title',
    'procurement': 'procurement.purchase.list_title', 'tasks': 'task.list.title', 'chat': 'chat.title',
    'notifications': 'notification.title', 'broadcasts': 'broadcast.title', 'activity': 'activity.title',
    'users': 'user.list.title', 'settings': 'settings.title', 'profile': 'profile.title',
}

for feature, spec in FEATURES.items():
    feature_dir = os.path.join(BASE, feature)
    os.makedirs(feature_dir, exist_ok=True)
    routes_path = os.path.join(feature_dir, f'{feature}.routes.ts')
    if not os.path.exists(routes_path):
        with open(routes_path, 'w', encoding='utf-8', newline='\n') as f:
            f.write(f"import {{ Routes }} from '@angular/router';\n\nexport const {spec['const']}: Routes = [\n")
            f.write('\n'.join(spec.get('routes', [])))
            f.write('\n];\n')

    for slug, cls in spec['pages']:
        page_dir = os.path.join(feature_dir, 'pages', slug)
        comp_path = os.path.join(page_dir, f'{slug}.component.ts')
        if os.path.exists(comp_path):
            continue
        os.makedirs(page_dir, exist_ok=True)
        with open(comp_path, 'w', encoding='utf-8', newline='\n') as f:
            f.write(f"""import {{ ChangeDetectionStrategy, Component }} from '@angular/core';
import {{ TranslateModule }} from '@ngx-translate/core';
import {{ PageHeaderComponent }} from '@shared/ui/page-header/page-header.component';

@Component({{
  selector: 'app-{feature.replace('_', '-')}-{slug.replace('_', '-')}',
  standalone: true,
  imports: [TranslateModule, PageHeaderComponent],
  template: `
    <app-page-header title="{TITLES.get(feature, 'app.name')}" />
    <p>TODO</p>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
}})
export class {cls} {{}}
""")

print('feature skeletons generated')
