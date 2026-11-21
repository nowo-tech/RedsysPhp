import { test, expect } from '@playwright/test';

/** REQ-DEMO-013 — happy path of the Redsys redirect demo (UiKit shell + FormKit payment form). */
test.describe('RedsysPhp demo', () => {
  test('home renders the demo platform shell and the FormKit payment form', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByTestId('demo-platform')).toContainText('Redsys PHP');
    await expect(page.getByLabel('Amount (cents)')).toHaveValue('145');
    await expect(page.getByTestId('demo-pay')).toBeVisible();
  });

  test('paying returns the signed auto-submit redirect form', async ({ page }) => {
    await page.goto('/');
    await page.getByLabel('Order (4–12 chars)').fill('E2E000123');
    // The auto-submit form posts to the Redsys SIS: capture that request and never reach the real host.
    await page.route(/redsys\.es/, (route) => route.abort());
    const [request] = await Promise.all([
      page.waitForRequest((r) => /redsys\.es/.test(r.url()) && r.method() === 'POST'),
      page.getByTestId('demo-pay').click(),
    ]);
    const body = decodeURIComponent(request.postData() ?? '');
    expect(body).toContain('Ds_SignatureVersion=HMAC_SHA512_V2');
    expect(body).toContain('Ds_MerchantParameters=');
    expect(body).toContain('Ds_Signature=');
  });

  test('the browser return pages render in the shell', async ({ page }) => {
    await page.goto('/ok');
    await expect(page.getByTestId('demo-result')).toContainText('Payment return OK');
    await page.goto('/ko');
    await expect(page.getByTestId('demo-result')).toContainText('Payment return KO');
  });
});
