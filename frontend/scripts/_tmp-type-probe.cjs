const ts = require('typescript');
const path = require('path');
const root = path.resolve(__dirname, '..');
const cfg = ts.readConfigFile(path.join(root, 'tsconfig.json'), ts.sys.readFile);
const parsed = ts.parseJsonConfigFileContent(cfg.config, ts.sys, root);
console.log('root fileNames count:', parsed.fileNames.length);
const program = ts.createProgram({
  rootNames: parsed.fileNames,
  options: { ...parsed.options, noEmit: true },
});
const target = path.join(root, 'src/app/shared/ui/form-dialog/form-dialog.component.ts');
const sf = program.getSourceFile(target);
if (!sf) {
  console.log('form-dialog NOT in root tsconfig program');
} else {
  const checker = program.getTypeChecker();
  const findNode = (n) => {
    if (ts.isImportSpecifier(n) && n.name.text === 'ApiErrorBody') return n;
    return ts.forEachChild(n, findNode);
  };
  const spec = findNode(sf);
  if (spec) {
    const t = checker.getTypeAtLocation(spec);
    console.log('ApiErrorBody flags hasAny:', !!(t.flags & ts.TypeFlags.Any), 'intrinsic:', t.intrinsicName);
    const sym = checker.getSymbolAtLocation(spec.name);
    console.log('symbol:', sym ? sym.getName() : 'NONE');
  } else {
    console.log('import specifier not found');
  }
  const diags = program.getSemanticDiagnostics(sf).slice(0, 10);
  for (const d of diags) console.log('diag:', ts.flattenDiagnosticMessageText(d.messageText, ' '));
}
