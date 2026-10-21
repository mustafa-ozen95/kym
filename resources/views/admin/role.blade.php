<x-layout.panel :title="$rol->label.' · Roller'">
    <div class="page">
        <div class="page__inner page__inner--wide">
            <x-ui.page-head :title="$rol->label" :subtitle="$rol->description">
                <x-slot:actions>
                    <x-ui.badge :variant="$rol->badge">{{ $rol->slug }}</x-ui.badge>
                    <span class="text-sm text-muted">{{ $kullaniciSayisi }} kullanıcı</span>
                </x-slot:actions>
            </x-ui.page-head>

            <x-ui.tabs :items="$serit" />

            <x-ui.flash />

            <form method="POST" action="{{ route('yonetim.roller.guncelle', $rol) }}">
                @csrf
                @method('PUT')

                <x-ui.panel title="Rol">
                    <div class="form-grid">
                        <x-form.field name="label" label="Rol adı" id="rol-adi">
                            <x-form.input name="label" id="rol-adi" :value="$rol->label" required />
                        </x-form.field>

                        @if ($rol->is_system)
                            {{-- Yerleşik rolün kod adı değişmez: göç, testler ve şablon ona adıyla bakıyor. --}}
                            <x-form.field name="slug_sabit" label="Kod adı" id="rol-kod"
                                          hint="Yerleşik rol; kod adı değiştirilemez.">
                                <x-form.input name="slug_sabit" id="rol-kod" :value="$rol->slug" mono disabled />
                            </x-form.field>

                            <input type="hidden" name="slug" value="{{ $rol->slug }}">
                        @else
                            <x-form.field name="slug" label="Kod adı" id="rol-kod">
                                <x-form.input name="slug" id="rol-kod" :value="$rol->slug" mono required />
                            </x-form.field>
                        @endif

                        <x-form.field name="description" label="Açıklama" id="rol-aciklama">
                            <x-form.input name="description" id="rol-aciklama" :value="$rol->description" />
                        </x-form.field>

                        <x-form.field name="badge" label="Renk" id="rol-renk">
                            <x-form.select name="badge" id="rol-renk" :options="$rozetler" :selected="$rol->badge->value" />
                        </x-form.field>

                        <x-form.field name="position" label="Sıra" id="rol-sira" hint="Listede küçükten büyüğe.">
                            <x-form.input type="number" name="position" id="rol-sira" min="0" max="999"
                                          :value="$rol->position" />
                        </x-form.field>

                        <x-form.field name="is_admin" label="Yönetici" id="rol-yonetici"
                                      hint="Bütün yetki kontrollerini geçer. En az bir yönetici rolü kalmak zorunda.">
                            <label class="checkbox">
                                <input type="checkbox" name="is_admin" value="1" id="rol-yonetici"
                                       @checked($rol->is_admin)>
                                <span>Bütün kontrolleri geçsin</span>
                            </label>
                        </x-form.field>

                        <x-form.field name="is_default" label="Varsayılan rol" id="rol-varsayilan"
                                      hint="Merkeze yeni kaydolan hesaplara bu rol verilir. Tek bir rol varsayılan olabilir.">
                            <label class="checkbox">
                                <input type="checkbox" name="is_default" value="1" id="rol-varsayilan"
                                       @checked($rol->is_default)>
                                <span>Yeni hesapların varsayılanı</span>
                            </label>
                        </x-form.field>
                    </div>
                </x-ui.panel>

                {{--
                    Hazır yetkiler. Yönetici rolünde anlamsızdır: o rol zaten
                    bütün kontrolleri geçer ve kutucuk göstermek,
                    işaretlenmeyenlerin kapalı olduğu izlenimi verirdi.

                    MERKEZİN YÖNETİMİ YALNIZCA YÖNETİCİDEYKEN yönetim
                    satırları listeye hiç girmez (bkz. ekranSatirlari);
                    kutucuk ve salon ERİŞİMLERİ kalır, onlar Hızlı Erişim'in
                    görünürlüğüdür. Etkisi olmayan bir kutu göstermek,
                    işaretleyene yalan söylemektir.
                --}}
                @unless ($rol->is_admin)
                    <x-ui.panel title="Hazır yetkiler"
                                subtitle="Bu rolü taşıyan herkes işaretlenenleri yapabilir.">
                        @unless ($yetkiDagitilabilir)
                            <p class="text-sm text-muted">
                                Merkezin <strong>yönetimi</strong> (kutucuk/salon düzenleme, kullanıcılar,
                                ayarlar, uygulamalar, kayıtlar, roller) yalnızca yönetici rolündedir ve
                                burada listelenmez. Birinin yönetim ekranlarına girmesi gerekiyorsa ona
                                yönetici rolü verin. Aşağıdakiler Hızlı Erişim sayfasının görünürlüğüdür.
                            </p>
                        @endunless

                        @foreach ($kaynaklar as $kaynak => $tanim)
                            <div class="grid-row grid-row--static rol-kaynak">
                                <span>
                                    {{ $tanim['label'] }}
                                    <span class="text-sm text-muted">{{ $tanim['description'] }}</span>
                                </span>

                                <span class="row row--wrap">
                                    @foreach ($tanim['rows'] as $yetenek => $etiket)
                                        <label class="checkbox">
                                            <input type="checkbox" name="template[]"
                                                   value="{{ $yetenek }}"
                                                   @checked(in_array($yetenek, $sablon, true))>
                                            <span>{{ $etiket }}</span>
                                        </label>
                                    @endforeach
                                </span>
                            </div>
                        @endforeach
                    </x-ui.panel>
                @endunless

                @if ($duzenleyebilir)
                    <div class="form-actions">
                        <button type="submit" class="btn btn--primary">Kaydet</button>

                        {{-- Yerleşik roller silinmez: göç ve testler onlara adıyla bakıyor. --}}
                        @unless ($rol->is_system)
                            <button type="submit" form="rol-sil" class="btn btn--danger">Sil</button>
                        @endunless
                    </div>
                @endif
            </form>

            @unless ($rol->is_system)
                <form method="POST" id="rol-sil" hidden action="{{ route('yonetim.roller.sil', $rol) }}"
                      data-confirm="{{ $rol->label }} rolü silinecek. Bu rolde kullanıcı varsa silinmez."
                      data-confirm-title="Rolü sil" data-confirm-variant="danger"
                      data-confirm-verify="{{ $rol->label }}">
                    @csrf
                    @method('DELETE')
                </form>
            @endunless
        </div>
    </div>
</x-layout.panel>
