<x-layout.panel :title="$kullanici->name.' yetkileri'">
    <div class="page">
        <div class="page__inner page__inner--wide">
            <x-ui.page-head :title="$kullanici->name" :subtitle="$kullanici->email">
                <x-slot:actions>
                    @if ($kullanici->rol)
                        <x-ui.badge :variant="$kullanici->rol->badge">{{ $kullanici->rol->label }}</x-ui.badge>
                    @else
                        <x-ui.badge variant="red">Rolsüz</x-ui.badge>
                    @endif

                    <a href="{{ route('yonetim.kullanicilar') }}" class="btn btn--sm">Kullanıcılar</a>
                </x-slot:actions>
            </x-ui.page-head>

            <x-ui.flash />

            @if ($kendisi)
                {{--
                    KENDİ YETKİNİ DEĞİŞTİREMEZSİN.

                    Değiştirebilseydi "yetki dağıt" yetkisi, kendine her şeyi
                    verme yetkisine dönüşürdü.
                --}}
                <x-ui.panel title="Kendi hesabınız">
                    <p class="text-sm text-muted">
                        Kendi yetkilerinizi bu ekrandan değiştiremezsiniz. Aşağıdaki liste
                        yalnızca şu an neleri yapabildiğinizi gösterir.
                    </p>
                </x-ui.panel>
            @endif

            {{--
                MERKEZİN YÖNETİMİ YALNIZCA YÖNETİCİDEYKEN yönetim satırları
                listeye hiç girmez (bkz. ekranSatirlari); kutucuk ve salon
                erişimleri kalır. Seçilip hiçbir şey açmayan bir kutu,
                seçene yalan söyler.
            --}}
            @unless ($yetkiDagitilabilir)
                <x-ui.panel title="Merkezin yönetimi">
                    <p class="text-sm text-muted">
                        Yönetim ekranları (kutucuk/salon düzenleme, kullanıcılar, ayarlar,
                        uygulamalar, kayıtlar, roller) yalnızca <strong>yönetici</strong> rolündedir;
                        kişiye özel olarak verilemez ve aşağıda listelenmez. Bu kullanıcının yönetim
                        ekranlarına girmesi gerekiyorsa ona yönetici rolü verin.
                    </p>
                </x-ui.panel>
            @endunless

            <form method="POST" action="{{ route('yonetim.kullanicilar.yetkiler.kaydet', $kullanici) }}"
                  data-confirm="{{ $kullanici->name }} kullanıcısının yetkileri değişecek."
                  data-confirm-title="Yetkileri kaydet"
                  data-confirm-verify="{{ $kullanici->name }}">
                @csrf
                @method('PUT')

                @foreach ($kaynaklar as $kaynak => $tanim)
                    <x-ui.panel :title="$tanim['label']" :subtitle="$tanim['description'] ?? null">
                        @foreach ($tanim['rows'] as $yetenek => $etiket)

                            <div class="grid-row grid-row--static yetki-satir">
                                <span>
                                    {{ $etiket }}

                                    {{--
                                        Rolün verdiği yetki AYRI gösterilir:
                                        "rolden gelsin" seçiliyken bu kutunun
                                        sonucunun ne olduğu başka türlü
                                        anlaşılmıyordu.
                                    --}}
                                    @if (in_array($yetenek, $sablon, true))
                                        <x-ui.badge variant="green">rolde var</x-ui.badge>
                                    @endif
                                </span>

                                <span class="mono text-sm text-muted truncate">{{ $yetenek }}</span>

                                <span>
                                    <x-form.select :name="'yetkiler['.$yetenek.']'"
                                                   :options="[
                                                       '' => 'Rolden gelsin',
                                                       'izin' => 'İzin ver',
                                                       'red' => 'Reddet',
                                                   ]"
                                                   :selected="$istisnalar[$yetenek] ?? ''"
                                                   :disabled="$kendisi" />
                                </span>
                            </div>
                        @endforeach
                    </x-ui.panel>
                @endforeach

                @unless ($kendisi)
                    <div class="form-actions">
                        <button type="submit" class="btn btn--primary">Yetkileri kaydet</button>
                    </div>
                @endunless
            </form>
        </div>
    </div>
</x-layout.panel>
