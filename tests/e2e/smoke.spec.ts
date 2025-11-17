import { test, expect } from '@playwright/test';

test('home page loads', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveTitle(/DLme Marketplace/i);
});

test('login page is reachable', async ({ page }) => {
  await page.goto('/wp-login.php');
  await expect(page.locator('form#loginform')).toBeVisible();
});
