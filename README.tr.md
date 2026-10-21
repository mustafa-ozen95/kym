# Kimlik Yönetim Merkezi

[English](README.md) · **Türkçe**

Tek uygulamada iki iş: diğer panellerin **kimlik sağlayıcısı** ve herkesin
girdiği **Hızlı Erişim** sayfası.

Kardeş projeler: EYS (parola kasası) ve NYS (ağ paneli).

Neyin **ne ölçüde** desteklendiği: [`SUPPORT.md`](SUPPORT.md).

## Merkez yetki dağıtmaz

Burada yanıtlanan tek soru "merkezin **kendisini** kim yönetir". Bir kişinin
hangi projede ne yapabileceğine o proje kendi yetki sistemiyle karar verir;
roller merkeze sızarsa modülerlik ilk özel durumda çöker.

Rol bilgi olarak döner, **emir olarak değil**: proje kabul edip etmemekte,
hangi yerel role çevirmekte ve yöneticiliği devralıp almamakta serbesttir.

## Kimlik sağlayıcı

Yalnızca **Authorization Code + PKCE** açıktır. Parola akışı kapatılmıştır
(istemciye kullanıcının parolasını verdirir), implicit akış zaten yoktur
(jetonu adres çubuğuna yazar).

| Uç | İş |
| --- | --- |
| `/.well-known/openid-configuration` | Keşif belgesi — başka dildeki projeler yalnızca bunu bilir |
| `/oauth/authorize` · `/oauth/token` | Passport'tan gelen standart uçlar |
| `/oauth/kullanici` | Kimlik bilgisi: `sub` (uuid), ad, e-posta, doğrulanmış mı, rol |
| `/api/kimlik-durumu` | Arka kanal: bağlı projeler "bu kimlikler şu an ne durumda" diye sorar |

Jeton ömrü **10 dakika**, yenileme jetonu 1 gün. `id_token` üretilmez ve keşif
belgesi bunu açıkça söyler.

### Yeni bir uygulama bağlamak

`/yonetim/uygulamalar` → "Yeni uygulama". Sır **bir kez** gösterilir. Laravel
projeleri için tek dosya üretilir (`app/KimlikMerkezi.php`): rotalar, giriş
düğmesi, PKCE akışı ve hesap eşleştirme birlikte gelir; projede yapılacaklar
üçe iner — dosyayı at, sağlayıcıyı kaydet, `@kimlikGiris` koy. Başka diller
için ekranda ham protokol ve çalışan bir Node.js örneği vardır.

Sır yenilemek verilmiş bütün jetonları düşürür: sır sızdığı için
yeniliyorsak, sızıntıyla alınmış jeton ömrü dolana kadar çalışmaya devam
ederdi. Bağlantı kaldırıldığında kayıt silinmez, iptal edilir — denetim
kaydındaki satırlar sahipsiz kalmasın diye.

## Hızlı Erişim

Kutucuklar ve salonlar kayıttır; sayfa statik değildir. Görünürlük üç
düzeydedir:

| Düzey | Kim görür |
| --- | --- |
| `herkes` | Oturum gerekmez (varsayılan) |
| `girisli` | Girmiş olan herkes |
| `yetkili` | O kutucuğun/salonun yetkisi verilmiş olanlar |

Süzme veritabanında yapılır: "hepsini getir sonra ekranda gizle" güvenlik
değildir — kaynak koda bakan kişi kutucuğun adını ve adresini yine görürdü.
Boş kalan salon tümüyle çizilmez.

Bir salon **kendi sayfası** olarak işaretlenebilir (`/salon/salon-a`).
Göremediği salona giren 404 alır, 403 değil: 403 "böyle bir sayfa var" demek
olurdu.

### Kapı ucu

`/kapi/{anahtar}` nginx'in `auth_request` yönergesi içindir: oturum yoksa 401,
yetki yoksa 403, varsa 200 ve kimlik başlıkları. Kural liste ile aynı yerden
geçer ve bir test ikisinin ayrışmadığını kilitler.

## Hesaplar

1. Kayıt herkese açıktır — ama kapatılabilir ve kapatıldığında ucun kendisi de
   kapanır; bağlantıyı gizlemek kapatmak değildir.
2. Yeni hesap **beklemede** doğar ve hiçbir yere erişemez.
3. Yönetici onaylar. Doğrulama zorunlu değilken onayı veren kişi adresin
   sahibine kefil olur.
4. Askıya alma **anında** etkilidir: her web isteğinde durum sorulur.

İki adımlı doğrulama (TOTP) elle uygulanmıştır (RFC 6238) ve doğruluğu RFC'nin
kendi test vektörleriyle sınanır. Zorunluluk yalnızca yöneticiler ya da herkes
için açılabilir; kendi ikinci adımı kurulu olmayan yönetici zorunluluğu açamaz.

## Ayarlar

Ayarlar veritabanındadır ve panelden yönetilir: `.env` değiştirmek dosya
erişimi ister ve kimin yaptığı belli olmaz. Kayıt kapısı, iki adım
zorunluluğu, e-posta doğrulama zorunluluğu ve SMTP burada. Deneme iletisi
gitmeden e-posta doğrulama zorunlu tutulamaz.

## İşletim

Kurulum deponun kökündeki `docker-compose.yml` ile (Compose standardı: MariaDB ve
redis ayrı servis, merkez yalnız HTTP; her platformda aynı dosya). Ayrıntı ve
değişkenler: `docker/README.md`, `.env.example`.

    cp .env.example .env && docker compose up -d --build   # kur / güncelle
    docker compose logs -f panel                           # kayıtlar
    docker compose exec panel supervisorctl status         # servisler

## Geliştirme kurulumu

```bash
composer setup
php artisan kimlik:yonetici-ekle --ad="Ad Soyad" --eposta=ad@ornek.test
php artisan kimlik:istemci-ekle --ad="NYS" --adres=https://ornek/giris/merkez/geri
```

Panelde "kendini yönetici yap" diye bir yol **yoktur**; ilk yönetici bu
komutla açılır.

## Testler

```bash
composer test
```

Görsel testler kabin çizimlerinde **konsolun temiz kaldığını** da sınar.

### Görsel testler

Görsel takım ÜRETİM veritabanına bakmaz: kendi sqlite'ıyla ayrı bir sunucu
açılır. Aksi hâlde testler gerçek kayıtlara göre salınır ve panel senaryoları
gerçek hesapları değiştirebilir.

```bash
S=storage/gorsel.sqlite
ORTAK="APP_ENV=local DB_CONNECTION=sqlite DB_DATABASE=$PWD/$S \
  APP_CONFIG_CACHE=/tmp/dc-gorsel-config.php APP_ROUTES_CACHE=/tmp/dc-gorsel-routes.php"

touch $S
env $ORTAK php artisan migrate --force
env $ORTAK php artisan db:seed --class=VisualSeeder --force

# Yerel sunucu: canlı adres https, tarayıcı yerelde http konuşur.
env $ORTAK APP_URL=http://127.0.0.1:8402 SESSION_SECURE_COOKIE=false \
  php artisan serve --port=8402 &

PLAYWRIGHT_BASE_URL=http://127.0.0.1:8402 \
  E2E_EMAIL=gorsel@ornek.test E2E_PASSWORD=gorsel-test-1616 \
  npx playwright test      # masaüstü + telefon
```

`VisualSeeder` sabit ve küçük bir dünya kurar: bir yönetici, iki salon, dört
kutucuk. `APP_CONFIG_CACHE`'in başka bir yolu göstermesi şarttır — üretimin
önbelleğe alınmış config'i yoksa `APP_URL`'i ezer ve varlıklar canlı adresten
istenir.

## Kurallar

Kod standartları `CLAUDE.md`'de. Tasarım sistemi EYS'den gelir; panel tarafı
kardeş projelerle aynı bileşen adlarını kullanır.
