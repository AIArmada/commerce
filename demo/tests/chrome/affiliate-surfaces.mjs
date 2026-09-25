#!/usr/bin/env node
/**
 * Real-Chrome smoke test for the fresh affiliates + affiliate-network
 * Filament surfaces.
 *
 * Prerequisites: the demo app is migrated and served, e.g.
 *   php artisan serve --port=8000
 *
 * Usage:
 *   BASE_URL=http://127.0.0.1:8000 node tests/chrome/affiliate-surfaces.mjs
 *   BASE_URL=http://127.0.0.1:8000 node tests/chrome/affiliate-surfaces.mjs --no-seed
 *
 * The script re-seeds deterministic CHROME-* fixtures (unless --no-seed),
 * logs into /admin as admin@commerce.demo, and walks the new surfaces:
 * offers table (Fee column) → offer Legs tab → leg reverse; sites →
 * rotate catalog token; conversions table (Origin / Source Ref) →
 * conversion reverse. Screenshots land in tests/chrome/screenshots/.
 */
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import puppeteer from 'puppeteer';

const here = dirname(fileURLToPath(import.meta.url));
const demoRoot = join(here, '..', '..');
const shotsDir = join(here, 'screenshots');
mkdirSync(shotsDir, { recursive: true });

const BASE_URL = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const EMAIL = process.env.CHROME_USER ?? 'admin@commerce.demo';
const PASSWORD = process.env.CHROME_PASSWORD ?? 'password';

const failures = [];
let shot = 0;

async function screenshot(page, name) {
  shot += 1;
  await page.screenshot({ path: join(shotsDir, `${String(shot).padStart(2, '0')}-${name}.png`), fullPage: false });
}

function check(name, ok) {
  console.log(`${ok ? '  ✓' : '  ✗'} ${name}`);
  if (!ok) failures.push(name);
}

/** Trusted-click the first visible element whose text contains `text`. */
async function clickText(page, text, selector = 'a, button') {
  const handle = await page.evaluateHandle((label, sel) => [...document.querySelectorAll(sel)].find((el) => {
    const rect = el.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0 && (el.innerText ?? '').includes(label);
  }) ?? null, text, selector);
  const el = handle.asElement();
  if (!el) throw new Error(`could not find clickable "${text}"`);
  await el.click();
}

/** Wait until the visible element with `text` is enabled, then trusted-click it. */
async function clickEnabledText(page, text, selector = 'a, button', timeout = 15000) {
  await page.waitForFunction((label, sel) => [...document.querySelectorAll(sel)].some((el) => {
    const rect = el.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0 && (el.innerText ?? '').includes(label) && !el.disabled;
  }), { timeout }, text, selector);
  await clickText(page, text, selector);
}

async function pageHas(page, text) {
  return page.evaluate(
    (needle) => (document.body.innerText ?? '').includes(needle),
    text,
  );
}

async function waitForText(page, text, timeout = 15000) {
  await page.waitForFunction(
    (needle) => (document.body.innerText ?? '').includes(needle),
    { timeout },
    text,
  );
}

/** Fill the open Filament modal field (mounted action form ids end with the field name). */
async function fillModalField(page, field, value) {
  const selector = `.fi-modal input[id$="${field}"], dialog input[id$="${field}"]`;
  await page.waitForSelector(selector, { timeout: 10000 });
  await page.click(selector, { clickCount: 3 });
  await page.type(selector, value);
}

/** Submit the open Filament modal. */
async function submitModal(page) {
  await clickText(page, 'Submit', '.fi-modal button, dialog button');
  await new Promise((r) => setTimeout(r, 2500));
}

/** Confirm the open Filament confirmation modal. */
async function confirmModal(page) {
  await clickText(page, 'Confirm', '.fi-modal button, dialog button');
  await new Promise((r) => setTimeout(r, 2500));
}

async function clickNav(page, label) {
  await clickText(page, label, 'aside a, nav a, a.fi-sidebar-item-button');
  await new Promise((r) => setTimeout(r, 2500));
}

/** Trusted-click a row action (label, title, or aria-label) inside the row containing `rowText`. */
async function clickRowAction(page, rowText, actionText) {
  await page.waitForFunction((row, label) => {
    const tr = [...document.querySelectorAll('tbody tr')]
      .find((el) => (el.innerText ?? '').includes(row));
    if (!tr) return false;
    return [...tr.querySelectorAll('a, button')].some((el) => !el.disabled
      && ((el.innerText ?? '').includes(label)
        || (el.getAttribute('title') ?? '').includes(label)
        || (el.getAttribute('aria-label') ?? '').includes(label)));
  }, { timeout: 15000 }, rowText, actionText);
  const handle = await page.evaluateHandle((row, label) => {
    const tr = [...document.querySelectorAll('tbody tr')]
      .find((el) => (el.innerText ?? '').includes(row));
    return [...tr.querySelectorAll('a, button')]
      .find((el) => !el.disabled
        && ((el.innerText ?? '').includes(label)
          || (el.getAttribute('title') ?? '').includes(label)
          || (el.getAttribute('aria-label') ?? '').includes(label))) ?? null;
  }, rowText, actionText);
  const el = handle.asElement();
  if (!el) throw new Error(`row action not found: "${rowText}" / "${actionText}"`);
  await el.click();
  await new Promise((r) => setTimeout(r, 2500));
}

if (!process.argv.includes('--no-seed')) {
  console.log('Seeding CHROME-* fixtures…');
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\ChromeSmokeSeeder', '--force'], {
    cwd: demoRoot,
    stdio: 'inherit',
  });
}

const browser = await puppeteer.launch({
  headless: true,
  ignoreHTTPSErrors: true,
  args: ['--no-sandbox', '--disable-setuid-sandbox'],
});
const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 900 });

try {
  // Login.
  await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'networkidle2', timeout: 60000 });
  await page.waitForSelector('input[type="email"]', { timeout: 15000 });
  await page.type('input[type="email"]', EMAIL);
  await page.type('input[type="password"]', PASSWORD);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 60000 }),
    page.click('button[type="submit"]'),
  ]);
  check('login lands on the admin dashboard', page.url().includes('/admin'));
  await screenshot(page, 'dashboard');

  // Offers table: fee column.
  await clickNav(page, 'Offers');
  await waitForText(page, 'CHROME-OFFER');
  check('offers table lists the seeded offer', await pageHas(page, 'CHROME-OFFER'));
  check('offers table shows the Fee (bp) column', await pageHas(page, 'Fee (bp)'));
  await screenshot(page, 'offers-table');

  // Offer edit: Legs tab + reverse.
  await clickRowAction(page, 'CHROME-OFFER', 'Edit');
  await waitForText(page, 'Legs');
  check('offer edit shows the Legs relation tab', await pageHas(page, 'Legs'));
  // Relation tabs lazy-load on intersection: bring them into view first.
  await page.evaluate(() => {
    const btn = [...document.querySelectorAll('button')].find((el) => (el.innerText ?? '').trim().startsWith('Legs'));
    if (btn) btn.scrollIntoView({ block: 'center' });
  });
  await new Promise((r) => setTimeout(r, 1500));
  await clickText(page, 'Legs', 'button');
  await waitForText(page, 'CHROME-LEG-001');
  check('legs tab lists the seeded leg', await pageHas(page, 'CHROME-LEG-001'));
  check('leg money renders in major units', await pageHas(page, '899.00'));
  check('leg money is not 100x off', !(await pageHas(page, '89,900')));
  await screenshot(page, 'offer-legs');
  await clickRowAction(page, 'CHROME-LEG-001', 'Reverse');
  await fillModalField(page, 'reason', 'chrome refund');
  await submitModal(page);
  await waitForText(page, 'Reversed');
  check('leg reverse marks the leg reversed', await pageHas(page, 'Reversed'));
  await screenshot(page, 'leg-reversed');

  // Sites: rotate catalog token.
  await clickNav(page, 'Sites');
  await waitForText(page, 'CHROME-SITE');
  await clickRowAction(page, 'CHROME-SITE', 'Edit');
  await waitForText(page, 'Rotate catalog token');
  check('site edit shows the rotate token action', await pageHas(page, 'Rotate catalog token'));
  await clickEnabledText(page, 'Rotate catalog token', 'button');
  // Confirmation modal: confirm, then the one-time token notification appears.
  await new Promise((r) => setTimeout(r, 1200));
  await confirmModal(page);
  await waitForText(page, 'New catalog token');
  check('rotation surfaces the new token once', await pageHas(page, 'New catalog token'));
  await screenshot(page, 'token-rotated');

  // Engine conversions: origin + source ref + reverse.
  await clickNav(page, 'Affiliate Conversions');
  await waitForText(page, 'CHROME-CONV-001');
  check('conversions table shows the Origin column', await pageHas(page, 'Origin'));
  check('conversions table shows the Source Ref column', await pageHas(page, 'Source Ref'));
  const convRow = await page.evaluate(() => {
    const tr = [...document.querySelectorAll('tbody tr')]
      .find((el) => (el.innerText ?? '').includes('CHROME-CONV-001'));
    return tr ? (tr.innerText ?? '') : '';
  });
  check('seeded conversion carries the network origin', convRow.includes('network'));
  check('seeded conversion links the source leg', convRow.includes('CHROME-LEG-001'));
  await screenshot(page, 'conversions-table');
  await clickRowAction(page, 'CHROME-CONV-001', 'Reverse');
  await fillModalField(page, 'reason', 'chrome chargeback');
  await submitModal(page);
  await waitForText(page, 'Reversed');
  check('conversion reverse marks the conversion reversed', await pageHas(page, 'Reversed'));
  await screenshot(page, 'conversion-reversed');
} catch (error) {
  failures.push(`exception: ${error.message}`);
  await screenshot(page, 'failure');
} finally {
  await browser.close();
}

if (failures.length > 0) {
  console.error(`\n${failures.length} chrome check(s) failed:`);
  for (const failure of failures) console.error(`  - ${failure}`);
  process.exit(1);
}

console.log('\nAll chrome checks passed.');
