import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { waitForWebProfiler } from './helpers';

/** REQ-DEMO-013 — README screenshots (captured as PNG, converted to WebP by scripts/to-webp.mjs). */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('RedsysPhp screenshots', () => {
  test.use({ viewport: { width: 1280, height: 800 } });

  test('payment-form — redirect payment form', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByTestId('demo-pay')).toBeVisible();
    await waitForWebProfiler(page);
    await page.screenshot({ path: resolve(outDir, 'payment-form.png'), fullPage: true });
  });

  test('payment-return — browser return page', async ({ page }) => {
    await page.goto('/ok');
    await expect(page.getByTestId('demo-result')).toBeVisible();
    await waitForWebProfiler(page);
    await page.screenshot({ path: resolve(outDir, 'payment-return.png'), fullPage: true });
  });
});
