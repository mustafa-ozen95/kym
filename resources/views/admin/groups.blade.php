<x-layout.panel title="Salonlar">
    <div class="page">
        <div class="page__inner page__inner--wide">
            <x-ui.page-head title="Salonlar"
                            subtitle="Hızlı Erişim'deki bölümler. Bir salon kendi sayfası da olabilir; o zaman ana sayfaya karışmaz.">
                @if ($ekleyebilir)
                    <x-slot:actions>
                        <a href="{{ route('yonetim.gruplar', ['pencere' => 'ekle']) }}"
                           class="btn btn--primary btn--sm" data-modal-open="salon-ekle">Yeni salon</a>
                    </x-slot:actions>
                @endif
            </x-ui.page-head>

            <x-ui.flash />

            <x-ui.panel flush>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Salon</th>
                            <th>Anahtar</th>
                            <th>Kutucuk</th>
                            <th>Yer</th>
                            <th>Görünürlük</th>
                            <th></th>
                        </tr>
                    </thead>
                    {{-- Salonlar da sürüklenerek sıralanır (bkz. panel/components/sorting.js). --}}
                    <tbody data-sira="{{ route('yonetim.gruplar.sirala') }}" data-sira-alan="sira">
                        @forelse ($gruplar as $grup)
                            <tr draggable="true" data-sira-kimlik="{{ $grup->uuid }}">
                                <td>
                                    <span class="tutamak" aria-hidden="true">⣿</span>{{ $grup->ad }}
                                    @if ($grup->not)
                                        <span class="text-sm text-muted">{{ $grup->not }}</span>
                                    @endif
                                </td>

                                <td class="mono text-muted">{{ $grup->anahtar }}</td>
                                <td class="mono text-muted">{{ $grup->sistemler_count }}</td>

                                <td>
                                    @if ($grup->ayri_sayfa)
                                        {{-- Kendi sayfası olan salon ana sayfada bölüm olarak çizilmez. --}}
                                        <a href="{{ route('salon', $grup) }}" class="mono text-sm">/salon/{{ $grup->anahtar }}</a>
                                    @else
                                        <span class="text-sm text-muted">Ana sayfada</span>
                                    @endif
                                </td>

                                <td>
                                    @if ($grup->gorunurluk === 'yetkili')
                                        <x-ui.badge variant="purple">Yetkili</x-ui.badge>
                                    @elseif ($grup->gorunurluk === 'girisli')
                                        <x-ui.badge variant="blue">Girişli</x-ui.badge>
                                    @else
                                        <span class="text-sm text-muted">Herkes</span>
                                    @endif
                                </td>


                                <td>
                                    <div class="row-actions row--end">
                                        @if ($duzenleyebilir)
                                            <a href="{{ route('yonetim.gruplar', ['pencere' => 'duzenle', 'kayit' => $grup->uuid]) }}"
                                               class="btn btn--sm" data-modal-open="salon-ekle">Düzenle</a>
                                        @endif

                                        @if ($silebilir)
                                            <x-ui.delete-form :action="route('yonetim.gruplar.sil', $grup)"
                                                              :confirm="$grup->ad . ' salonu silinecek. İçinde kutucuk varsa silinmez.'"
                                                              :verify="$grup->ad"
                                                              title="Salonu sil" />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-ui.empty title="Salon yok" text="Kutucukların dizilebilmesi için en az bir salon gerekir." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.panel>
        </div>
    </div>

    @if ($ekleyebilir || $duzenleyebilir)
        <x-ui.modal id="salon-ekle" :title="$duzenlenen?->ad ?? 'Yeni salon'"
                    :open="$pencere === 'ekle' || $duzenlenen !== null || $errors->any()">
            <form method="POST"
                  action="{{ $duzenlenen ? route('yonetim.gruplar.guncelle', $duzenlenen) : route('yonetim.gruplar.ekle') }}">
                @csrf
                @if ($duzenlenen) @method('PUT') @endif

                <div class="modal__body">
                    <div class="field-row">
                        <x-form.field name="ad" label="Salon adı">
                            <x-form.input name="ad" :value="$duzenlenen?->ad" required />
                        </x-form.field>

                        <x-form.field name="anahtar" label="Anahtar"
                                      hint="Boş bırakılırsa addan türetilir. Kutucuklar salonu bununla tanır.">
                            <x-form.input name="anahtar" :value="$duzenlenen?->anahtar" mono />
                        </x-form.field>
                    </div>

                    <x-form.field name="not" label="Açıklama" hint="Başlığın yanındaki ince satır.">
                        <x-form.input name="not" :value="$duzenlenen?->not" />
                    </x-form.field>

                    {{-- Sıra alanı YOK: dizilim listede sürüklenerek verilir. --}}
                    <div class="field-row">
                        <x-form.field name="gorunurluk" label="Görünürlük"
                                      hint="Yetkili seçilirse salonu ve sayfasını yalnızca yetkisi olan görür.">
                            <x-form.select name="gorunurluk"
                                           :options="[
                                               'herkes' => 'Herkes görsün',
                                               'girisli' => 'Yalnızca giriş yapanlar',
                                               'yetkili' => 'Yalnızca yetkisi olanlar',
                                           ]"
                                           :selected="$duzenlenen?->gorunurluk ?? 'herkes'" />
                        </x-form.field>
                    </div>

                    {{--
                        Ayrı sayfa: salon ana sayfaya karışmaz, kendi adresinde
                        durur.
                    --}}
                    <label class="checkbox">
                        <input type="checkbox" name="ayri_sayfa" value="1" @checked($duzenlenen?->ayri_sayfa)>
                        <span>Kendi sayfası olsun <span class="text-sm text-muted">(ana sayfada bölüm olarak çizilmez)</span></span>
                    </label>
                </div>

                <x-ui.modal-actions :submit="$duzenlenen ? 'Kaydet' : 'Ekle'" />
            </form>
        </x-ui.modal>
    @endif
</x-layout.panel>
