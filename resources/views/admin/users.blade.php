<x-layout.panel title="Kullanıcılar">
    <div class="page">
        <div class="page__inner page__inner--wide">
            <x-ui.page-head title="Kullanıcılar"
                            subtitle="Kendi kendine açılan hesap onay bekler ve onaya kadar hiçbir sisteme erişemez; buradan açılan hesap etkin doğar.">
                @if ($ekleyebilir)
                    <x-slot:actions>
                        <a href="{{ route('admin.users', ['window' => 'add']) }}"
                           class="btn btn--primary btn--sm" data-modal-open="user-add">Yeni hesap</a>
                    </x-slot:actions>
                @endif
            </x-ui.page-head>

            <x-ui.flash />

            <x-ui.panel flush>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ad</th>
                            <th>E-posta</th>
                            <th>Durum</th>
                            <th>Rol</th>
                            <th>İkinci adım</th>
                            <th>Son giriş</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($kullanicilar as $kullanici)
                            <tr>
                                <td>{{ $kullanici->name }}</td>
                                <td class="mono text-muted">{{ $kullanici->email }}</td>

                                <td>
                                    <x-ui.badge :variant="$kullanici->durum->badge()">{{ $kullanici->durum->label() }}</x-ui.badge>
                                </td>

                                <td>
                                    @if ($kullanici->rol)
                                        <x-ui.badge :variant="$kullanici->rol->badge">{{ $kullanici->rol->label }}</x-ui.badge>
                                    @else
                                        {{-- Rolsüz hesap hiçbir şey yapamaz; görünmesi gerekir. --}}
                                        <x-ui.badge variant="red">Rolsüz</x-ui.badge>
                                    @endif
                                </td>

                                <td>
                                    {{--
                                        Kimin korunduğu görünsün: zorunluluğu açmadan önce
                                        kaç kişinin hazır olduğu buradan okunur.
                                    --}}
                                    @if ($kullanici->ikiAdimliMi())
                                        <x-ui.badge variant="green">Kurulu</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="gray">Kurulu değil</x-ui.badge>
                                    @endif
                                </td>

                                <td class="mono text-muted">
                                    {{ $kullanici->son_giris_at?->translatedFormat('d.m.Y H:i') ?? '—' }}
                                </td>

                                <td>
                                    <div class="row-actions row--end">
                                        {{--
                                            Bütün alanlar PENCEREDE: satırda üç
                                            kutu dururken ad ve e-posta
                                            değiştirmenin yolu hiç yoktu.
                                        --}}
                                        @if ($duzenleyebilir || $onaylayabilir || $rolVerebilir)
                                            <a href="{{ route('admin.users', ['window' => 'edit', 'record' => $kullanici->uuid]) }}"
                                               class="btn btn--sm">Düzenle</a>
                                        @endif

                                        {{--
                                            Kişisel istisnalar KENDİ SAYFASINDA:
                                            listenin altına katlanmış duruyordu ve
                                            kaynak sayısı arttıkça hangi kutucuğun
                                            kime ait olduğu kayboluyordu.
                                        --}}
                                        @if ($rolVerebilir)
                                            <a href="{{ route('admin.users.permissions', $kullanici) }}"
                                               class="btn btn--sm">Yetkiler</a>
                                        @endif

                                        {{-- Silme yalnızca yöneticide; kendi hesabında düğme yok. --}}
                                        @if (auth()->user()->yonetici() && ! $kullanici->is(auth()->user()))
                                            <x-ui.delete-form :action="route('admin.users.destroy', $kullanici)"
                                                              confirm="{{ $kullanici->name }} ({{ $kullanici->email }}) hesabı silinecek."
                                                              title="Kullanıcıyı sil"
                                                              verify="{{ $kullanici->email }}" />
                                        @endif
                                    </div>
                                </td>
                            </tr>

                        @endforeach
                    </tbody>
                </table>
            </x-ui.panel>
        </div>
    </div>

    @if ($ekleyebilir || $duzenleyebilir)
        {{--
            Hesap açma ve bilgi düzenleme AYNI pencere: alanlar bire bir aynı.
            İki ayrı pencere yazılsaydı biri değişince diğeri sessizce eski
            kalırdı.
        --}}
        <x-ui.modal id="user-add" :title="$duzenlenen?->name ?? 'Yeni hesap'"
                    :open="$window === 'add' || $duzenlenen !== null || $errors->any()">
            <form method="POST"
                  action="{{ $duzenlenen
                      ? route('admin.users.update', $duzenlenen)
                      : route('admin.users.store') }}">
                @csrf
                @if ($duzenlenen) @method('PUT') @endif

                <div class="modal__body">
                    @if ($duzenleyebilir || ! $duzenlenen)
                    <div class="field-row">
                        <x-form.field name="name" label="Ad">
                            <x-form.input name="name" :value="$duzenlenen?->name" required />
                        </x-form.field>

                        <x-form.field name="email" label="E-posta">
                            <x-form.input name="email" type="email" :value="$duzenlenen?->email" mono required />
                        </x-form.field>
                    </div>

                    {{--
                        Düzenlemede parola BOŞ BIRAKILABİLİR: zorunlu olsaydı,
                        yalnızca adını düzeltmek isteyen yönetici her seferinde
                        kullanıcının parolasını sıfırlamak zorunda kalırdı.
                    --}}
                    <div class="field-row">
                        <x-form.field name="password" label="Parola"
                                      :hint="$duzenlenen ? 'Değiştirmek istemiyorsanız boş bırakın.' : 'En az '.config('limits.password_length').' karakter.'">
                            <x-form.input name="password" type="password" autocomplete="new-password"
                                          :required="! $duzenlenen" />
                        </x-form.field>

                        <x-form.field name="password_confirmation" label="Parola (tekrar)">
                            <x-form.input name="password_confirmation" type="password" autocomplete="new-password"
                                          :required="! $duzenlenen" />
                        </x-form.field>
                    </div>
                    @endif

                    {{--
                        KENDİ durumunu ve rolünü değiştiremezsin: "kullanıcıları
                        yönet" yetkisi kendini düşürme ya da paneli sahipsiz
                        bırakma yetkisi değildir. Adını düzeltmek serbest.
                    --}}
                    @unless ($duzenlenen?->is(auth()->user()))
                        <div class="field-row">
                            @if ($onaylayabilir)
                                <x-form.field name="status" label="Durum">
                                    <x-form.select name="status" :options="$durumSecenekleri"
                                                   :selected="$duzenlenen?->durum->value ?? 'etkin'" />
                                </x-form.field>
                            @endif

                            {{-- Rol kutusu YALNIZCA ayrı yetkiyle çizilir. --}}
                            @if ($rolVerebilir)
                                <x-form.field name="role" label="Rol">
                                    <x-form.select name="role" :options="$rolSecenekleri"
                                                   :selected="$duzenlenen?->rol?->uuid" />
                                </x-form.field>
                            @endif
                        </div>
                    @endunless
                </div>

                <x-ui.modal-actions :submit="$duzenlenen ? 'Kaydet' : 'Hesabı aç'">
                    {{-- Yetkiler ayrı sayfada; pencereden de oraya geçilebilsin. --}}
                    @if ($duzenlenen && $rolVerebilir)
                        <a href="{{ route('admin.users.permissions', $duzenlenen) }}" class="btn">Yetkiler</a>
                    @endif
                </x-ui.modal-actions>
            </form>
        </x-ui.modal>
    @endif
</x-layout.panel>
