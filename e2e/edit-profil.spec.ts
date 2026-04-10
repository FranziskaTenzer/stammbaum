import { test, expect, Page } from '@playwright/test';

const AUTH_FILE = 'playwright/.auth/auth.json';

test.use({ storageState: AUTH_FILE });

test('Profil bearbeiten speichert und kann zurueckgesetzt werden', async ({ page }) => {
  await page.goto('/stammbaum/app/views/user/profil.php');

  const emailInput = page.locator('#email');
  const notifications = page.locator('#notifications_enabled');

  const originalEmail = await emailInput.inputValue();
  const originalNotifications = await notifications.isChecked();
  const newEmail = `e2e.admin.${Date.now()}@stammbaum.test`;

  await emailInput.fill(newEmail);
  if (originalNotifications) {
    await notifications.uncheck();
  } else {
    await notifications.check();
  }

  await page.getByRole('button', { name: /Aenderungen speichern|Änderungen speichern/i }).click();
  await expect(page.locator('.alert-success')).toContainText(/Profil erfolgreich gespeichert/i);
  await expect(emailInput).toHaveValue(newEmail);
  await expect(notifications).toBeChecked({ checked: !originalNotifications });

  await emailInput.fill(originalEmail);
  if (originalNotifications) {
    await notifications.check();
  } else {
    await notifications.uncheck();
  }

  await page.getByRole('button', { name: /Aenderungen speichern|Änderungen speichern/i }).click();
  await expect(page.locator('.alert-success')).toContainText(/Profil erfolgreich gespeichert/i);
  await expect(emailInput).toHaveValue(originalEmail);
  await expect(notifications).toBeChecked({ checked: originalNotifications });
  await expect(page.getByText('✅ Profil erfolgreich gespeichert')).toBeVisible();
});