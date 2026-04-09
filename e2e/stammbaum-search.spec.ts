import { test, expect, Page } from '@playwright/test';

const AUTH_FILE = 'playwright/.auth/auth.json';

test.use({ storageState: AUTH_FILE });

async function openExtendedTreeFromSearch(page: Page, vorname: string, nachname: string, expectedId: string): Promise<void> {
  //await page.goto('/stammbaum/app/views/user/stammbaum-search.php');

  await page.fill('#vorname', vorname);
  await page.fill('#nachname', nachname);
  await page.getByRole('button', { name: /Suchen/i }).click();

  await expect(page.locator('table.results-table')).toBeVisible();

  const row = page.locator(`xpath=//table[contains(@class,'results-table')]//tbody/tr[td[1][normalize-space()='${expectedId}']]`);
  await expect(row).toBeVisible();

  await row.getByRole('link', { name: 'Stammbaum komplett' }).click();
  await expect(page).toHaveURL(new RegExp(`/stammbaum/app/views/user/stammbaum-display-extended\\.php\\?id=${expectedId}`));
}

test('Katharina Kostenzer (ID 3) hat 10 Kinder', async ({ page }) => {
  //await page.getByText('Personensuche').click();
  
  await page.goto('/stammbaum/app/views/user/stammbaum-search.php');
  await openExtendedTreeFromSearch(page, 'Katharina', 'Kostenzer', '3');

  const firstDescendantGeneration = page.locator('section.tree-panel.descendants section.generation').first();
  await expect(firstDescendantGeneration).toBeVisible();

  const childrenCount = await firstDescendantGeneration.locator('article.person-card').count();
  expect(childrenCount).toBe(10);
});

test('Margreth Naschberger (ID 9464): 2 Kinder, je 1 Ehe, Vorfahren 1/2/2', async ({ page }) => {
  await page.goto('/stammbaum/app/views/user/stammbaum-search.php');
  await openExtendedTreeFromSearch(page, 'Margreth', 'Naschberger', '9464');

  const descendantsPanel = page.locator('section.tree-panel.descendants');
  const firstDescendantGeneration = descendantsPanel.locator('section.generation').first();
  await expect(firstDescendantGeneration).toBeVisible();

  const childCards = firstDescendantGeneration.locator('article.person-card');
  const childrenCount = await childCards.count();
  expect(childrenCount).toBe(2);

  for (let i = 0; i < childrenCount; i++) {
    const spouseLine = await childCards.nth(i).locator('.person-spouse').innerText();
    expect(spouseLine).not.toContain('Ehepartner: -');
    expect(spouseLine).not.toContain(', ');
  }

  const ancestorsPanel = page.locator('section.tree-panel.ancestors');

  const gen1 = ancestorsPanel.locator('section.generation', {
    has: page.locator('h4.generation-title', { hasText: /^\s*Eltern\s*$/ }),
  });
  const gen2 = ancestorsPanel.locator('section.generation', {
    has: page.locator('h4.generation-title', { hasText: /^\s*Grosseltern\s*$/ }),
  });
  const gen3 = ancestorsPanel.locator('section.generation', {
    has: page.locator('h4.generation-title', { hasText: /^\s*3\.\s*Vorfahrengeneration\s*$/ }),
  });

  await expect(gen1).toBeVisible();
  await expect(gen2).toBeVisible();
  await expect(gen3).toBeVisible();

  expect(await gen1.locator('article.person-card').count()).toBe(1);
  expect(await gen2.locator('article.person-card').count()).toBe(2);
  expect(await gen3.locator('article.person-card').count()).toBe(2);
});
