<x-layout.panel title="Denetim kaydı · Ayarlar">
    <div class="page">
        <div class="page__inner page__inner--wide">
            <x-ui.page-head title="Ayarlar"
                            subtitle="Kim ne yaptı, kim nereye giriş yaptı. Her satırın özeti bir öncekini de kapsar; sonradan düzenlenen bir satır zinciri kırar.">
                @if ($dogrulayabilir)
                    <x-slot:actions>
                        {{--
                            Doğrulama kaydın TAMAMINI baştan sona okur; büyük
                            kurulumda birkaç saniye sürebilir. Bu yüzden elle
                            başlatılır, her sayfa açılışında değil.
                        --}}
                        <form method="POST" action="{{ route('admin.logs.verify') }}">
                            @csrf
                            <button type="submit" class="btn btn--sm">Zinciri doğrula</button>
                        </form>
                    </x-slot:actions>
                @endif
            </x-ui.page-head>

            <x-ui.tabs :items="$serit" />

            <x-ui.flash />

            <x-ui.panel title="Kayıtlar" subtitle="En yeni satır üstte. Kayıt silinmez ve düzenlenmez.">
                <form method="GET" action="{{ route('admin.logs') }}" class="row row--wrap">
                    <x-form.select name="action"
                                   :options="['' => 'Tüm eylemler'] + array_combine($eylemler, $eylemler)"
                                   :selected="$secilenEylem" />

                    <x-form.input name="search" :value="$arama" placeholder="Özet ya da kişi adı" />

                    <button type="submit" class="btn btn--sm">Süz</button>
                </form>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Zaman</th>
                            <th>Kim</th>
                            <th>Eylem</th>
                            <th>Özet</th>
                            <th>Adres</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($kayitlar as $kayit)
                            <tr>
                                <td class="mono text-muted">{{ $kayit->created_at?->translatedFormat('d.m.Y H:i:s') }}</td>

                                <td>
                                    {{--
                                        Ad SATIRIN İÇİNDE saklı: hesap sonradan
                                        silinse bile kaydın kimden bahsettiği
                                        kaybolmasın.
                                    --}}
                                    {{ $kayit->actor_name ?? '—' }}
                                </td>

                                <td><span class="mono text-sm">{{ $kayit->eylem }}</span></td>
                                <td>{{ $kayit->ozet }}</td>
                                <td class="mono text-sm text-muted">{{ $kayit->ip ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-ui.empty title="Kayıt yok" text="Süzgeci genişletmeyi deneyin." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{ $kayitlar->links() }}
            </x-ui.panel>
        </div>
    </div>
</x-layout.panel>
