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
                    <a href="{{ route('yonetim.kullanicilar') }}">Kullanıcılar</a> ekranından görebilirsiniz.
                </p>

                <form method="POST" action="{{ route('yonetim.ayarlar.kaydet') }}" class="form-dar">
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
                        <label class="field__label" for="saat-dilimi">Saat dilimi</label>
                        <select class="input" id="saat-dilimi" name="saat_dilimi" required>
                            @foreach ($saatDilimleri as $deger => $etiket)
                                <option value="{{ $deger }}" @selected(old('saat_dilimi', $saatDilimi) === $deger)>{{ $etiket }}</option>
                            @endforeach
                        </select>
                        <p class="text-sm text-muted">
                            Türkiye için <strong>Europe/Istanbul</strong>. Kayıtlardaki ve ekranlardaki
                            bütün tarih/saatler bu dilime göre gösterilir.
                        </p>
                    </div>

                    <label class="checkbox">
                        <input type="checkbox" name="kayit_acik" value="1" @checked($kayitAcik)>
                        <span>
                            Kendi kendine kayıt açık
                            <span class="text-sm text-muted">(hesap yine onay bekler)</span>
                        </span>
                    </label>

                    {{-- İki ayrı eşik: yönetici hesabı bütün sistemlerin anahtarını dağıtıyor. --}}
                    <label class="checkbox">
                        <input type="checkbox" name="iki_adim_zorunlu" value="1" @checked($yoneticiZorunlu)>
                        <span>Yöneticiler için ikinci adım zorunlu <span class="text-sm text-muted">(yönetim ekranlarında)</span></span>
                    </label>

                    <label class="checkbox">
                        <input type="checkbox" name="iki_adim_zorunlu_kullanici" value="1" @checked($kullaniciZorunlu)>
                        <span>Tüm kullanıcılar için ikinci adım zorunlu <span class="text-sm text-muted">(girişten sonra hemen istenir)</span></span>
                    </label>

                    {{--
                        Şart olan YÖNETİCİ ONAYIDIR; doğrulama isteğe bağlıdır.
                        Kapalıyken hesabı bir insan inceleyip etkinleştiriyor ve
                        onayı veren kişi adresin o kişiye ait olduğuna kefil
                        oluyor. Açıkken kefalet yerini kullanıcının tıkladığı
                        bağlantıya bırakır.
                    --}}
                    <label @class(['checkbox', 'checkbox--kilitli' => $epostaKilitli])>
                        <input type="checkbox" name="eposta_dogrulama_zorunlu" value="1"
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

                <form method="POST" action="{{ route('yonetim.ayarlar.posta') }}" class="form-dar">
                    @csrf
                    @method('PUT')

                    <div class="field">
                        <label class="field__label" for="sunucu">Sunucu adresi</label>
                        <input class="input input--mono" id="sunucu" name="sunucu" type="text"
                               value="{{ old('sunucu', $posta['sunucu']) }}" required
                               placeholder="posta.ornek.test">
                    </div>

                    <div class="field">
                        <label class="field__label" for="port">Port</label>
                        <input class="input input--mono" id="port" name="port" type="number" min="1" max="65535"
                               value="{{ old('port', $posta['port'] ?? 587) }}" required>
                    </div>

                    <div class="field">
                        <label class="field__label" for="sifreleme">Şifreleme</label>
                        <select class="input" id="sifreleme" name="sifreleme" required>
                            <option value="tls" @selected(old('sifreleme', $posta['sifreleme']) === 'tls')>STARTTLS (587)</option>
                            <option value="ssl" @selected(old('sifreleme', $posta['sifreleme']) === 'ssl')>SSL/TLS (465)</option>
                            <option value="yok" @selected(old('sifreleme', $posta['sifreleme']) === 'yok')>Yok (25)</option>
                        </select>
                    </div>

                    <div class="field">
                        <label class="field__label" for="kullanici">Kullanıcı adı</label>
                        <input class="input input--mono" id="kullanici" name="kullanici" type="text"
                               value="{{ old('kullanici', $posta['kullanici']) }}" autocomplete="off">
                    </div>

                    {{--
                        Parola ekrana HİÇ basılmaz. Boş bırakmak "sil" değil
                        "değiştirme" demektir: başka bir alanı düzeltmek isteyen
                        biri her kaydedişte parolayı sıfırlamasın.
                    --}}
                    <div class="field">
                        <label class="field__label" for="parola">Parola</label>
                        <input class="input" id="parola" name="parola" type="password" autocomplete="new-password"
                               placeholder="{{ $posta['parola_var'] ? 'Kayıtlı — değiştirmek için yazın' : 'Gerekiyorsa yazın' }}">
                    </div>

                    <div class="field">
                        <label class="field__label" for="gonderen">Gönderen adresi</label>
                        <input class="input input--mono" id="gonderen" name="gonderen" type="email"
                               value="{{ old('gonderen', $posta['gonderen']) }}" required
                               placeholder="kimlik@ornek.test">
                    </div>

                    <div class="field">
                        <label class="field__label" for="gonderen_ad">Gönderen adı</label>
                        <input class="input" id="gonderen_ad" name="gonderen_ad" type="text"
                               value="{{ old('gonderen_ad', $posta['gonderen_ad'] ?? config('app.name')) }}" required>
                    </div>

                    <button type="submit" class="btn btn--primary btn--sm">Kaydet</button>
                </form>

                {{--
                    Deneme iletisi HER ZAMAN isteği yapanın kendi adresine
                    gider; bu uç başkasına posta göndermenin yolu olmamalı.
                --}}
                <form method="POST" action="{{ route('yonetim.ayarlar.posta.deneme') }}" class="form-dar">
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
