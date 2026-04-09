import fs from 'fs';
import path from 'path';
import { expect, test as setup } from '@playwright/test';

const AUTH_FILE = 'playwright/.auth/auth.json';

setup('create authenticated session for e2e user', async ({ page }) => {
  fs.mkdirSync(path.dirname(AUTH_FILE), { recursive: true });

  await page.goto('/stammbaum/public/login.php');

  await page.getByRole('button', { name: 'Verstanden' }).click();
  await page.fill('#username', 'e2eTest');
  await page.fill('#password', 'F!cken123');
  await page.getByRole('button', { name: 'Anmelden' }).click();

  await page.context().storageState({ path: AUTH_FILE });

  
  //await page.waitForURL('/stammbaum/app/views/user/index.php');
  await expect(page).toHaveURL(/\/stammbaum\/app\/views\/user\/index\.php/);
});

