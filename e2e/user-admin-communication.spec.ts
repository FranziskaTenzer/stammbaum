import { test, expect } from '@playwright/test';

const AUTH_FILE = 'playwright/.auth/auth.json';

test.use({ storageState: AUTH_FILE });

function uniqueToken(prefix: string): string {
  return `${prefix}-${Date.now()}-${Math.floor(Math.random() * 1000)}`;
}

async function loginIfNeeded(page: import('@playwright/test').Page): Promise<void> {
  await page.goto('/stammbaum/app/views/user/index.php');
  if (!/\/public\/login\.php/.test(page.url())) {
    return;
  }

  await page.goto('/stammbaum/public/login.php');

  const cookieButton = page.getByRole('button', { name: 'Verstanden' });
  if (await cookieButton.isVisible().catch(() => false)) {
    await cookieButton.click();
  }

  await page.fill('#username', 'e2eTest');
  await page.fill('#password', 'F!cken123');
  await page.getByRole('button', { name: 'Anmelden' }).click();

  await expect(page).toHaveURL(/\/stammbaum\/app\/views\/(user|admin)\/.+\.php/);
}

test.beforeEach(async ({ page }) => {
  await loginIfNeeded(page);
});

test('Recherche-Anfrage erscheint im Adminbereich', async ({ page }) => {
  const token = uniqueToken('e2e-recherche');
  const messageText = `Automatischer E2E Recherche-Test: ${token}`;

  await page.goto('/stammbaum/app/views/user/recherche-anfrage.php');

  await page.fill('#person_id', '9464');
  await page.fill('#person_name', 'Margreth Naschberger');
  await page.fill('#nachricht', messageText);
  await page.getByRole('button', { name: /Recherche senden/i }).click();

  await expect(page.locator('.alert-success')).toContainText(/erfolgreich gesendet/i);
  await expect(page.locator('.nachrichten-list')).toContainText(token);

  await page.goto('/stammbaum/app/views/admin/recherche-anfragen.php');
  const openCard = page.locator('.nachricht-card', { hasText: token }).first();

  await expect(openCard).toBeVisible();
  await expect(openCard).toContainText('e2eTest');
  await expect(openCard).toContainText('Margreth Naschberger');
  await expect(openCard).toContainText('9464');
});

test('Nachricht wird beantwortet, Status geprueft und vom User geloescht', async ({ page }) => {
  const token = uniqueToken('e2e-nachricht');
  const subject = `E2E Nachricht ${token}`;
  const body = `Automatischer E2E Nachrichtentest: ${token}`;
  const adminReply = `Admin-Antwort fuer ${token}`;

  await page.goto('/stammbaum/app/views/user/nachrichten.php');

  await page.fill('#betreff', subject);
  await page.fill('#nachricht', body);
  await page.getByRole('button', { name: /Nachricht senden/i }).click();

  await expect(page.locator('.alert-success')).toContainText(/erfolgreich gesendet/i);
  await expect(page.locator('.nachrichten-list')).toContainText(subject);

  await page.goto('/stammbaum/app/views/admin/admin-nachrichten.php?filter=offen&typ=Nachricht');

  const overviewRow = page.locator('.overview-table tr', { hasText: subject }).first();
  await expect(overviewRow).toBeVisible();
  await overviewRow.click();

  const detailCard = page.locator('.nachricht-card', { hasText: subject }).first();
  await expect(detailCard).toBeVisible();
  await expect(detailCard).toContainText(body);
  await expect(detailCard).toContainText('e2eTest');

  await detailCard.locator('textarea[name="antwort"]').fill(adminReply);
  await detailCard.getByRole('button', { name: /Antwort speichern/i }).click();
  await expect(page.locator('.alert-success')).toContainText(/Antwort erfolgreich gespeichert/i);

  await page.goto('/stammbaum/app/views/admin/admin-nachrichten.php?filter=offen&typ=Nachricht');
  await expect(page.locator('.overview-table tr', { hasText: subject })).toHaveCount(0);

  await page.goto('/stammbaum/app/views/admin/admin-nachrichten.php?filter=beantwortet&typ=Nachricht');
  const answeredRow = page.locator('.overview-table tr', { hasText: subject }).first();
  await expect(answeredRow).toBeVisible();
  await expect(answeredRow).toContainText('Beantwortet');
  await answeredRow.click();
  await expect(page.locator('.nachricht-card', { hasText: adminReply }).first()).toBeVisible();

  await page.goto('/stammbaum/app/views/user/nachrichten.php');
  const userCard = page.locator('.nachricht-card', { hasText: subject }).first();
  await expect(userCard).toBeVisible();
  await expect(userCard).toContainText(adminReply);

  page.once('dialog', (dialog) => dialog.accept());
  await userCard.getByRole('button', { name: /Loeschen|Löschen/i }).click();
  await expect(page.locator('.alert-success')).toContainText(/erfolgreich geloescht|erfolgreich gelöscht/i);
  await expect(page.locator('.nachricht-card', { hasText: subject })).toHaveCount(0);
});

