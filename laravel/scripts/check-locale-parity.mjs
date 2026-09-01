// Asserts en.json and ar.json expose exactly the same key paths.
//
// A key present in one dictionary but not the other is a real defect: the UI
// reads dictionary paths directly, so a missing Arabic key renders `undefined`
// (or falls back to raw English) for Arabic users, and a missing English key
// does the same in reverse. This is cheap to check and easy to regress.

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const localesDir = resolve(here, '..', 'resources', 'js', 'locales');

function loadDictionary(name) {
  const path = resolve(localesDir, `${name}.json`);

  try {
    return JSON.parse(readFileSync(path, 'utf8'));
  } catch (error) {
    console.error(`Could not read or parse ${path}: ${error.message}`);
    process.exit(1);
  }
}

function collectKeyPaths(value, prefix = '', out = new Set()) {
  if (value === null || typeof value !== 'object' || Array.isArray(value)) {
    out.add(prefix);
    return out;
  }

  for (const [key, nested] of Object.entries(value)) {
    collectKeyPaths(nested, prefix ? `${prefix}.${key}` : key, out);
  }

  return out;
}

function report(label, keys) {
  const sample = [...keys].sort().slice(0, 25);

  console.error(`\n${label} (${keys.size}):`);
  for (const key of sample) {
    console.error(`  - ${key}`);
  }
  if (keys.size > sample.length) {
    console.error(`  … and ${keys.size - sample.length} more`);
  }
}

const en = collectKeyPaths(loadDictionary('en'));
const ar = collectKeyPaths(loadDictionary('ar'));

const missingInArabic = new Set([...en].filter((key) => !ar.has(key)));
const missingInEnglish = new Set([...ar].filter((key) => !en.has(key)));

if (missingInArabic.size === 0 && missingInEnglish.size === 0) {
  console.log(`Locale parity OK — ${en.size} keys present in both en.json and ar.json.`);
  process.exit(0);
}

console.error('Locale dictionaries are out of sync.');

if (missingInArabic.size > 0) {
  report('Present in en.json but missing from ar.json', missingInArabic);
}

if (missingInEnglish.size > 0) {
  report('Present in ar.json but missing from en.json', missingInEnglish);
}

console.error('\nAdd the missing keys to both dictionaries.');
process.exit(1);
