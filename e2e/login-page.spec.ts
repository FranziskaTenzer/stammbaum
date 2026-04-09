import { test, expect } from '@playwright/test';

test.use({ storageState: { cookies: [], origins: [] } });

test('login page loads', async ({ page }) => {
  await page.goto('/stammbaum/public/login.php');
  await expect(page).toHaveTitle(/Stammbaum|Login|Anmeldung/i);
  await expect(page.locator('body')).toContainText(/Stammbaum|Login|Anmeldung/i);
});

test('login with valid credentials', async ({ page }) => {
  await page.goto('/stammbaum/public/login.php');
  await page.fill('#username', 'e2eTest');    
  await page.fill('#password', 'F!cken123');
  await page.getByRole('button', { name: 'Anmelden' }).click();
  await expect(page).toHaveURL(/\/stammbaum\/app\/views\/user\/index\.php/);
});

test('login with invalid credentials shows error', async ({ page }) => {        
  await page.goto('/stammbaum/public/login.php');
  await page.fill('#username', 'invalidUser');
  await page.fill('#password', 'invalidPassword');
  await page.getByRole('button', { name: 'Anmelden' }).click();
  await expect(page.locator('.error')).toHaveText(/Benutzername oder Passwort ist falsch!/i);
});
