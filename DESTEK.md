# Neler destekleniyor

## Kimlik sağlayıcı — OAuth 2.0

| Akış | Durum | Neden |
| --- | --- | --- |
| **Authorization Code + PKCE** | ✅ Tek açık akış | — |
| Parola (password grant) | ❌ Kapalı | İstemciye kullanıcının parolasını verdirir |
| Implicit | ❌ Yok | Jetonu adres çubuğuna yazar |

| Uç | İş |
| --- | --- |
| `/.well-known/openid-configuration` | Keşif belgesi — başka dildeki projeler yalnızca bunu bilir |
| `/oauth/authorize` · `/oauth/token` | Standart uçlar |
| `/oauth/kullanici` | `sub` (uuid), ad, e-posta, doğrulanmış mı, rol |
| `/api/kimlik-durumu` | Arka kanal: bağlı projeler durumu sorar |

Jeton ömrü **10 dakika**, yenileme jetonu 1 gün. `id_token` **üretilmez** ve
keşif belgesi bunu açıkça söyler.

**Merkez yetki dağıtmaz.** Rol bilgi olarak döner, emir olarak değil: bağlı
proje kabul edip etmemekte, hangi yerel role çevirmekte ve yöneticiliği
devralıp almamakta serbesttir.

## Bağlanabilen projeler

| Dil / çatı | Ne ölçüde |
| --- | --- |
| **Laravel** | Tek dosya üretilir (`app/KimlikMerkezi.php`): rotalar, giriş düğmesi, PKCE akışı ve hesap eşleştirme birlikte gelir |
| **Diğer diller** | Ekranda ham protokol ve çalışan bir Node.js örneği |

Keşif belgesini okuyabilen her istemci bağlanabilir; panelin Laravel olması
gerekmez.

### Giriş yetmez: rol ve durum sonradan da değişir

Bağlanmak girişten ibaret değildir. Merkezde biri askıya alınınca ya da rolü
değişince projenin bunu öğrenmesi gerekir; yoksa erişimi kaldırılan kişi
projede çalışmaya devam eder.

**MERKEZ HER ZAMAN EZER.** Projede yerel olarak yapılan durum/rol değişikliği
o projede kalır ve merkezi etkilemez; merkezden yapılan ise bir sonraki
eşitlemede projedekini ezer. Tek doğruluk kaynağı merkezdir.

İki yol birlikte çalışır:

| Yol | Ne zaman | Gecikme |
| --- | --- | --- |
| **Çekme** — proje sorar | Düzenli aralıkla (projenin ayarı) | En fazla bir tur |
| **Bildirim** — merkez söyler | Rol ya da durum değişir değişmez | Saniyeler |

Bildirim `POST {proje kökü}/api/kimlik/tazele` adresine gider, gövdesi
`{"subject": "..."}` ve başlığı `X-Kimlik-Imza: HMAC-SHA256(subject, bildirim
sırrı)`. Bildirim sırrı her istemci için ayrı üretilir ve İstemciler ekranında
yazar.

**Gövdeye güvenilmez ve güvenilmemelidir:** bildirim yeni rolü ya da durumu
TAŞIMAZ, yalnızca "bu kimliği yeniden sor" der. Proje doğruyu merkezden kendi
sorar. Bu yüzden bildirim yolu ele geçse bile kimsenin rolü yükseltilemez;
yapılabilecek tek şey projenin merkezle hizalanmasını erkene almaktır.

Bildirim kaybolabilir (ağ, yeniden başlatma, proje o an kapalı). Kaybolanın
bedeli gecikmedir, kalıcı ayrışma değil: çekme turu yedekte durur ve
kaldırılmamalıdır.

**Erişimi kalkan hesabın AÇIK OTURUMU da biter** — hem merkezde hem
projelerde. Merkezde ayrıca verilmiş OAuth jetonları iptal edilir; oturumu
kapatıp jetonu bırakmak, kapıyı kilitleyip anahtarı kapıda unutmaktır.

## İki adımlı doğrulama

**TOTP (RFC 6238)**, elle uygulanmıştır ve doğruluğu RFC'nin kendi test
vektörleriyle sınanır. Zorunluluk yalnızca yöneticiler ya da herkes için
açılabilir; kendi ikinci adımı kurulu olmayan yönetici zorunluluğu açamaz.

## Hızlı Erişim ve kapı ucu

Görünürlük üç düzeyde: `herkes`, `girisli`, `yetkili`. Süzme **veritabanında**
yapılır — "hepsini getir sonra ekranda gizle" güvenlik değildir.

`/kapi/{anahtar}` ucu nginx'in `auth_request` yönergesi içindir: oturum yoksa
401, yetki yoksa 403, varsa 200 ve kimlik başlıkları. Böylece kimlik doğrulaması
olmayan herhangi bir web uygulaması merkezin arkasına alınabilir.

## Bağımsızlık

Merkez, bağlı panellerin **giriş kapısıdır** ama zorunlu değildir: paneller
SSO kapalıyken kendi giriş formlarıyla çalışır. Merkez yalnızca tek oturum
isteniyorsa gerekir.

## E-posta değişince doğrulama düşer

Bu panel bağlı projelere (EYS, NYS) `email_verified` diye bir bilgi
gönderiyor ve o projeler kimliği yerel hesapla **ilk kez** eşleştirirken buna
bakıyor: doğrulanmış sayılan bir adres, yereldeki aynı adresli hesaba
bağlanıyor.

Bu yüzden kişi kendi profilinden e-postasını değiştirdiğinde "doğrulanmış"
işareti **silinir**. Değiştirmeseydik şu olurdu: burada onaylanmış herhangi
bir kullanıcı adresini bir meslektaşının adresiyle değiştirir, adresin
kendisine ait olduğunu hiç kanıtlamadığı hâlde iddia `true` gitmeye devam
eder ve bağlı projede o adrese karşılık gelen, henüz hiçbir kimliğe
bağlanmamış hesap onun olurdu. Kurulumdan sonra `kimlik:yonetici-ekle` ile
açılan hesap tam olarak böyledir — ilk SSO girişine kadar bağsız durur.

İşaret geri konurken bir insan devrededir: yönetici hesabı onaylar (varsayılan
kurulumda onay, adresin doğrulanması sayılır) ya da doğrulama postası gider.
Yöneticinin bir hesabın adresini değiştirmesi ayrı konudur; orada adresi
giren kişi zaten onun o kişiye ait olduğuna kefil oluyor ve işaret aynı
kayıtta yeniden konuyor.

Kural tek yerde, `User` modelinde durur; adres üç ayrı yerden
değiştirilebiliyor (profil, yönetim ekranı, konsol komutu) ve her birine ayrı
yazmak, dördüncüsü eklendiğinde unutmanın yoluydu.

## Tarayıcıya gönderilen güvenlik başlıkları

Panelin her yanıtı `X-Frame-Options`, `Content-Security-Policy`
(`frame-ancestors`), `X-Content-Type-Options`, `Referrer-Policy`,
`X-Permitted-Cross-Domain-Policies`, `Strict-Transport-Security` ve
`Permissions-Policy` taşır. Değerler `config/security.php` içindedir; hangi
kısıtın neden konduğu orada yazar.

Önemlisi çerçeve kilidi: burası bir kimlik sağlayıcısı ve çerçeveye alınmış
bir giriş ekranı, kullanıcının parolayı hangi siteye yazdığını göremediği bir
giriş ekranıdır.

Başlıklar nginx'te değil uygulamada durur — vekil ya da vhost değişince
kaybolmasınlar diye. Aynı ara katman bağlı panellerde de bulunur; biri
değişirse ötekilere de bakın.

İçerik CSP'si (`script-src` vb.) burada **yok**: görünümlerde satır içi
`<script>` blokları var ve `script-src 'self'` onları sessizce çalıştırmaz,
giriş ekranı bozulurdu. Eklenecekse önce o bloklar dosyaya taşınmalı.

## Girişte sınır yok — bilerek

İki ayrı fren var ve aynı şeyi saymadıkları için **ayrı ayarları okurlar**;
ikisi de varsayılan olarak kapalıdır ve `.env` ile açılır:

| Ayar | Ne sayar | Varsayılan |
|---|---|---|
| `LIMIT_GIRIS` | Giriş ucuna dakikada kaç istek | 0 (kapalı) |
| `LOGIN_MAX_ATTEMPTS` | Art arda kaç yanlış parola (e-posta+IP başına) | 0 (kapalı) |
| `LIMIT_IKI_ADIM` | İkinci adım kodu denemesi | 5 — **kapatılmadı** |

Sıfır "kapalı" demektir, "sıfır deneme hakkı" değil: `tooManyAttempts($k, 0)`
sayaç sıfıra eşit olduğu anda dolu sayar ve **ilk yanlış paroladan sonra**
kilitlerdi; `'throttle:0,1'` ise "dakikada sıfır istek" demektir ve giriş ucunu
herkese kapatırdı. Karar `App\Support\IstekSiniri`'ndedir ve sıfırda ara katman
hiç eklenmez.

İkinci adım sınırı bilerek açık: altı hanelik bir kod sınırsız denemeye
bırakılamaz.

Kapalıyken parola denemesinin önünde fren kalmaz; koruma dışarıdan gelmelidir.
Bağlı panellerde aynı ayarlar aynı adla bulunur.
