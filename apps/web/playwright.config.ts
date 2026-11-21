import { defineConfig, devices } from "@playwright/test";

export default defineConfig({
  testDir: "./tests/e2e",
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  reporter: "list",
  use: {
    baseURL: process.env.LARAS_E2E_URL ?? "http://localhost:8080",
    trace: "retain-on-failure",
  },
  projects: [{
    name: "chromium",
    use: { ...devices["Desktop Chrome"], channel: process.env.LARAS_BROWSER_CHANNEL },
  }],
});
