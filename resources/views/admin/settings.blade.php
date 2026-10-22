<x-layout.panel title="Ayarlar">
    <div class="page">
        <div class="page__inner">
            <x-ui.page-head title="Ayarlar" subtitle="Panelin davranışını belirleyen kararlar." />

            <x-ui.tabs :items="$serit" />

            <x-ui.flash />

            <x-ui.panel title="Giriş kuralları"
                        subtitle="Kimin, hangi kanıtla içeri girebileceği.">
                <p class="text-sm text-muted">
                    <strong>{{ $toplamYonetici }}</strong> yöneticiden <strong>{{ $hazirYonetici }}</strong> tanesinin,
                    toplam <strong>{{ $toplamKullanici }}</strong> hesaptan <strong>{{ $hazirKullanici }}</strong> tanesinin
                    ikinci adımı kurulu. Kimin hazır olduğunu
                    <a href="{{ route('admin.users') }}">Kullanıcılar</a> ekranından görebilirsiniz.
                </p>

                <form method="POST" action="{{ route('admin.settings.update') }}" class="form-narrow">
                    @csrf
                    @method('PUT')

                    {{--
                        KAYIT AÇIKKEN de güvenli: hesap yetkisiz doğuyor ve
                        yönetici etkinleştirene kadar hiçbir sisteme erişemiyor.
                        Kapatmak, onay kuyruğunu yabancı isteklerle doldurmamak
                        isteyen kurulumlar için.
                    --}}
                    {{--
                        Saat dilimi: denetim kaydı takibi buna bağlı. Yanlış
                        dilim, olayların gerçekte ne zaman olduğunu saatlerce
                        kaydırır; anlar UTC saklanır, ekranda buna çevrilir.
                    --}}
                    <div class="field">
                        <label class="field__label" for="timezone">Saat dilimi</label>
                        <select class="input" id="timezone" name="timezone" required>
                            @foreach ($saatDilimleri as $deger => $etiket)
                                <option value="{{ $deger }}" @selected(old('timezone', $saatDilimi) === $deger)>{{ $etiket }}</option>
                            @endforeach
                        </select>
                        <p class="text-sm text-muted">
                            Türkiye için <strong>Europe/Istanbul</strong>. Kayıtlardaki ve ekranlardaki
                            bütün tarih/saatler bu dilime göre gösterilir.
                        </p>
                    </div>

                    <label class="checkbox">
                        <input type="checkbox" name="registration_open" value="1" @checked($kayitAcik)>
                        <span>
                            Kendi kendine kayıt açık
                            <span class="text-sm text-muted">(hesap yine onay bekler)</span>
                        </span>
                    </label>

                    {{-- İki ayrı eşik: yönetici hesabı bütün sistemlerin anahtarını dağıtıyor. --}}
                    <label class="checkbox">
                        <input type="checkbox" name="two_step_required_admins" value="1" @checked($yoneticiZorunlu)>
                        <span>Yöneticiler için ikinci adım zorunlu <span class="text-sm text-muted">(yönetim ekranlarında)</span></span>
                    </label>

                    <label class="checkbox">
                        <input type="checkbox" name="two_step_required_users" value="1" @checked($kullaniciZorunlu)>
                        <span>Tüm kullanıcılar için ikinci adım zorunlu <span class="text-sm text-muted">(girişten sonra hemen istenir)</span></span>
                    </label>

                    {{--
                        Şart olan YÖNETİCİ ONAYIDIR; doğrulama isteğe bağlıdır.
                        Kapalıyken hesabı bir insan inceleyip etkinleştiriyor ve
                        onayı veren kişi adresin o kişiye ait olduğuna kefil
                        oluyor. Açıkken kefalet yerini kullanıcının tıkladığı
                        bağlantıya bırakır.
                    --}}
                    <label @class(['checkbox', 'checkbox--locked' => $epostaKilitli])>
                        <input type="checkbox" name="email_verification_required" value="1"
                               @checked($epostaZorunlu) @disabled($epostaKilitli)>
                        <span>
                            E-posta doğrulama zorunlu
                            <span class="text-sm text-muted">(yönetici onayı tek başına yetmez)</span>
                        </span>
                    </label>

                    @unless ($epostaAcilabilir)
                        <p class="text-sm text-muted">
                            Bu seçenek, aşağıdan deneme iletisi gönderilip postanın gittiği görülene kadar açılamaz:
                            posta gitmiyorken zorunluluk açılırsa yeni kayıtların tamamı sessizce kilitlenir.
                        </p>
                    @else
                        <p class="text-sm text-muted">
                            Şu anda <strong>{{ $dogrulanmamis }}</strong> hesabın adresi doğrulanmamış; zorunluluk
                            açılırsa bu hesaplar bağlantıya tıklayana kadar giriş yapamaz.
                        </p>
                    @endunless

                    <button type="submit" class="btn btn--primary btn--sm">Kaydet</button>
                </form>
            </x-ui.panel>

            <x-ui.panel title="Posta sunucusu"
                        subtitle="Doğrulama ve bildirim iletileri buradan gönderilir.">
                @if ($postaSinandi)
                    <p class="text-sm text-muted">Son başarılı deneme: <strong>{{ $postaSinandi }}</strong></p>
                @else
                    <p class="text-sm text-muted">Bu ayarlarla henüz başarılı bir gönderim yapılmadı.</p>
                @endif

                <form method="POST" action="{{ route('admin.settings.mail') }}" class="form-narrow">
                    @csrf
                    @method('PUT')

                    <div class="field">
                        <label class="field__label" for="server">Sunucu adresi</label>
                        <input class="input input--mono" id="server" name="server" type="text"
                               value="{{ old('server', $posta['server']) }}" required
                               placeholder="posta.ornek.test">
                    </div>

                    <div class="field">
                        <label class="field__label" for="port">Port</label>
                        <input class="input input--mono" id="port" name="port" type="number" min="1" max="65535"
                               value="{{ old('port', $posta['port'] ?? 587) }}" required>
                    </div>

                    <div class="field">
                        <label class="field__label" for="encryption">Şifreleme</label>
                        <select class="input" id="encryption" name="encryption" required>
                            <option value="tls" @selected(old('encryption', $posta['encryption']) === 'tls')>STARTTLS (587)</option>
                            <option value="ssl" @selected(old('encryption', $posta['encryption']) === 'ssl')>SSL/TLS (465)</option>
                            <option value="yok" @selected(old('encryption', $posta['encryption']) === 'yok')>Yok (25)</option>
                        </select>
                    </div>

                    <div class="field">
                        <label class="field__label" for="user">Kullanıcı adı</label>
                        <input class="input input--mono" id="user" name="username" type="text"
                               value="{{ old('username', $posta['username']) }}" autocomplete="off">
                    </div>

                    {{--
                        Parola ekrana HİÇ basılmaz. Boş bırakmak "sil" değil
                        "değiştirme" demektir: başka bir alanı düzeltmek isteyen
                        biri her kaydedişte parolayı sıfırlamasın.
                    --}}
                    <div class="field">
                        <label class="field__label" for="password">Parola</label>
                        <input class="input" id="password" name="password" type="password" autocomplete="new-password"
                               placeholder="{{ $posta['password_set'] ? 'Kayıtlı — değiştirmek için yazın' : 'Gerekiyorsa yazın' }}">
                    </div>

                    <div class="field">
                        <label class="field__label" for="sender">Gönderen adresi</label>
                        <input class="input input--mono" id="sender" name="sender" type="email"
                               value="{{ old('sender', $posta['sender']) }}" required
                               placeholder="kimlik@ornek.test">
                    </div>

                    <div class="field">
                        <label class="field__label" for="sender_name">Gönderen adı</label>
                        <input class="input" id="sender_name" name="sender_name" type="text"
                               value="{{ old('sender_name', $posta['sender_name'] ?? config('app.name')) }}" required>
                    </div>

                    <button type="submit" class="btn btn--primary btn--sm">Kaydet</button>
                </form>

                {{--
                    Deneme iletisi HER ZAMAN isteği yapanın kendi adresine
                    gider; bu uç başkasına posta göndermenin yolu olmamalı.
                --}}
                <form method="POST" action="{{ route('admin.settings.mail.test') }}" class="form-narrow">
                    @csrf
                    <p class="text-sm text-muted">
                        Deneme iletisi kendi adresinize gönderilir: <strong>{{ $denemeAdresi }}</strong>
                    </p>
                    <button type="submit" class="btn btn--sm" @disabled(! $postaHazir)>Deneme iletisi gönder</button>
                </form>
            </x-ui.panel>
        </div>
    </div>
</x-layout.panel>
