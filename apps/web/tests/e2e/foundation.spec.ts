import { expect, test, type Page } from "@playwright/test";

const password = process.env.LARAS_DEMO_PASSWORD;
const admin = "admin@laras.test";
const driver = "driver@laras.test";

async function signIn(page: Page, email: string) {
  if (!password) throw new Error("Set LARAS_DEMO_PASSWORD to match the local seed accounts.");
  await page.goto("/login");
  await page.getByLabel("Email").fill(email);
  await page.getByLabel("Kata sandi").fill(password);
  await page.getByRole("button", { name: "Masuk", exact: true }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page.getByRole("heading", { name: "Dasbor" })).toBeVisible();
}

test("signed-out visitors see login and admin can sign in and out", async ({ page }) => {
  await page.goto("/dashboard");
  await expect(page).toHaveURL(/\/login$/);
  await signIn(page, admin);
  await expect(page.getByText("IDR", { exact: true })).toBeVisible();
  await expect(page.getByRole("link", { name: "Pengguna dan peran" })).toBeVisible();
  await page.getByRole("button", { name: "Keluar", exact: true }).click();
  await expect(page).toHaveURL(/\/login$/);
  expect((await page.request.get("/api/v1/auth/me")).status()).toBe(401);
});

test("driver cannot access organization accounts or audit data", async ({ page }) => {
  await signIn(page, driver);
  await expect(page.getByRole("link", { name: "Pengguna dan peran" })).toHaveCount(0);
  const response = await page.evaluate(async () => {
    const [users, audit] = await Promise.all([fetch("/api/v1/users"), fetch("/api/v1/audit-logs")]);
    return [users.status, audit.status];
  });
  expect(response).toEqual([403, 403]);
  await page.goto("/settings/users");
  await expect(page.getByRole("main").getByRole("alert")).toContainText("tidak memiliki izin");
});

test("admin changes an account, audit records it and CSRF rejects an untrusted write", async ({ page, baseURL }) => {
  const rejected = await page.request.post("/api/v1/auth/login", {
    headers: { Origin: baseURL ?? "http://localhost:8080", Accept: "application/json" },
    data: { email: admin, password },
  });
  expect(rejected.status()).toBe(419);

  await signIn(page, admin);
  await page.getByRole("link", { name: "Pengguna dan peran" }).click();
  await page.getByRole("button", { name: "Tambah pengguna" }).click();
  const nonce = Date.now();
  const name = "Pengguna E2E " + nonce;
  await page.getByLabel("Nama", { exact: true }).fill(name);
  await page.getByLabel("Email", { exact: true }).fill("e2e-" + nonce + "@laras.test");
  await page.getByLabel("Kata sandi").fill("e2e-password-example");
  await page.getByRole("checkbox", { name: "Pengemudi / Kru Pengambilan" }).check();
  const creation = page.waitForResponse((response) =>
    new URL(response.url()).pathname === "/api/v1/users" && response.request().method() === "POST",
  );
  await page.getByRole("button", { name: "Simpan pengguna" }).click();
  const created = await creation;
  expect(created.status()).toBe(201);
  const accountId: string = (await created.json()).data.id;
  await expect(page.getByText("Pengguna ditambahkan.")).toBeVisible();
  await page.getByRole("searchbox").fill(name);
  await page.getByRole("button", { name: "Cari", exact: true }).click();
  await page.getByRole("button", { name: "Ubah " + name }).click();
  await page.getByRole("checkbox", { name: "Akun aktif" }).uncheck();
  await page.getByRole("button", { name: "Simpan pengguna" }).click();
  await expect(page.getByText("Perubahan pengguna disimpan.")).toBeVisible();
  await expect(page.getByText("Nonaktif", { exact: true })).toBeVisible();
  await page.getByRole("link", { name: "Log audit" }).click();
  const record = page.locator("details").filter({ hasText: accountId }).filter({ hasText: "Pengguna diubah" });
  await expect(record.locator("summary")).toBeVisible();
  const timestamp = await record.locator("time").getAttribute("datetime");
  expect(Math.abs(Date.now() - Date.parse(timestamp ?? ""))).toBeLessThan(120_000);
});
