<x-layout.panel title="Sistemler">
    <div class="page">
        <div class="page__inner page__inner--wide">
            <x-ui.page-head title="Sistemler"
                            subtitle="Hızlı Erişim sayfasındaki kutucuklar. Buradan eklenir, düzenlenir ve kaldırılır.">
                <x-slot:actions>
                    <a href="{{ route('yonetim.sistemler', ['pencere' => 'ekle']) }}"
                       class="btn btn--primary btn--sm" data-modal-open="sistem-ekle">Yeni kutucuk</a>
                </x-slot:actions>
            </x-ui.page-head>

            <x-ui.flash />

            {{--
                Kutucuklar SALON SALON listelenir.

                Tek düz tabloda hangi kutucuğun hangi bölümde durduğu ancak
                "Grup" sütunu okunarak anlaşılıyordu; sayfadaki yerleşim ise
                salon sırasıyla. Ekran artık sayfayla aynı düzeni gösteriyor,
                bu yüzden grup sütununa da gerek kalmadı.
            --}}
            @foreach ($salonlar as $salon)
                {{--
                    İlk salon AÇIK gelir: sayfa tümüyle katlı açılsaydı ekranda
                    yalnızca salon adları kalır ve "kutucuklar nerede" sorusu
                    doğardı.
                --}}
                <x-ui.katlanir :title="$salon->ad"
                               :subtitle="($salon->ayri_sayfa ? 'kendi sayfası /salon/'.$salon->anahtar : 'ana sayfada')
                                   . ' · ' . ($salonaGore[$salon->anahtar] ?? collect())->count() . ' kutucuk'"
                               :open="$loop->first">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Kutucuk</th>
                                <th>Adres</th>
                                <th>Şerit</th>
                                <th>Durum</th>
                                <th>Görünürlük</th>
                                <th></th>
                            </tr>
                        </thead>
                        {{--
                            Satırlar SÜRÜKLENEREK sıralanır; sıra sayısı elle
                            yazılmaz. `data-sira` bırakma anında yeni dizilimin
                            gönderileceği adresi taşır.
                        --}}
                        <tbody data-sira="{{ route('yonetim.sistemler.sirala', $salon->anahtar) }}"
                               data-sira-alan="sira">
                            @forelse ($salonaGore[$salon->anahtar] ?? [] as $sistem)
                                <tr draggable="true" data-sira-kimlik="{{ $sistem->uuid }}">
                                    <td>
                                        {{-- Tutamak ayrı bir hedef değil, "buradan sürüklenir" ipucu. --}}
                                        <span class="tutamak" aria-hidden="true">⣿</span>

                                        {{-- Renk, kaydı tanımanın en hızlı yolu; kartın ışığı da bu. --}}
                                        <span class="renk-noktasi" data-nokta="{{ $sistem->renk }}"></span>{{ $sistem->ad }}
                                        @if ($sistem->kod)
                                            <span class="text-sm text-muted mono">{{ $sistem->kod }}</span>
                                        @endif
                                    </td>
                                    <td class="mono text-muted truncate">{{ $sistem->adres ?: '—' }}</td>
                                    <td class="text-muted text-sm">{{ $seritler[$sistem->serit] ?? '—' }}</td>
                                    <td><x-ui.badge :variant="$sistem->durum_rengi">{{ $sistem->durum_etiketi }}</x-ui.badge></td>

                                    {{--
                                        Görünürlük listede DE görünsün: "yetkili"
                                        yaptığı kutucuğu kimin göreceğini merak eden
                                        kişi, kaydı tek tek açmak zorunda kalmasın.
                                    --}}
                                    <td>
                                        @if ($sistem->gorunurluk === 'yetkili')
                                            <x-ui.badge variant="purple">Yetkili</x-ui.badge>
                                            <span class="mono text-sm text-muted">{{ $sistem->anahtar }}</span>
                                        @elseif ($sistem->gorunurluk === 'girisli')
                                            <x-ui.badge variant="blue">Girişli</x-ui.badge>
                                        @else
                                            <span class="text-sm text-muted">Herkes</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="row-actions row--end">
                                            <a href="{{ route('yonetim.sistemler', ['pencere' => 'duzenle', 'kayit' => $sistem->uuid]) }}"
                                               class="btn btn--sm">Düzenle</a>

                                            <x-ui.delete-form :action="route('yonetim.sistemler.sil', $sistem)"
                                                              confirm="{{ $sistem->ad }} kutucuğu sayfadan kaldırılacak."
                                                              title="Kutucuğu sil"
                                                              verify="{{ $sistem->ad }}" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <x-ui.empty title="Bu salon boş"
                                                    text="Sayfada bölüm olarak çizilmez; bir kutucuk eklenince görünür." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-ui.katlanir>
            @endforeach

            {{--
                SALONU OLMAYAN kutucuklar.

                Silinmiş bir salonun ardında kalan kayıt listede hiç çıkmasaydı
                kimse düzeltemezdi; sayfada da çizilmediği için sessizce
                kaybolurdu.
            --}}
            @if ($sahipsiz->isNotEmpty())
                {{-- Bu bölüm AÇIK gelir: burada bir kayıt varsa düzeltilmesi gerekiyor. --}}
                <x-ui.katlanir title="Salonu olmayan kutucuklar"
                               subtitle="sayfada çizilmezler; düzenleyip bir salona taşıyın"
                               open>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Kutucuk</th>
                                <th>Adres</th>
                                <th>Şerit</th>
                                <th>Durum</th>
                                <th>Görünürlük</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sahipsiz as $sistem)
                                <tr>
                                    <td>
                                        {{-- Tutamak ayrı bir hedef değil, "buradan sürüklenir" ipucu. --}}
                                        <span class="tutamak" aria-hidden="true">⣿</span>

                                        {{-- Renk, kaydı tanımanın en hızlı yolu; kartın ışığı da bu. --}}
                                        <span class="renk-noktasi" data-nokta="{{ $sistem->renk }}"></span>{{ $sistem->ad }}
                                        @if ($sistem->kod)
                                            <span class="text-sm text-muted mono">{{ $sistem->kod }}</span>
                                        @endif
                                    </td>
                                    <td class="mono text-muted truncate">{{ $sistem->adres ?: '—' }}</td>
                                    <td class="text-muted text-sm">{{ $seritler[$sistem->serit] ?? '—' }}</td>
                                    <td><x-ui.badge :variant="$sistem->durum_rengi">{{ $sistem->durum_etiketi }}</x-ui.badge></td>

                                    {{--
                                        Görünürlük listede DE görünsün: "yetkili"
                                        yaptığı kutucuğu kimin göreceğini merak eden
                                        kişi, kaydı tek tek açmak zorunda kalmasın.
                                    --}}
                                    <td>
                                        @if ($sistem->gorunurluk === 'yetkili')
                                            <x-ui.badge variant="purple">Yetkili</x-ui.badge>
                                            <span class="mono text-sm text-muted">{{ $sistem->anahtar }}</span>
                                        @elseif ($sistem->gorunurluk === 'girisli')
                                            <x-ui.badge variant="blue">Girişli</x-ui.badge>
                                        @else
                                            <span class="text-sm text-muted">Herkes</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="row-actions row--end">
                                            <a href="{{ route('yonetim.sistemler', ['pencere' => 'duzenle', 'kayit' => $sistem->uuid]) }}"
                                               class="btn btn--sm">Düzenle</a>

                                            <x-ui.delete-form :action="route('yonetim.sistemler.sil', $sistem)"
                                                              confirm="{{ $sistem->ad }} kutucuğu sayfadan kaldırılacak."
                                                              title="Kutucuğu sil"
                                                              verify="{{ $sistem->ad }}" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-ui.katlanir>
            @endif
        </div>
    </div>

    @include('yonetim.partials.sistem-penceresi')
</x-layout.panel>
