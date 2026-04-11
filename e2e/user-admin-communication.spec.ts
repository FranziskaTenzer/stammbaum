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

test('Ungelesene Admin-Antworten zeigen "neue Nachrichten" im Sidemenü und Index', async ({ page }) => {
  const token = uniqueToken('e2e-unread');
  const subject = `E2E Ungelesen Test ${token}`;
  const body = `Test für ungelesene Nachrichten: ${token}`;
  const adminReply = `Ungelesene Admin-Antwort für ${token}`;

  // User sendet eine Nachricht
  await page.goto('/stammbaum/app/views/user/nachrichten.php');
  await page.fill('#betreff', subject);
  await page.fill('#nachricht', body);
  await page.getByRole('button', { name: /Nachricht senden/i }).click();
  await expect(page.locator('.alert-success')).toContainText(/erfolgreich gesendet/i);

  // Admin antwortet
  await page.goto('/stammbaum/app/views/admin/admin-nachrichten.php?filter=offen&typ=Nachricht');
  const row = page.locator('.overview-table tr', { hasText: subject }).first();
  await row.click();

  const card = page.locator('.nachricht-card', { hasText: subject }).first();
  await card.locator('textarea[name="antwort"]').fill(adminReply);
  await card.getByRole('button', { name: /Antwort speichern/i }).click();
  await expect(page.locator('.alert-success')).toContainText(/Antwort erfolgreich gespeichert/i);

  // User hat die Nachricht noch nicht gelesen - aber ist trotzdem im System
  // Wir müssen sicherstellen, dass is_read_by_user = 0 gesetzt bleibt

  // ** WICHTIG: Hier verlassen wir bewusst die nachrichten.php, um die Nachricht
  //    als ungelesen zu halten (is_read_by_user = 0)
  // Navigiere zu einer anderen Seite (z.B. Stammbaum-Search)
  await page.goto('/stammbaum/app/views/user/stammbaum-search.php');

  // Jetzt: Index-Seite prüfen - sollte "neue Nachrichten" Message zeigen
  await page.goto('/stammbaum/app/views/user/index.php');
  const heading = page.locator('.page-header h1');
  await expect(heading).toContainText(/neue Nachrichten|neue Nachrichten/i);

  // Sidebar prüfen - sollte "neue Nachrichten" (bold) zeigen, nicht "Nachrichten"
  const nacrichtenLink = page.locator(
    '.sidebar-nav a:has-text("✉️ Neue Nachrichten"), .sidebar-nav a:has-text("✉️") >> nth=1'
  ).first();
  
  // Alt: Via CSS-Selektor mit gehighlighteter Nachricht suchen
  const sidebarLink = page.locator('.sidebar-nav').locator('text=/Neue Nachrichten|Nachrichten/');
  
  // Verprüfen dass "Neue Nachrichten" (mit <strong> tags) sichtbar ist
  await expect(page.locator('.sidebar-nav >> text=Neue Nachrichten').first()).toBeVisible();

  // User besucht jetzt nachrichten.php und liest die Nachricht
  await page.goto('/stammbaum/app/views/user/nachrichten.php');
  await expect(page.locator('.nachricht-card', { hasText: subject })).toBeVisible();
  await expect(page.locator('.nachricht-card', { hasText: adminReply })).toBeVisible();

  // Nach dem Lesen: Navigation zurück zu Index
  await page.goto('/stammbaum/app/views/user/index.php');
  
  // Jetzt sollte die Nachricht als gelesen markiert sein
  // Index header sollte wieder normal sein (nicht "neue Nachrichten")
  const normalHeading = page.locator('.page-header h1');
  await expect(normalHeading).toContainText('Willkommen zum Stammbaum');
  
  // Sidebar sollte wieder "Nachrichten" zeigen (nicht bold/nicht "neue")
  // Verprüfen dass NICHT "Neue Nachrichten" sichtbar ist in der Sidebar
  await expect(
    page.locator('.sidebar-nav >> text=Neue Nachrichten')
  ).toHaveCount(0);
});

