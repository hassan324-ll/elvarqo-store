import { copyFile, mkdir, readdir, readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
const root = fileURLToPath(new URL('../', import.meta.url));
const app = process.argv[2];
if (!['storefront', 'admin'].includes(app))
  throw new Error('Choose storefront or admin');
const source = path.join(root, 'shared/ui');
const target = path.join(root, app, 'src/ui');
await mkdir(target, { recursive: true });
for (const name of await readdir(source)) {
  const next = await readFile(path.join(source, name));
  let previous;
  try {
    previous = await readFile(path.join(target, name));
  } catch {}
  if (!previous?.equals(next))
    await copyFile(path.join(source, name), path.join(target, name));
}
console.log(`Shared UI synchronized into ${app}/src/ui`);
