import { expect, test, type Page } from "@playwright/test";

const initialPassword = process.env.LARAS_DEMO_PASSWORD;
const fixturePassword = "phase-two-fixture-password";

async function login(page: Page, email: string, password: string) {
  await page.goto("/login");
  await page.getByLabel("Email").fill(email);
  await page.getByLabel("Kata sandi").fill(password);
  await page.getByRole("button", { name: "Masuk", exact: true }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page.getByRole("heading", { name: "Dasbor" })).toBeVisible();
}

test("office staff register a customer and activate and suspend a real contract", async ({ page }) => {
  if (!initialPassword) throw new Error("Set LARAS_DEMO_PASSWORD to match the local seed accounts.");
  test.setTimeout(60_000);

  const nonce = Date.now();
  const email = "phase2-" + nonce + "@laras.test";
  await login(page, "admin@laras.test", initialPassword);
  const provisioningStatus = await page.evaluate(async ({ email, password }) => {
    const cookie = document.cookie.split("; ").find((entry) => entry.startsWith("XSRF-TOKEN="));
    if (!cookie) throw new Error("CSRF cookie is missing.");
    const response = await fetch("/api/v1/users", {
      method: "POST",
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-XSRF-TOKEN": decodeURIComponent(cookie.slice("XSRF-TOKEN=".length)),
      },
      body: JSON.stringify({
        name: "Petugas Uji Fase Dua",
        email,
        password,
        role_codes: ["operations_manager", "finance_officer"],
      }),
    });

    return response.status;
  }, { email, password: fixturePassword });
  expect(provisioningStatus).toBe(201);
  await page.getByRole("button", { name: "Keluar", exact: true }).click();
  await expect(page).toHaveURL(/\/login$/);
  await login(page, email, fixturePassword);

  await page.getByRole("link", { name: "Pelanggan", exact: true }).click();
  await page.getByRole("button", { name: "Tambah pelanggan", exact: true }).click();
  const customer = page.getByRole("form", { name: "Pelanggan baru" });
  const customerName = "Pelanggan E2E " + nonce;
  await customer.getByLabel("Kode pelanggan").fill("C-E2E-" + nonce);
  await customer.getByLabel("Nama pelanggan").fill(customerName);
  await customer.getByLabel("Kategori pelanggan").selectOption({ label: "Usaha" });
  await customer.getByRole("button", { name: "Simpan" }).click();
  await expect(page.getByRole("heading", { name: customerName })).toBeVisible();

  await page.getByRole("button", { name: "Tambah kontak", exact: true }).click();
  const contact = page.getByRole("form", { name: "Tambah kontak" });
  await contact.getByLabel("Nama kontak").fill("Kontak E2E");
  await contact.getByLabel("Email kontak").fill("contact@example.test");
  await contact.getByRole("button", { name: "Simpan" }).click();
  await expect(page.getByText("contact@example.test")).toBeVisible();

  await page.getByRole("button", { name: "Tambah lokasi", exact: true }).click();
  const location = page.getByRole("form", { name: "Tambah lokasi" });
  await location.getByLabel("Nama lokasi").fill("Gudang E2E");
  await location.getByLabel("Alamat penjemputan").fill("Alamat Contoh E2E");
  await location.getByRole("button", { name: "Simpan" }).click();
  await expect(page.getByText("Gudang E2E", { exact: true })).toBeVisible();

  await page.getByRole("button", { name: "Tambah profil penagihan" }).click();
  const billing = page.getByRole("form", { name: "Profil penagihan" });
  await billing.getByLabel("Nama penagihan").fill("Profil E2E");
  await billing.getByLabel("Alamat penagihan").fill("Alamat Tagihan Contoh");
  await billing.getByLabel("Jatuh tempo dalam hari").fill("14");
  await billing.getByRole("button", { name: "Simpan" }).click();
  await expect(page.getByText("Jatuh tempo 14 hari")).toBeVisible();

  const today = new Intl.DateTimeFormat("sv-SE", { timeZone: "Asia/Jakarta" }).format(new Date());
  await page.getByRole("button", { name: "Tambah kontrak", exact: true }).click();
  const agreement = page.getByRole("form", { name: "Kontrak baru" });
  const contractName = "Kontrak E2E " + nonce;
  await agreement.getByLabel("Kode kontrak").fill("K-E2E-" + nonce);
  await agreement.getByLabel("Nama kontrak").fill(contractName);
  await agreement.getByLabel("Mulai kontrak").fill(today);
  await agreement.getByRole("button", { name: "Simpan" }).click();
  await expect(page.getByRole("heading", { name: contractName })).toBeVisible();

  await page.getByRole("button", { name: "Tambah layanan" }).click();
  await page.getByRole("button", { name: "Pilih Gudang E2E" }).click();
  const service = page.getByRole("form", { name: "Layanan baru" });
  await service.getByLabel("Nama layanan").fill("Pengambilan E2E");
  await service.getByRole("button", { name: "Simpan" }).click();
  await page.getByRole("button", { name: "Lihat tarif dan jadwal" }).click();

  await page.getByRole("button", { name: "Tambah tarif" }).click();
  const rate = page.getByRole("form", { name: "Tarif baru" });
  await rate.getByLabel("Model tarif").selectOption("monthly");
  await rate.getByLabel("Tarif (IDR)").fill("150000,25");
  await rate.getByRole("button", { name: "Simpan" }).click();
  await expect(page.getByText("Rp150.000,25 · Bulanan")).toBeVisible();

  await page.getByRole("button", { name: "Tambah jadwal" }).click();
  const schedule = page.getByRole("form", { name: "Jadwal baru" });
  await schedule.getByLabel("Hari", { exact: true }).selectOption("1");
  await schedule.getByLabel("Waktu penjemputan (WIB)").fill("08:00");
  await schedule.getByRole("button", { name: "Simpan" }).click();
  await expect(page.getByText("Senin, 08:00 WIB")).toBeVisible();

  await page.getByRole("button", { name: "Aktifkan", exact: true }).click();
  await page.getByRole("form", { name: "Aktifkan kontrak" }).getByRole("button", { name: "Simpan" }).click();
  await expect(page.getByRole("region", { name: "Riwayat status kontrak" }).getByText("Draf → Aktif")).toBeVisible();

  await page.getByRole("button", { name: "Tangguhkan", exact: true }).click();
  const suspend = page.getByRole("form", { name: "Tangguhkan kontrak" });
  await suspend.getByLabel("Alasan").fill("Peninjauan E2E");
  await suspend.getByRole("button", { name: "Simpan" }).click();
  const history = page.getByRole("region", { name: "Riwayat status kontrak" });
  await expect(history.getByText("Aktif → Ditangguhkan")).toBeVisible();
  await expect(history.getByText("Peninjauan E2E")).toBeVisible();

  await page.getByRole("link", { name: "Pelanggan", exact: true }).click();
  await page.getByRole("searchbox").fill(customerName);
  await page.getByRole("button", { name: "Cari", exact: true }).click();
  await expect(page.getByRole("link", { name: customerName })).toBeVisible();
});
