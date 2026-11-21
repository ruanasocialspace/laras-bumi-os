import { expect, test, type Page } from "@playwright/test";

const fixturePassword = "phase-three-fixture-password";
const imagePath = "/driver/icon/192";

async function login(page: Page, email: string, password: string) {
  await page.goto("/login");
  await page.getByLabel("Email").fill(email);
  await page.getByLabel("Kata sandi").fill(password);
  await page.getByRole("button", { name: "Masuk", exact: true }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page.getByRole("heading", { name: "Dasbor" })).toBeVisible();
}

async function request(page: Page, path: string, payload?: object) {
  return page.evaluate(async ({ path, payload }) => {
    const headers: Record<string, string> = { Accept: "application/json" };
    if (payload) {
      const token = document.cookie.split("; ").find((entry) => entry.startsWith("XSRF-TOKEN="));
      if (!token) throw new Error("CSRF cookie missing");
      headers["X-XSRF-TOKEN"] = decodeURIComponent(token.slice("XSRF-TOKEN=".length));
      headers["Content-Type"] = "application/json";
    }
    const response = await fetch("/api/v1" + path, {
      headers, method: payload ? "POST" : "GET", body: payload ? JSON.stringify(payload) : undefined,
    });
    return { status: response.status, body: await response.json() };
  }, { path, payload });
}

test("dispatch assigns a pickup and the mobile driver resumes and syncs offline photo evidence exactly once", async ({ page, browser, baseURL }) => {
  const password = process.env.LARAS_DEMO_PASSWORD;
  if (!password) throw new Error("Set LARAS_DEMO_PASSWORD to match the local seed accounts.");
  test.setTimeout(90_000);
  const nonce = Date.now();
  const officeEmail = "collect-" + nonce + "@laras.test";
  const customerName = "A Pelanggan Uji " + nonce;
  const locationName = "A Gudang Uji " + nonce;
  const vehicleName = "A Armada Uji " + nonce;

  await login(page, "admin@laras.test", password);
  const provisioned = await request(page, "/users", {
    name: "Petugas Pengambilan Uji", email: officeEmail, password: fixturePassword, role_codes: ["operations_manager"],
  });
  expect(provisioned.status).toBe(201);
  await page.getByRole("button", { name: "Keluar", exact: true }).click();
  await expect(page).toHaveURL(/\/login$/);
  await login(page, officeEmail, fixturePassword);

  const categories = await request(page, "/customer-categories");
  expect(categories.status).toBe(200);
  const customer = await request(page, "/customers", {
    code: "C-DRV-" + nonce, name: customerName, category_id: categories.body.data[0].id,
  });
  expect(customer.status).toBe(201);
  const location = await request(page, "/customers/" + customer.body.data.id + "/locations", { name: locationName, address: "Alamat Uji" });
  expect(location.status).toBe(201);

  await page.getByRole("link", { name: "Pengambilan", exact: true }).click();
  await page.getByRole("button", { name: "Kendaraan", exact: true }).click();
  await page.getByRole("button", { name: "Tambah kendaraan" }).click();
  const fleet = page.getByRole("form", { name: "Kendaraan baru" });
  await fleet.getByLabel("Nomor polisi").fill("AA " + nonce);
  await fleet.getByLabel("Nama kendaraan").fill(vehicleName);
  await fleet.getByRole("button", { name: "Simpan" }).click();
  await expect(page.getByText("Kendaraan tersimpan.")).toBeVisible();

  await page.getByRole("button", { name: "Penugasan", exact: true }).click();
  await page.getByRole("button", { name: "Tambah pengambilan" }).click();
  await page.getByRole("button", { name: "Pilih " + customerName }).click();
  await page.getByRole("button", { name: "Pilih " + locationName }).click();
  const pickup = page.getByRole("form", { name: "Waktu pengambilan" });
  await pickup.getByLabel("Waktu pengambilan (WIB)").fill("08:00");
  await pickup.getByRole("button", { name: "Simpan" }).click();
  await expect(page.getByText("Pengambilan tersimpan.")).toBeVisible();

  const taskRow = page.getByRole("listitem").filter({ hasText: customerName });
  await taskRow.getByRole("button", { name: "Tugaskan", exact: true }).click();
  await page.getByRole("button", { name: "Pilih Pengemudi Lokal", exact: true }).click();
  await page.getByRole("button", { name: "Pilih " + vehicleName }).click();
  await page.getByRole("form", { name: "Simpan penugasan" }).getByRole("button", { name: "Simpan" }).click();
  await expect(page.getByText("Penugasan tersimpan.")).toBeVisible();

  const picture = await page.request.get(imagePath);
  expect(picture.ok()).toBeTruthy();
  expect(picture.headers()["content-type"]).toContain("image/png");
  const photo = await picture.body();
  const context = await browser.newContext({
    baseURL, viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true,
    geolocation: { latitude: -8.214, longitude: 113.643, accuracy: 6 }, permissions: ["geolocation"],
  });
  try {
    const driver = await context.newPage();
    await login(driver, "driver@laras.test", password);
    await driver.getByRole("link", { name: "Aplikasi pengemudi" }).click();
    const task = driver.getByRole("article", { name: "Tugas " + customerName });
    await expect(task).toBeVisible();
    await expect(driver.getByText("Aplikasi siap dibuka offline setelah tugas diunduh.")).toBeVisible();

    await context.setOffline(true);
    await expect(driver.getByText("Offline", { exact: true })).toBeVisible();
    await task.getByRole("button", { name: "Mulai perjalanan" }).click();
    await task.getByRole("button", { name: "Saya sudah tiba" }).click();
    await task.getByRole("button", { name: "Selesaikan pengambilan" }).click();
    const evidence = task.getByRole("form", { name: "Bukti penyelesaian" });
    await evidence.getByLabel("Foto bukti").setInputFiles({ name: "bukti.png", mimeType: "image/png", buffer: photo });
    await evidence.getByRole("button", { name: "Ambil lokasi GPS" }).click();
    await expect(evidence.getByText(/GPS tersedia/)).toBeVisible();
    await evidence.getByLabel("Berat (kg)").fill("12,5");
    await evidence.getByRole("button", { name: "Simpan bukti" }).click();
    await expect(driver.getByText(/Antrean: 3/)).toBeVisible();

    await driver.reload({ waitUntil: "domcontentloaded" });
    await expect(task.getByText("Selesai", { exact: true })).toBeVisible();
    await expect(driver.getByText(/Antrean: 3/)).toBeVisible();

    let lost = false;
    await driver.route("**/api/v1/pickup-tasks/*/events", async (route) => {
      if (!lost && route.request().method() === "POST") {
        const stored = await route.fetch();
        expect(stored.ok()).toBeTruthy();
        await stored.dispose();
        lost = true;
        await route.abort("failed");
      } else {
        await route.continue();
      }
    });
    await context.setOffline(false);
    await expect(driver.getByText(/Sinkronisasi dijeda/)).toBeVisible();
    await driver.getByRole("button", { name: "Sinkronkan", exact: true }).click();
    await expect(driver.getByText(/Antrean: 0 · Perlu perhatian: 0/)).toBeVisible();
    expect(lost).toBe(true);

    const picks = await request(page, "/pickup-tasks?per_page=100");
    const stored = picks.body.data.find((item: { customer_name: string }) => item.customer_name === customerName);
    expect(stored.status).toBe("completed");
    const timeline = await request(page, "/pickup-tasks/" + stored.id + "/events");
    expect(timeline.body.data.map((entry: { type: string }) => entry.type)).toEqual(["scheduled", "assigned", "en_route", "arrived", "completed"]);
    expect(timeline.body.data[4].weight_basis).toBe("estimated");
    expect(timeline.body.data[4].weight_kg).toBe("12.500");
    expect(timeline.body.data[4].evidence_ids).toHaveLength(1);
  } finally {
    await context.close();
  }
});
