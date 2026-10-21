<x-layout.panel title="Hesabım">
    <div class="page">
        <div class="page__inner">
            <x-ui.page-head title="Hesabım" subtitle="Bilgileriniz, parolanız ve giriş güvenliğiniz." />

            <x-ui.flash />

            <x-ui.panel title="Bilgiler">
                <form method="POST" action="{{ route('hesap.profil.kaydet') }}" class="form-dar">
                    @csrf
                    @method('PUT')

                    <x-form.field name="name" label="Ad">
                        <x-form.input name="name" :value="$kullanici->name" required />
                    </x-form.field>

                    <x-form.field name="email" label="E-posta"
                                  hint="Girişte kullandığınız adres; projelerdeki hesabınızla eşleşmede de bu kullanılır.">
                        <x-form.input name="email" type="email" :value="$kullanici->email" mono required />
                    </x-form.field>

                    <button type="submit" class="btn btn--primary btn--sm">Kaydet</button>
                </form>
            </x-ui.panel>

            <x-ui.panel title="Parola" subtitle="Değiştirdiğinizde diğer cihazlardaki oturumlarınız kapanır.">
                <form method="POST" action="{{ route('hesap.parola') }}" class="form-dar">
                    @csrf
                    @method('PUT')

                    {{-- Mevcut parola şart: oturumu ele geçiren biri hesabı devralamasın. --}}
                    <x-form.field name="mevcut" label="Mevcut parola">
                        <x-form.input name="mevcut" type="password" autocomplete="current-password" required />
                    </x-form.field>

                    <x-form.field name="password" label="Yeni parola"
                                  hint="En az {{ config('limits.parola_uzunluk') }} karakter, harf ve rakam içermeli.">
                        <x-form.input name="password" type="password" autocomplete="new-password" required />
                    </x-form.field>

                    <x-form.field name="password_confirmation" label="Yeni parola (tekrar)">
                        <x-form.input name="password_confirmation" type="password" autocomplete="new-password" required />
                    </x-form.field>

                    <button type="submit" class="btn btn--primary btn--sm">Parolayı değiştir</button>
                </form>
            </x-ui.panel>

            <x-ui.panel title="İkinci adım"
                        :subtitle="$kullanici->ikiAdimliMi()
                            ? 'Açık: girişte parolanın ardından kod soruluyor.'
                            : 'Kapalı: girişte yalnızca parola soruluyor.'">
                <a href="{{ route('hesap.iki-adim') }}" class="btn btn--sm">
                    {{ $kullanici->ikiAdimliMi() ? 'Ayarları aç' : 'İkinci adımı kur' }}
                </a>
            </x-ui.panel>
        </div>
    </div>
</x-layout.panel>
