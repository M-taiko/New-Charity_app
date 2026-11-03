import { readFileSync } from 'node:fs';

const data = JSON.parse(readFileSync('lint.json', 'utf8'));
const myFiles = [
  'features/social-cases/pages/detail/detail.component.ts',
  'features/expenses/pages/edit-request-detail/edit-request-detail.component.ts',
  'features/expenses/pages/edit-request/edit-request.component.ts',
  'features/expenses/pages/new/new.component.ts',
  'features/settings/pages/list/list.component.ts',
  'features/social-cases/pages/new/new.component.ts',
  'features/expenses/pages/detail/detail.component.ts',
  'features/hr/pages/attendance/attendance.component.ts',
  'features/hr/pages/kpi/kpi.component.ts',
  'features/hr/pages/employee-form/employee-form.component.ts',
  'features/hr/pages/salaries/salaries.component.ts',
  'features/hr/pages/salary-edit/salary-edit.component.ts',
  'features/social-cases/pages/list/list.component.ts',
  'features/social-cases/pages/mine/mine.component.ts',
  'features/hr/pages/employees/employees.component.ts',
  'features/hr/pages/salary-detail/salary-detail.component.ts',
  'features/hr/pages/salary-history/salary-history.component.ts',
  'features/expenses/pages/edit-requests/edit-requests.component.ts',
  'features/expenses/pages/list/list.component.ts',
  'features/expenses/pages/mine/mine.component.ts',
];
for (const f of data) {
  const norm = f.filePath.split('\\').join('/');
  const rel = norm.substring(norm.indexOf('src/app/') + 8);
  if (myFiles.includes(rel)) {
    for (const m of f.messages) {
      console.log(`${rel}:${m.line}:${m.column} [${m.severity}] ${m.ruleId ?? '(null)'} — ${m.message}`);
    }
  }
}
