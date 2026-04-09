import { defineConfig, devices } from '@playwright/test';

const webkitEnv = {
  ...process.env,
};

delete webkitEnv.GIO_MODULE_DIR;
delete webkitEnv.GTK_PATH;
delete webkitEnv.GTK_PATH_VSCODE_SNAP_ORIG;

if (process.env.XDG_DATA_DIRS_VSCODE_SNAP_ORIG) {
  webkitEnv.XDG_DATA_DIRS = process.env.XDG_DATA_DIRS_VSCODE_SNAP_ORIG;
}

if (process.env.XDG_CONFIG_DIRS_VSCODE_SNAP_ORIG) {
  webkitEnv.XDG_CONFIG_DIRS = process.env.XDG_CONFIG_DIRS_VSCODE_SNAP_ORIG;
}

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'html',
  use: {
    baseURL: 'http://localhost',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'setup',
      testMatch: /auth\.setup\.ts/,
    },
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'playwright/.auth/e2e-user.json',
      },
      dependencies: ['setup'],
    },
    {
      name: 'firefox',
      use: {
        ...devices['Desktop Firefox'],
        storageState: 'playwright/.auth/e2e-user.json',
      },
      dependencies: ['setup'],
    },
    {
      name: 'webkit',
      use: {
        ...devices['Desktop Safari'],
        storageState: 'playwright/.auth/e2e-user.json',
        launchOptions: {
          env: webkitEnv,
        },
      },
      dependencies: ['setup'],
    },
  ],
  webServer: {
    command: 'php -S 127.0.0.1:8080 -t public',
    url: 'http://localhost/stammbaum/public/login.php',
    reuseExistingServer: true,
    timeout: 120000,
  },
});
