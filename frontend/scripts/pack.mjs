// pack (plan 8.1): copies the built SPA into ../public/app, writes the
// .htaccess (compression + cache headers) and a checksum list. The owner
// uploads the folder; nothing runs on the server except PHP.
import { cpSync, mkdirSync, rmSync, writeFileSync, readdirSync, statSync, readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { resolve, dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..');
const src = resolve(root, 'dist/app/browser');
const dest = resolve(root, '..', 'public', 'app');

if (!readdirSync(src).length) {
  console.error('pack: nothing in dist/app/browser — run npm run build first');
  process.exit(1);
}

rmSync(dest, { recursive: true, force: true });
mkdirSync(dest, { recursive: true });
cpSync(src, dest, { recursive: true });

const htaccess = `# Naguib Selim Charity SPA — served as static files by PHP only (plan 8.2)
# SPA fallback: deep links to the app router (index.html); assets served directly
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /app/
  RewriteCond %{REQUEST_FILENAME} -f [OR]
  RewriteCond %{REQUEST_FILENAME} -d
  RewriteRule ^ - [L]
  RewriteRule ^ index.html [L]
</IfModule>
<IfModule mod_mime.c>
  AddType text/javascript .js
  AddType text/css .css
  AddType font/woff2 .woff2
  AddType image/svg+xml .svg
</IfModule>
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json image/svg+xml
</IfModule>
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/css "access plus 1 year"
  ExpiresByType application/javascript "access plus 1 year"
  ExpiresByType image/svg+xml "access plus 1 year"
  ExpiresByType font/woff2 "access plus 1 year"
  ExpiresByType text/html "access plus 0 seconds"
</IfModule>
<IfModule mod_headers.c>
  <FilesMatch "\\.html$">
    Header set Cache-Control "no-cache, no-store, must-revalidate"
  </FilesMatch>
  <FilesMatch "\\.(js|css|woff2|svg)$">
    Header set Cache-Control "public, max-age=31536000, immutable"
  </FilesMatch>
</IfModule>
`;
writeFileSync(join(dest, '.htaccess'), htaccess);

function* files(dir) {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) {
      yield* files(full);
    } else {
      yield full;
    }
  }
}

const lines = [];
for (const file of files(dest)) {
  const hash = createHash('sha256').update(readFileSync(file)).digest('hex').slice(0, 16);
  lines.push(`${hash}  ${relative(dest, file).split('\\').join('/')}`);
}
writeFileSync(join(dest, 'checksums.txt'), lines.join('\n') + '\n');
console.log(`pack: ${lines.length} files -> public/app (checksums.txt written)`);
