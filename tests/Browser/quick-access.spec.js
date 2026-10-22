import { expect, test } from '@playwright/test';

/**
 * Hızlı Erişim sayfası.
 *
 * Sayfa neredeyse tamamen betikle çiziliyor: elle yazılmış SVG kabinler ve
 * her karta ayrı şerit animasyonu. Taşımanın ölçütü "aynı görünen sayfa"
 * olduğu için sınanacak şey de çizimin gerçekten oluşması.
 */
test('kabinler cizilir ve konsol temiz kalir', async ({ page }) => {
    const hatalar = [];
    page.on('pageerror', (e) => hatalar.push(e.message));
    page.on('console', (m) => m.type() === 'error' && hatalar.push(m.text()));

    await page.goto('/');

    await expect(page.locator('h1')).toHaveText('Hangi sisteme gidiyorsun?');

    /* Kutucuklar veriden geliyor; hiç çizilmemesi sessiz bir kırılma olurdu. */
    await expect(page.locator('#halls .name').first()).toBeVisible();
    expect(await page.locator('#halls .name').count()).toBeGreaterThan(0);

    expect(hatalar).toEqual([]);
});

/** Kart sayısı sunucudan gelen veriyle birebir aynı olmalı. */
test('cizilen kart sayisi veriyle ayni', async ({ page }) => {
    await page.goto('/');

    const veri = await page.evaluate(() =>
        JSON.parse(document.getElementById('system-data').textContent).length);

    await expect(page.locator('#halls .name')).toHaveCount(veri);
});

/** Dış adresler yeni sekmede açılır; iç sistemler aynı sekmede kalır. */
test('dis adresler yeni sekmede acilir', async ({ page }) => {
    await page.goto('/');

    const dis = page.locator('#halls .name[target="_blank"]').first();

    if (await dis.count() > 0) {
        await expect(dis).toHaveAttribute('rel', /noopener/);
    }
});

test('sayfa yatay kaymaz', async ({ page }) => {
    await page.goto('/');

    const tasma = await page.evaluate(() =>
        document.documentElement.scrollWidth - document.documentElement.clientWidth);

    expect(tasma).toBeLessThanOrEqual(1);
});

test('tema degistirilebilir ve secim korunur', async ({ page }) => {
    await page.goto('/');

    const onceki = await page.evaluate(() => document.documentElement.dataset.theme);
    await page.click('#theme');

    const sonraki = await page.evaluate(() => document.documentElement.dataset.theme);
    expect(sonraki).not.toBe(onceki);

    await page.reload();
    expect(await page.evaluate(() => document.documentElement.dataset.theme)).toBe(sonraki);
});

/**
 * Pencere davranışı.
 *
 * Pencerenin adresi vardır: açılınca adres çubuğuna yazılır, kapanınca
 * silinir. Doğrulama hatasının pencereyi açık geri getirmesi buna bağlı.
 */
test('giris penceresi acilir ve adres cubugu guncellenir', async ({ page }) => {
    await page.goto('/');

    await expect(page.locator('#window-login')).toBeHidden();

    await page.click('[data-window-open="login"]');

    await expect(page.locator('#window-login')).toBeVisible();
    expect(page.url()).toContain('window=login');
});

test('pencere kapaninca adres temizlenir', async ({ page }) => {
    await page.goto('/?window=login');

    await expect(page.locator('#window-login')).toBeVisible();

    await page.keyboard.press('Escape');

    await expect(page.locator('#window-login')).toBeHidden();
    expect(page.url()).not.toContain('window=');
});

test('pencereler arasinda gecilir', async ({ page }) => {
    await page.goto('/?window=login');

    /* Açık pencerenin İÇİNDEKİ bağlantı; başlıktaki aynı adlı bağlantı zeminin
       arkasında kalıyor — ki doğrusu da bu. */
    await page.click('#window-login [data-window-open="register"]');

    await expect(page.locator('#window-register')).toBeVisible();
    await expect(page.locator('#window-login')).toBeHidden();
});

/** Betik yüklenmese de pencere çalışmalı: sunucu onu açık gönderiyor. */
test('betiksiz de pencere acik gelir', async ({ browser }) => {
    const baglam = await browser.newContext({ javaScriptEnabled: false });
    const sayfa = await baglam.newPage();

    await sayfa.goto('/?window=login');

    await expect(sayfa.locator('#window-login')).toBeVisible();
    await expect(sayfa.locator('#window-login input[name="email"]')).toBeVisible();

    await baglam.close();
});

/**
 * Tema İKİ EKRAN ARASINDA taşınır.
 *
 * Hızlı Erişim sayfası ile panel aynı üründür; ayrı yerlerde tema sakladıkları
 * sürece kullanıcı bir ekranda koyu, diğerinde açık temayla karşılaşıyordu.
 * Aynı anahtar, aynı depo.
 */
test('tema secimi panel ile hizli erisim arasinda korunur', async ({ page }) => {
    await page.goto('/');

    const ilk = await page.evaluate(() => document.documentElement.dataset.theme);

    await page.click('#theme');
    const secilen = await page.evaluate(() => document.documentElement.dataset.theme);
    expect(secilen).not.toBe(ilk);

    /* Aynı seçim panelin okuduğu yerde durmalı. */
    const saklanan = await page.evaluate(() => localStorage.getItem('tema'));
    expect(saklanan).toBe(secilen);

    await page.reload();
    expect(await page.evaluate(() => document.documentElement.dataset.theme)).toBe(secilen);
});

/**
 * Parolada EKSİK olan yazarken söylenir.
 *
 * Tek geri bildirim sunucunun "en az 8 karakter" hatasıydı: kullanıcı formu
 * gönderene kadar yeterli olup olmadığını bilmiyordu. Karşılanan ölçüt
 * gösterilmez — ekranda yalnızca yapılacak iş durur.
 */
test('parolada eksik olan yazarken soylenir', async ({ page }) => {
    await page.goto('/?window=register');

    const uyari = page.locator('[data-password-meter]');

    /* Boşken uyarı yok: kullanıcı daha yazmadı. */
    await expect(uyari).toBeHidden();

    await page.fill('#register-password', 'abc');
    await expect(uyari).toBeVisible();
    await expect(uyari).toContainText('En az 8 karakter');
    await expect(uyari).toContainText('En az bir rakam');

    /* Karşılanan ölçüt anılmaz. */
    await expect(uyari).not.toContainText('En az bir harf');

    await page.fill('#register-password', 'abcdefg1');
    await expect(uyari).toBeHidden();

    /* Tekrar alanına dokunulmadan "eşleşmiyor" denmez. */
    await page.fill('#register-password-repeat', 'abcdefg2');
    await expect(uyari).toContainText('İki parola aynı olmalı');

    await page.fill('#register-password-repeat', 'abcdefg1');
    await expect(uyari).toBeHidden();
});

/** Kurallar "?" iminde durur: ekranı doldurmadan ulaşılabilir. */
test('parola kurallari ipucunda durur', async ({ page }) => {
    await page.goto('/?window=register');

    const im = page.locator('.window__body .hint-mark').first();

    await expect(im).toBeVisible();
    await expect(im).toHaveAttribute('title', /En az 8 karakter/);
    await expect(im).toHaveAttribute('title', /En az bir rakam/);
});

/**
 * KAYIT SONUCU EKRANDA GÖRÜNÜR.
 *
 * Mesaj oturumda `status` ile bırakılıyordu, sayfa ise `durum` diye bakıyordu:
 * hesap açılıyor ama kullanıcıya hiçbir şey söylenmiyordu. Sunucu tarafı
 * RegistrationNotificationTest'te sınanıyor; burada sınanan onun EKRANA ÇIKMASI.
 */
test('kayit sonrasi bildirim ekranda belirir', async ({ page }) => {
    const benzersiz = `gecici-${Date.now()}@ornek.test`;

    await page.goto('/?window=register');

    await page.fill('#register-name', 'Geçici Kişi');
    await page.fill('#register-email', benzersiz);
    await page.fill('#register-password', 'gecici-parola-16');
    await page.fill('#register-password-repeat', 'gecici-parola-16');
    await page.click('.window[data-window="register"] button[type="submit"]');

    const bildirim = page.locator('.toast-host .toast');

    await expect(bildirim).toBeVisible();
    await expect(bildirim).toContainText('Hesabınız açıldı');
    await expect(bildirim).toHaveClass(/toast--success/);

    /* Tıklayınca kapanır: bildirim kullanıcıyı bekletmez. */
    await bildirim.click();
    await expect(bildirim).toHaveCount(0);
});
