# kym — konteyner kurulumu

Kurulum deponun kökündeki `docker-compose.yml` ile yapılır. Dosya Compose
standardındadır (compose-spec.io): aynı dosya `docker compose` ile de, NineDeploy,
Coolify, Dokploy, Portainer gibi bir panelin "Git deposundan compose" kurulumuyla da
çalışır. Ayarların hepsi ortamdan gelir; liste ve açıklamalar `.env.example`'da.

    cp .env.example .env          # en az APP_URL ve DB_PASSWORD; chmod 600 .env
    docker compose up -d --build

Bir platformda: depoyu "compose" türünde ekleyin (dosya `docker-compose.yml`), ana
servis `panel`, port 80, sağlık yolu `/up`; `.env.example`'daki adları platformun
değişken ekranına girin.

**HTTP portu dışarı açılmaz.** Alan adını ve TLS'i önündeki vekil verir (platformun
kendisi ya da Caddy/nginx); merkez isteğin HTTPS olduğunu `X-Forwarded-Proto`'dan bilir.
Vekilsiz deneme: `cp docker-compose.override.example.yml docker-compose.override.yml`
(düz HTTP, port 8080).

Önde kendi Caddy'niz duruyorsa `docker/proxy.caddy` örnek kuraldır: vekil merkeze
konteyner ağından (`kym:80`) ulaşmalı, `Host` başlığını da geçirmeli (OAuth dönüş
adresi konak adına bakıyor; bkz. aşağıda "Panelleri merkeze bağlamak").


## Servisler

| Servis | Ne | Not |
|---|---|---|
| `panel` | nginx + php-fpm + kuyruk işçisi + zamanlayıcı, supervisord altında | yalnız 80 |
| `db` | MariaDB 11.4 | `DB_REPLICAS=0` ile kalkmaz (dışarıdaki veritabanı) |
| `redis` | önbellek, oturum, kuyruk | kalıcılık kapalı: yeniden başlatmada oturumlar düşer, veri düşmez |

Merkez, diğer panellerin **giriş kapısıdır**: EYS, NYS, SYD ve satış jetonu buradan
alır; merkez ayakta değilse o panellere girilemez. Kurulum bu yüzden en az parçada
tutulur.

Kuyruk işçisi süs değildir: e-posta doğrulama bildirimi `ShouldQueue`'dur. İşçi
olmadan kayıt olan kişiye doğrulama postası **hiç ulaşmaz** — iş sıraya girer ve
orada kalır.

`NETWORK_ISOLATED=true` konteynerleri bağlı panellerden, posta sunucusundan ve dışarıdan
yalıtır (taşıma ya da deneme kurulumu canlıya dokunmasın diye).


## Hacimler

Adlar `STACK_PREFIX` ile başlar (varsayılan `kym`); aynı sunucuda ikinci kurulum onu değiştirir.

| Hacim | İçerik |
|---|---|
| `kym_storage` | `storage/app`: **OAuth imza anahtarları** (`oauth/`), APP_KEY (verilmediyse), ilk parola dosyası, Ayarlar → Veritabanı'nın bağlantı dosyası (`database/`) |
| `kym_database` | db servisinin veri dizini |
| `kym_logs` | `storage/logs` |


## ⚠ APP_KEY

İki adım gizli anahtarları (`totp_secret`), kurtarma kodları ve şifrelenen her alan
buna bağlı: kaybolursa iki adım kurmuş **herkes** kilitlenir. Boş bırakılırsa ilk
açılışta üretilir ve `storage/app/app-key.txt` içinde saklanır (günlüğe basılmaz);
oradan alıp ortama yazın ve **storage hacmiyle birlikte yedekleyin**.

    docker compose exec panel cat /uygulama/storage/app/app-key.txt


## ⚠ OAuth imza anahtarları

Passport jetonları özel anahtarla imzalar. Her açılışta yenisi üretilseydi,
yeniden başlatmanın ertesinde **dağıtılmış bütün jetonlar geçersiz** olurdu:
bağlı panellerin kullanıcıları toptan dışarı düşerdi.

Bu yüzden anahtarlar ilk açılışta üretilir, `storage/app/oauth/` altında, yani
**hacimde** durur ve içerikleri ortam değişkeni olarak geçirilir
(`config/passport.php` → `PASSPORT_PRIVATE_KEY`). Dosya yolu yerine içeriğin
geçirilmesinin sebebi: uygulama dizini imajın parçası, hacim değil.


## Veritabanı

Merkez veritabanını ortamdan alır (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`,
`DB_PASSWORD`). Varsayılan compose'taki `db` servisi; parolası `DB_PASSWORD`, ilk
açılışta kullanıcı ve veritabanı bununla kurulur. Açılışta merkez veritabanını en çok
üç dakika bekler, gelmezse açılmaz (konteyner yeniden dener).

Dışarıdaki ya da platformun yönettiği bir MariaDB/MySQL: `DB_*`'ı ona çevirin ve
`DB_REPLICAS=0` deyin. Göçler konteyner açılışında koşar; verilen kullanıcının şema
değiştirme yetkisi olmalıdır.

Oturumun saat dilimi konteynerin saatidir (`DB_TIMEZONE`, varsayılan `+03:00`): UTC'de
çalışan bir sunucuda TIMESTAMP sütunları 3 saat kaymış okunmasın.

### Panelden taşıma (Ayarlar → Veritabanı)

`ayar.veritabani` yetkisi ister. Merkez hedefi sınar: bağlantı, sürüm, hedefin boş ve
şu anki veritabanından farklı olması, göçlerin ihtiyaç duyduğu yetkiler (tablo, dizin,
yabancı anahtar, değişiklik, yazma, silme). Sınama geçerse **Taşı** konteyneri yeniden
başlatır. Açılışta merkez kapalıyken şu anki veritabanı hedefe kopyalanır ve her
tablonun satır sayısı iki tarafta karşılaştırılır. Bir adım tutmazsa merkez şu anki
veritabanıyla açılır ve sebep sekmede görünür. Kopya sürerken merkez kapalıdır; bağlı
paneller o süre boyunca giriş yaptıramaz.

Panelden tanımlanan bağlantı `storage/app/database/connection.json` dosyasında APP_KEY ile
şifreli durur ve ortamdaki `DB_*` değerlerinin yerine geçer. `state.json` taşımanın
aşamasını tutar ve sır içermez. Yeniden başlatmayı konteyner içindeki `dc-restart`
yapar; bunun için yeniden başlatma politikası **`unless-stopped`** olmalı (compose'ta öyle).

Taşıma bittikten sonra eski veritabanına **kendiliğinden dönülmez**; taşıma anındaki
hâliyle durur. Bilerek dönmek için: uzak veritabanının dökümünü eskisine yükleyin,
sonra `storage/app/database` altındaki `connection.json` ve `state.json`'u silip
konteyneri yeniden başlatın. Dökümü yüklemezseniz taşımadan sonraki her şey kaybolur.

APP_KEY değişirse bağlantı dosyası çözülemez ve merkez açılmaz.


## İlk giriş

Kullanıcı tablosu boşsa başlatıcı bir yönetici açar (`ADMIN_EMAIL`; verilmezse
`admin@kimlik.local`). Hesap doğrulanmış doğar: kurulumu yapan kişinin beklemesi
gereken bir posta sunucusu henüz yok. `ADMIN_PASSWORD` boşsa parola **rastgele
üretilir** ve yalnız `storage/app/initial-password.txt` içine yazılır (günlüğe basılmaz).
Sabit bir varsayılan parola konulmaz: imajı kuran herkeste aynı giriş bulunurdu.

    docker compose exec panel cat /uygulama/storage/app/initial-password.txt

Giriş yapıp parolayı değiştirdikten sonra o dosyayı silin. Sonradan yönetici açmak
(parola sorulur, komut satırına yazılmaz):

    docker compose exec panel php artisan admin:add

`SystemSeeder` bilerek çağrılmaz — örnek kutucuklarını yeni bir kuruluma yazmak yanlış
olurdu (`SAMPLE_DATA` varsayılan kapalı).


## Panelleri merkeze bağlamak

Her panel için merkezde bir **istemci** kaydı açılır (yönetim → İstemciler).
Dönüş adresi panelin `IDENTITY_REDIRECT_URI` değeriyle **birebir** aynı olmalıdır;
port dâhil. Aradaki en küçük fark takası `redirect_uri mismatch` ile
reddettirir ve giriş hiç tamamlanmaz.

Konteynerin nginx'i bu yüzden `HTTP_HOST` başlığına portu geri ekler
(`docker/rootfs/etc/nginx/nginx.conf` içindeki `map`). Debian'ın
`fastcgi_params` dosyası portu düşürüyor; standart olmayan bir portta çalışan
merkez, kayıtlı adresle eşleşmeyen bir dönüş üretirdi.

Merkezin sertifikası genel bir otoriteden değilse (iç ağda `tls internal`, kendi
CA'nız) bağlı paneller onun kök sertifikasını ister: jeton takasını sunucu tarafında
yaparlar ve doğrulamayı kapatma seçeneği yoktur (panellerde `IDENTITY_CA_CERT`).


## Güncelleme

    git pull && docker compose up -d --build

Bir platformda: yeniden dağıtın (platform depoyu çekip derler). Göçler her açılışta
`--isolated` ile koşar; iki örnek aynı anda kalksa bile bir kez çalışır.

GitHub Actions her main'de imajı derleyip `ghcr.io/mustafa-ozen95/kym` altına da
yükler (`.github/workflows/image.yml`): hazır imaj isteyen kurulum `docker/Dockerfile`'ı
derlemek yerine onu kullanabilir.

### Güncelleme kontrolü

Ayarlar → Güncelleme (`ayar.guncelleme` yetkisi) çalışan sürümü GitHub'da main dalının **son başarılı
imaj derlemesiyle** karşılaştırır: kaç commit geride, hangi commit'ler yeni. Yön de ölçülür;
çalışan sürüm son derlemeden yeniyse "güncelleme" denmez. Kontrol her sabah 06:50'de kendiliğinden
yapılır (`update:check`). Yeni sürüm varsa bu yetkisi olanlar ekranın üstünde uyarı görür.
Ekran **yalnızca bakar**; güncelleme sunucudan, yedekle ve onayla yapılır.

- Çalışan sürüm imaja derlemede yazılır (`ARG VERSION` → `APP_VERSION`; iş akışı commit'i verir).
- Depo açık: jeton gerekmez, GitHub'a jetonsuz sorulur (sınır IP başına saatte 60 istek). Depo
  özelse `UPDATE_TOKEN` salt okunur bir jeton olmalı (fine-grained, yalnız panel depoları,
  Contents ve Actions: read). Erişilemezse kontrol "hata" der, panelin başka yeri etkilenmez.
- Çalışan sürüm derlemede imaja yazılır (`ARG VERSION`, iş akışı commit'i verir). Derlemeye commit
  vermeyen platformlarda (NineDeploy gibi) bağlamdaki `.git`'ten okunur (`docker/version-read.sh`;
  `.dockerignore` yalnız `HEAD` ve ref'leri alır).
- Merkez GitHub'a çıkamıyorsa (kapalı ağ) kontrol "erişilemedi" der.


## Sertifika sekmesi

Ayarlar → Sertifika, sunucudaki sertifika ajanının durumunu gösterir ve ona tur isteği
bırakır (`/etc/dc/sertifika-durum.json`, `/etc/dc/sertifika-panel`). Standart kurulumda
TLS'i vekil yönettiği için bu dosyalar bağlı değildir; sekme "bilinmiyor" der ve
düğmeler çalışmaz.


## Durdurma

    docker compose down

`db` servisinin kapanış payı 5 dakika (`stop_grace_period`): docker'ın varsayılan
10 saniyesi InnoDB boşaltmasına yetmeyebiliyor ve veritabanı kirli kapanıyordu.


## Sorun giderme

    docker compose logs -f panel
    docker compose exec panel supervisorctl status
    docker compose exec panel php artisan tinker
