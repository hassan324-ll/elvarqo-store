/* Offline conversion only: never edits the input directory or fetches remote URLs. */
const fs = require('node:fs/promises');
const path = require('node:path');
const sharp = require('sharp');

async function main() {
  const [inputArg, outputArg] = process.argv.slice(2);
  if (!inputArg || !outputArg) throw new Error('Usage: node media/convert-import-avif.cjs INPUT_DIRECTORY NEW_OUTPUT_DIRECTORY');
  const input = await fs.realpath(inputArg);
  const output = path.resolve(outputArg);
  if (output === input || output.startsWith(input + path.sep)) throw new Error('Output must be separate from the original input directory.');
  if (! (await fs.stat(input)).isDirectory()) throw new Error('Input must be a directory.');
  try { await fs.access(output); throw new Error('Output already exists. Choose a new directory; originals and previous conversions are never overwritten.'); }
  catch (error) { if (error.code !== 'ENOENT') throw error; }
  const files = [];
  async function visit(directory) {
    for (const entry of await fs.readdir(directory, { withFileTypes: true })) {
      if (entry.isSymbolicLink()) throw new Error('Symbolic links are not allowed.');
      const source = path.join(directory, entry.name);
      if (entry.isDirectory()) await visit(source);
      else if (entry.isFile() && path.extname(entry.name).toLowerCase() === '.avif') files.push(source);
      if (files.length > 200) throw new Error('Maximum 200 AVIF images per conversion.');
    }
  }
  await visit(input);
  if (!files.length) throw new Error('No AVIF images found.');
  await fs.mkdir(output, { recursive: false });
  const report = [];
  let failed = false;
  for (const source of files) {
    const relative = path.relative(input, source);
    const destination = path.join(output, relative.replace(/\.avif$/i, '.webp'));
    try {
      if ((await fs.stat(source)).size > 8 * 1024 * 1024) throw new Error('Source exceeds 8 MiB.');
      const metadata = await sharp(source, { limitInputPixels: 24000000, failOn: 'error' }).metadata();
      if ((metadata.pages || 1) !== 1) throw new Error('Animated images are not supported.');
      await fs.mkdir(path.dirname(destination), { recursive: true });
      await sharp(source, { limitInputPixels: 24000000, failOn: 'error' }).rotate().webp({ lossless: true }).toFile(destination);
      report.push({ source: relative, output: path.relative(output, destination), status: 'converted' });
    } catch (error) {
      failed = true;
      report.push({ source: relative, status: 'failed', error: error.message });
    }
  }
  await fs.writeFile(path.join(output, 'conversion-report.json'), JSON.stringify(report, null, 2) + '\n', { flag: 'wx' });
  console.log(`${report.filter((entry) => entry.status === 'converted').length} converted; ${report.filter((entry) => entry.status === 'failed').length} failed. Originals unchanged. ZIP only the image files, excluding conversion-report.json; update JSON image references before preview.`);
  if (failed) process.exitCode = 1;
}
main().catch((error) => { console.error(error.message); process.exitCode = 1; });
