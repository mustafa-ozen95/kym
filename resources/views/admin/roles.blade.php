<x-layout.panel title="Roller · Ayarlar">
    <div class="page">
        <div class="page__inner page__inner--wide">
            <x-ui.page-head title="Ayarlar"
                            subtitle="Rol hazır bir yetki şablonudur; o rolü taşıyan herkes bu yetkileri alır. Kişiye özel istisnalar Kullanıcılar ekranından verilir." />

            <x-ui.tabs :items="$serit" />

            <x-ui.flash />

            <x-ui.panel title="Roller"
                        subtitle="Varsayılan rol, merkeze yeni kaydolan hesaplara verilir.">
                @if ($duzenleyebilir)
                    <x-slot:actions>
                        <button type="button" class="btn btn--primary btn--sm" data-modal-open="rol-ekle">Rol ekle</button>
                    </x-slot:actions>
                @endif

                @foreach ($roller as $rol)
                    <div class="grid-row grid-row--static rol-satir">
                        <span>
                            <a href="{{ route('yonetim.roller.goster', $rol) }}" class="mono">{{ $rol->slug }}</a>
                        </span>

                        <span>
                            <x-ui.badge :variant="$rol->badge">{{ $rol->label }}</x-ui.badge>

                            @if ($rol->is_admin)
                                <x-ui.badge variant="red">yönetici</x-ui.badge>
                            @endif

                            @if ($rol->is_default)
                                <x-ui.badge variant="green">varsayılan</x-ui.badge>
                            @endif
                        </span>

                        <span class="text-muted truncate">
                            {{ $rol->kullanicilar_count }} kullanıcı ·
                            {{ $rol->is_admin ? 'bütün kontrolleri geçer' : count($rol->yetenekler()).' yetki' }}
                        </span>

                        <span class="text-right">
                            <a href="{{ route('yonetim.roller.goster', $rol) }}" class="btn btn--sm">Düzenle</a>
                        </span>
                    </div>
                @endforeach
            </x-ui.panel>
        </div>
    </div>

    @if ($duzenleyebilir)
        {{--
            Yeni rol PENCEREDEN açılır, yetkileri ayrıntı sayfasında seçilir:
            pencereye onlarca kutucuk sığdırmak, açılır açılmaz kaydırmak
            gerektiren bir form demekti.
        --}}
        <x-ui.modal id="rol-ekle" title="Rol ekle">
            <form method="POST" action="{{ route('yonetim.roller.ekle') }}">
                @csrf

                <div class="modal__body">
                    <x-form.field name="label" label="Rol adı" id="rol-adi">
                        <x-form.input name="label" id="rol-adi" required />
                    </x-form.field>

                    <x-form.field name="slug" label="Kod adı" id="rol-kod"
                                  hint="Küçük harf, rakam ve tire. Kod tarafındaki kimliktir.">
                        <x-form.input name="slug" id="rol-kod" mono required placeholder="saha-ekibi" />
                    </x-form.field>

                    <x-form.field name="description" label="Açıklama" id="rol-aciklama">
                        <x-form.input name="description" id="rol-aciklama" />
                    </x-form.field>

                    <x-form.field name="badge" label="Renk" id="rol-renk">
                        <x-form.select name="badge" id="rol-renk" :options="$rozetler" selected="gray" />
                    </x-form.field>

                    {{-- Yönetici rolü bütün kontrolleri geçer; şablonu anlamsızdır. --}}
                    <x-form.field name="is_admin" label="Yönetici" id="rol-yonetici"
                                  hint="Bütün yetki kontrollerini geçer; hazır yetki şablonu anlamsızdır.">
                        <label class="checkbox">
                            <input type="checkbox" name="is_admin" value="1" id="rol-yonetici">
                            <span>Bütün kontrolleri geçsin</span>
                        </label>
                    </x-form.field>
                </div>

                <x-ui.modal-actions submit="Ekle" />
            </form>
        </x-ui.modal>
    @endif
</x-layout.panel>
