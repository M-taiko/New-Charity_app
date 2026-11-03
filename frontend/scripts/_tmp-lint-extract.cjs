const report = require('../lint.json');
const myFiles = [
  'src/app/shared/ui/data-table/data-table.component.ts',
  'src/app/core/http/interceptors.ts',
  'src/app/shared/ui/direction-picker/direction-picker.component.ts',
  'src/app/layout/shell/shell.component.ts',
  'src/app/app.config.ts',
  'src/app/core/http/api-client.ts',
  'src/app/features/errors/error-pages.ts',
  'src/app/layout/notification-bell/notification-bell.component.ts',
  'src/app/shared/ui/attachment-field/attachment-field.component.ts',
  'src/app/shared/ui/form-dialog/form-dialog.component.ts',
  'src/app/shared/ui/reason-dialog/reason-dialog.component.ts',
  'src/app/app.component.ts',
  'src/app/core/auth/auth.service.ts',
  'src/app/core/motion/motion.service.ts',
  'src/app/core/storage/local-storage.service.ts',
  'src/app/features/auth/login/login.component.ts',
  'src/app/shared/pipes/pipes.ts',
  'src/app/shared/ui/chart-card/chart-card.component.ts',
  'src/app/shared/ui/confirm-delete/confirm-delete.component.ts',
  'src/app/shared/ui/line-items-editor/line-items-editor.component.ts',
  'src/app/shared/ui/segmented-bar/segmented-bar.component.ts',
  'src/app/shared/ui/stat-tile/stat-tile.component.ts',
  'src/app/layout/user-menu/user-menu.component.ts',
];
const norm = (p) => p.replace(/\\/g, '/');
for (const f of myFiles) {
  const entry = report.find((r) => norm(r.filePath).endsWith(f));
  if (!entry) {
    console.log('NOT FOUND: ' + f);
    continue;
  }
  const msgs = entry.messages.filter((m) => m.severity === 1);
  console.log('=== ' + f + ' (' + msgs.length + ') ===');
  for (const m of msgs) console.log('  ' + m.line + ':' + m.column + ' [' + m.ruleId + '] ' + m.message);
}
