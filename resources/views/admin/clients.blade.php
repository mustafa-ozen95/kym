<x-layout.panel title="Uygulamalar">
    <div class="page">
        <div class="page__inner page__inner--wide">
            <x-ui.page-head title="Bağlı uygulamalar"
                            subtitle="Merkez ile giriş yapabilen projeler. Yeni bir siteyi bağlamanın tek yeri burasıdır.">
                @if ($ekleyebilir)
                    <x-slot:actions>
                        <a href="{{ route('admin.clients', ['window' => 'add']) }}"
                           class="btn btn--primary btn--sm" data-modal-open="client-add">Yeni uygulama</a>
                    </x-slot:actions>
                @endif
            </x-ui.page-head>

            <x-ui.flash />

            {{--
                SIR BİR KEZ GÖRÜNÜR.

                Sayfa yenilendiğinde kaybolur; kalıcı olsaydı ekranı açık
                unutan biri onu paylaşmış olurdu. Kaybolursa yenilenebilir —
                ama yenilemek o uygulamanın girişini o an durdurur.
            --}}
            @if (session('yeniSir'))
                <x-ui.panel title="Sır — bu ekranı kapatınca bir daha görünmeyecek">
                    <p class="text-sm text-muted">
                        Aşağıdaki bloğu projenin <span class="mono">.env</span> dosyasına yapıştırın,
                        sonra <span class="mono">php artisan config:cache</span> çalıştırın.
                    </p>

                    <pre class="code" data-copy>{{ session('yeniEnv') }}</pre>
                </x-ui.panel>
            @endif

            {{--
                Uygulamalar KATLI durur.

                Her birinin formu birden açık olunca ekran, üç uygulamada bile
                okunamaz bir form yığınına dönüşüyordu. Günlük iş "hangi
                uygulamalar bağlı" sorusudur; düzenleme nadirdir.

                <details> seçildi çünkü betik yüklenmese de çalışır ve
                tarayıcının kendi davranışıdır.
            --}}
            @forelse ($istemciler as $istemci)
                <details class="collapsible">
                    <summary class="collapsible__title">
                        <span class="collapsible__name">{{ $istemci->name }}</span>

                        @if ($istemci->revoked)
                            <x-ui.badge variant="red">Kaldırıldı</x-ui.badge>
                        @else
                            <x-ui.badge variant="green">Etkin</x-ui.badge>
                        @endif

                        <span class="collapsible__extra mono text-sm text-muted">{{ $istemci->getKey() }}</span>
                    </summary>

                    <div class="collapsible__body">
                        <p class="text-sm text-muted">
                            @if ($istemci->revoked)
                                Bağlantı kaldırıldı; bu uygulama artık merkez ile giriş yapamaz.
                            @else
                                {{ $istemci->created_at?->translatedFormat('d.m.Y') }} tarihinde bağlandı.
                            @endif
                        </p>

                        @if ($duzenleyebilir && ! $istemci->revoked)
                            <form method="POST" action="{{ route('admin.clients.update', $istemci) }}">
                                @csrf
                                @method('PUT')

                                <x-form.field name="name" label="Uygulama adı"
                                              :id="'ad-' . $istemci->getKey()">
                                    <x-form.input name="name" :id="'ad-' . $istemci->getKey()" :value="$istemci->name" required />
                                </x-form.field>

                                {{--
                                    Dönüş adresi TAM ve https olmak zorunda: Passport
                                    yalnızca birebir eşleşeni kabul eder ve kodun başka
                                    bir yere gönderilmesini engelleyen asıl koruma budur.
                                --}}
                                <x-form.field name="addresses" label="Dönüş adresleri"
                                              hint="Her satıra bir adres. Projedeki IDENTITY_REDIRECT_URI ile birebir aynı olmalı."
                                              :id="'adres-' . $istemci->getKey()">
                                    <x-form.textarea name="addresses" :id="'adres-' . $istemci->getKey()"
                                                     class="input--mono" :value="$istemci->redirect_uris" />
                                </x-form.field>

                                {{--
                                    ERİŞİM KAPISI BURADA.

                                    Hangi rolün hangi uygulamaya girebileceğine merkez
                                    karar verir; uygulamalar o kararı uygular. Eskiden
                                    her panel kendi listesini tutuyordu ve aynı karar
                                    birkaç yerde yazılıydı — biri değişince öbürleri
                                    sessizce eski kalıyordu.

                                    HİÇBİRİ İŞARETLİ DEĞİLSE KAPI AÇIKTIR: kısıt
                                    eklemek bilinçli bir adımdır, boş liste kazara
                                    kimseyi dışarıda bırakmaz.
                                --}}
                                <x-form.field name="roles" label="Girebilen roller"
                                              hint="Hiçbiri işaretli değilse merkezdeki herkes girebilir. İçeride kimin neyi yapabileceğine uygulamanın kendisi karar verir."
                                              :id="'roller-' . $istemci->getKey()">
                                    <div class="choice-list">
                                        @foreach ($roller as $rol)
                                            <label class="checkbox">
                                                <input type="checkbox" name="roles[]" value="{{ $rol->uuid }}"
                                                       @checked($istemci->roller->contains('uuid', $rol->uuid))>
                                                <span>{{ $rol->label }} <span class="text-muted mono">{{ $rol->slug }}</span></span>
                                            </label>
                                        @endforeach
                                    </div>
                                </x-form.field>

                                {{--
                                    Merkezde rolü olan biri o uygulamaya ilk geldiğinde
                                    hesabı kendiliğinden açılsın mı? Kapalıyken uygulama
                                    "eşleşen hesap yok" der ve hesabı oranın yöneticisi
                                    açar.
                                --}}
                                <div class="field">
                                    <span class="field__label">Hesap açma</span>
                                    <label class="checkbox">
                                        <input type="checkbox" name="auto_account" value="1"
                                               @checked($istemci->otomatik_hesap)>
                                        <span>Girebilen roller için hesap kendiliğinden açılsın</span>
                                    </label>
                                    <span class="field__hint">
                                        Yalnızca adresi doğrulanmış ve girebilen roldeki kimlikler için.
                                        Yöneticilik devrolmaz; yeni hesap uygulamanın varsayılan yetkileriyle doğar.
                                    </span>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="btn btn--sm btn--primary">Kaydet</button>
                                </div>
                            </form>
                        @endif

                        <details class="permission collapsible__inner">
                            <summary class="permission__title">Bu uygulamanın .env bloğu</summary>
                            <pre class="code">{{ $envBloklari[$istemci->getKey()] }}</pre>
                        </details>

                        {{--
                            PHP DIŞINDAKİ PROJELER de bağlanabilir: merkez
                            standart OAuth2 + OIDC keşfi konuşuyor. Bunu
                            yazmamak, "bizim dosyamız yoksa bağlanamazsın"
                            izlenimi verirdi.
                        --}}
                        <details class="permission collapsible__inner">
                            <summary class="permission__title">Başka bir dille bağlanmak (Node, Python, Go…)</summary>

                            <p class="text-sm text-muted">
                                Kendi dilinizin OIDC kütüphanesine yalnızca keşif adresini, kimliği ve sırrı
                                verin; uçları oradan okur. Aşağıdaki değerler bu uygulamaya aittir.
                            </p>

                            <table class="table">
                                <tbody>
                                    @foreach ($protokoller[$istemci->getKey()] as $satir)
                                        <tr>
                                            <td class="text-muted">{{ $satir['alan'] }}</td>
                                            <td class="mono">{{ $satir['deger'] }}</td>
                                            <td class="text-sm text-muted">{{ $satir['not'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            {{--
                                BİLDİRİM SIRRI burada AÇIKÇA durur.
                                OAuth sırrı gibi "bir kez göster" değildir:
                                hiçbir yetki taşımıyor, yalnızca "bu kimliği
                                yeniden sor" bildirimini imzalıyor. Bir kez
                                gösterilseydi, projeye yazmayı unutan
                                yöneticinin tek çıkışı sırrı yenilemek olurdu.
                            --}}
                            <p class="text-sm text-muted">
                                Bildirim sırrı — projenin <span class="mono">IDENTITY_NOTIFY_SECRET</span> alanına:
                            </p>
                            <p class="mono text-sm">{{ $istemci->getAttributes()['bildirim_sirri'] ?: '— (kayıt eski; sırrı yenileyin)' }}</p>

                            <p class="text-sm text-muted">Node.js örneği:</p>
                            <pre class="code">{{ $nodeOrnekleri[$istemci->getKey()] }}</pre>
                        </details>

                        @if (! $istemci->revoked)
                            <div class="form-actions">
                                {{--
                                    Laravel projeleri için TEK dosya: rotalar,
                                    düğme, akış ve eşleştirme birlikte gelir.
                                    Sır dosyanın İÇİNDE DEĞİL — dosya kaynak
                                    denetimine girebilir ve indirilenler
                                    klasöründe kalır.
                                --}}
                                <a href="{{ route('admin.clients.setup', $istemci) }}"
                                   class="btn btn--sm btn--primary">Kurulum dosyasını indir</a>

                                @if ($sirYenileyebilir)
                                    {{--
                                        Sır yenilemek girişi O AN durdurur ve verilmiş
                                        jetonları düşürür; onaysız yapılmamalı.
                                    --}}
                                    <form method="POST" action="{{ route('admin.clients.secret', $istemci) }}"
                                          data-confirm="{{ $istemci->name }} sırrı yenilenecek. Yeni sır projeye yazılana kadar merkez ile giriş çalışmayacak ve verilmiş jetonlar düşecek."
                                          data-confirm-title="Sırrı yenile">
                                        @csrf
                                        @method('PUT')
                                        <button type="submit" class="btn btn--sm">Sırrı yenile</button>
                                    </form>
                                @endif

                                @if ($silebilir)
                                    <x-ui.delete-form :action="route('admin.clients.revoke', $istemci)"
                                                      :confirm="$istemci->name . ' bağlantısı kaldırılacak ve jetonları düşecek. Kayıt silinmez; geçmiş denetim satırları sahipsiz kalmasın diye iptal edilir.'"
                                                      :verify="$istemci->name"
                                                      title="Bağlantıyı kaldır"
                                                      label="Bağlantıyı kaldır" />
                                @endif
                            </div>
                        @endif

                        {{--
                            Kaldırılmış uygulama KALICI olarak silinebilir.
                            İki adımlı olmasının sebebi: tek adımda silmek
                            yanlışlıkla silmeye geri dönüş bırakmazdı. Denetim
                            kaydı bundan etkilenmez — uygulamanın adını metin
                            olarak taşır, yabancı anahtarla bağlı değildir.
                        --}}
                        @if ($istemci->revoked && $silebilir)
                            <div class="form-actions">
                                <x-ui.delete-form :action="route('admin.clients.purge', $istemci)"
                                                  :confirm="$istemci->name . ' kaydı KALICI olarak silinecek. Jetonları ve yetkilendirme kodları da silinir. Denetim kaydındaki satırlar adı metin olarak taşıdığı için korunur. Bu işlem geri alınamaz.'"
                                                  :verify="$istemci->name"
                                                  title="Kaydı kalıcı olarak sil"
                                                  label="Kalıcı olarak sil" />
                            </div>
                        @endif
                    </div>
                </details>
            @empty
                <x-ui.empty title="Henüz bağlı uygulama yok"
                            text="Sağ üstteki düğmeden bir uygulama bağlayın; adımlar en altta yazılı." />
            @endforelse

            {{--
                Adımlar KODDAN üretilir (bkz. IntegrationGuide).

                Ayrı bir belgeye yazılsaydı ilk adres değişikliğinde yanlış
                talimat vermeye başlardı ve bunu ancak yeni bağlanan proje
                çalışmadığında fark ederdik.
            --}}
            <x-ui.panel title="Laravel projesinde yapılacaklar"
                        subtitle="Üç adım. Başka dildeki projeler için her uygulamanın altındaki protokol bölümüne bakın.">
                @foreach ($adimlar as $adim)
                    <div class="step">
                        <h4>{{ $loop->iteration }}. {{ $adim['baslik'] }}</h4>
                        <p class="text-sm text-muted">{{ $adim['aciklama'] }}</p>
                    </div>
                @endforeach

                <p class="text-sm text-muted">
                    Dil fark etmez: merkez standart OAuth2 + OpenID Connect keşfi konuşur.
                    Her şey bu adreste yazılı —
                    <a href="{{ $kesifAdresi }}" class="mono">{{ $kesifAdresi }}</a>
                </p>
            </x-ui.panel>
        </div>
    </div>

    @if ($ekleyebilir)
        @include('admin.partials.client-window')
    @endif
</x-layout.panel>
