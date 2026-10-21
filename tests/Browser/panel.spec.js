import { expect, test } from '@playwright/test';

/**
 * Panel gezinmesi.
 *
 * Sayfa geçişleri tarayıcıyı YENİLEMEZ; ölçüsü doğrudan alınır: pencereye bir
 * işaret bırakılır ve geçişten sonra hâlâ orada mı diye bakılır. Tam
 * yenilemede o işaret kaybolur.
 *
 * Bu uygulamada ek bir kural var: panel ile Hızlı Erişim AYRI biçem paketleri
 * kullanıyor, o yüzden panel dışına çıkan bağlantılar yerinde değiştirilmez.
 */
const EPOSTA = process.env.E2E_EMAIL;
const PAROLA = process.env.E2E_PASSWORD;

async function girisYap(page) {
    await page.goto('/?pencere=giris');
    await page.fill('#pencere-giris input[name="email"]', EPOSTA);
    await page.fill('#pencere-giris input[name="password"]', PAROLA);
    await page.click('#pencere-giris button[type="submit"]');
    await expect(page.locator('[data-menu-ac]')).toBeVisible();
}

const isaretBirak = (page) => page.evaluate(() => { window.__iz = 'duruyor'; });
const isaret = (page) => page.evaluate(() => window.__iz ?? null);

test('panel icinde gecis sayfayi yenilemez', async ({ page }) => {
    await girisYap(page);
    await page.goto('/yonetim/sistemler');
    await isaretBirak(page);

    await page.getByRole('link', { name: 'Kullanıcılar' }).click();

    await expect(page).toHaveURL(/yonetim\/kullanicilar/);
    await expect(page.locator('h1')).toHaveText('Kullanıcılar');
    expect(await isaret(page)).toBe('duruyor');
});

test('geri tusu panel icinde calisir', async ({ page }) => {
    await girisYap(page);
    await page.goto('/yonetim/sistemler');

    await page.getByRole('link', { name: 'Ayarlar' }).click();
    await expect(page).toHaveURL(/ayarlar/);

    await isaretBirak(page);
    await page.goBack();

    await expect(page).toHaveURL(/sistemler/);
    expect(await isaret(page)).toBe('duruyor');
});

/**
 * Panel DIŞINA çıkan bağlantı tam yenileme yapmalı.
 *
 * Hızlı Erişim sayfasının biçemi ayrı; yerinde değiştirilseydi yeni gövde
 * panelin biçemiyle çizilir ve sayfa bozuk görünürdü.
 */
test('hizli erisime gecis tam yenileme yapar', async ({ page }) => {
    await girisYap(page);
    await page.goto('/yonetim/sistemler');
    await isaretBirak(page);

    await page.getByRole('link', { name: 'Hızlı Erişim' }).click();
    await expect(page).toHaveURL(/kimlik\.ornek\.test\/$|127\.0\.0\.1:\d+\/$/);

    expect(await isaret(page)).toBeNull();
    /* Sayfa kendi biçemiyle çizilmiş olmalı: kabinler görünüyor. */
    await expect(page.locator('#halls .name').first()).toBeVisible();
});

/** Geçişten sonra pencere ve menü hâlâ çalışmalı. */
test('gecisten sonra pencere ve hesap menusu calisir', async ({ page }) => {
    await girisYap(page);
    await page.goto('/yonetim/kullanicilar');

    await page.getByRole('link', { name: 'Sistemler' }).click();
    await expect(page).toHaveURL(/sistemler/);

    await page.getByRole('link', { name: 'Yeni kutucuk' }).click();
    await expect(page.locator('#sistem-ekle')).toBeVisible();

    await page.keyboard.press('Escape');
    await page.click('[data-menu-ac]');
    await expect(page.locator('[data-menu-liste]')).toBeVisible();
});
