{{-- Pencerenin açık gelip gelmeyeceği ve form hedefi controller'dan gelir. --}}
<x-ui.modal id="sistem-ekle" :title="$duzenlenen ? $duzenlenen->ad : 'Yeni kutucuk'" wide :open="$pencereAcik">
    <form method="POST" action="{{ $pencereHedefi }}">
        @csrf
        @if ($duzenlenen) @method('PUT') @endif

        <div class="modal__body">
            <div class="field-row">
                <x-form.field name="ad" label="Ad">
                    <x-form.input name="ad" :value="$duzenlenen?->ad" required />
                </x-form.field>

                <x-form.field name="kod" label="Kod" hint="Kabin etiketinde görünür.">
                    <x-form.input name="kod" :value="$duzenlenen?->kod" placeholder="CRM-01" mono />
                </x-form.field>
            </div>

            <x-form.field name="aciklama" label="Açıklama">
                <x-form.input name="aciklama" :value="$duzenlenen?->aciklama" />
            </x-form.field>

            <x-form.field name="adres" label="Adres"
                          hint="Boş bırakılırsa kutucuk tıklanmaz; adres beklendiği yazar.">
                <x-form.input name="adres" :value="$duzenlenen?->adres" placeholder="https://ornek.ornek.test" mono />
            </x-form.field>

            <div class="field-row">
                <x-form.field name="grup" label="Grup">
                    <x-form.select name="grup" :options="$gruplar" :selected="$duzenlenen?->grup" />
                </x-form.field>

                <x-form.field name="renk" label="Renk" hint="Kabin ışığı ve şerit bu renkten beslenir.">
                    <input type="color" name="renk" class="input input--renk"
                           value="{{ old('renk', $duzenlenen?->renk ?? '#79c8a4') }}" required>
                </x-form.field>
            </div>

            <div class="field-row">
                {{-- Şeritler elle çizilmiş animasyonlar: serbest metin değil, seçim. --}}
                <x-form.field name="serit" label="Şerit" hint="Kartın altındaki animasyon.">
                    <x-form.select name="serit" :options="['' => '— yok —', ...$seritler]" :selected="$duzenlenen?->serit" />
                </x-form.field>

                {{--
                    DURUM TEK SORU: sayfada mı, değil mi.

                    Önce iki alan vardı — burada "çevrimiçi / planlı bakım",
                    aşağıda "sayfada görünsün" — ve ikisi aynı şeyi farklı
                    sözcüklerle soruyordu. Üstelik "planlı bakım" ölü veriydi:
                    çizim betiği ona hiç bakmıyor, bakımdaki kutucuk sayfada
                    normal görünüyordu.
                --}}
                <x-form.field name="etkin" label="Durum"
                              hint="Pasif kutucuk sayfada hiç çizilmez.">
                    <x-form.select name="etkin"
                                   :options="['1' => 'Aktif', '0' => 'Pasif']"
                                   :selected="(string) (int) ($duzenlenen?->etkin ?? true)" />
                </x-form.field>
            </div>

            <div class="field-row">
                <x-form.field name="yan_etiket" label="Yan bağlantı" hint="İsteğe bağlı ikinci adres.">
                    <x-form.input name="yan_etiket" :value="$duzenlenen?->yan_etiket" placeholder="Yedekler" />
                </x-form.field>

                <x-form.field name="yan_adres" label="Yan bağlantı adresi">
                    <x-form.input name="yan_adres" :value="$duzenlenen?->yan_adres" mono />
                </x-form.field>
            </div>

            {{--
                GÖRÜNÜRLÜK: kutucuğu kim görecek.

                "Yetkili" seçildiğinde kutucuk, yetkisi olmayana HİÇ görünmez —
                sönük ya da kilitli değil, listede yok. Yetki, Ayarlar >
                Roller ekranında bu kutucuğun adıyla belirir.
            --}}
            <div class="field-row">
                <x-form.field name="gorunurluk" label="Görünürlük"
                              hint="Yetkili seçilirse kutucuk, yetkisi olmayanın sayfasında hiç görünmez.">
                    <x-form.select name="gorunurluk"
                                   :options="[
                                       'herkes' => 'Herkes görsün',
                                       'girisli' => 'Yalnızca giriş yapanlar',
                                       'yetkili' => 'Yalnızca yetkisi olanlar',
                                   ]"
                                   :selected="$duzenlenen?->gorunurluk ?? 'herkes'" />
                </x-form.field>

                <x-form.field name="anahtar" label="Anahtar"
                              hint="Yetki adının okunur yarısı. Boş bırakılırsa addan türetilir.">
                    <x-form.input name="anahtar" :value="$duzenlenen?->anahtar" mono />
                </x-form.field>
            </div>

            {{-- Sıra alanı YOK: dizilim listede sürüklenerek verilir. --}}
        </div>

        <x-ui.modal-actions :submit="$duzenlenen ? 'Kaydet' : 'Ekle'" />
    </form>
</x-ui.modal>
