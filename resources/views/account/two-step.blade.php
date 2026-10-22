<x-layout.panel title="İkinci adım">
    <div class="page">
        <div class="page__inner">
            <x-ui.page-head title="İkinci adım"
                            subtitle="Parolanın yanına, telefonunuzdaki doğrulayıcı uygulamanın ürettiği altı haneli kod eklenir." />

            <x-ui.flash />

            @if ($kurtarmaKodlari)
                {{--
                    Kodlar YALNIZCA BURADA, bir kez görünür.

                    Sunucuda özetleri saklanıyor; düz metinleri hiçbir yerde
                    durmuyor. Bu ekranı kapatan bir daha göremez.
                --}}
                <x-ui.panel title="Kurtarma kodları"
                            subtitle="Bu kodlar bir daha gösterilmeyecek. Telefonunuzu kaybederseniz tek giriş yolunuz bunlar.">
                    <ul class="recovery">
                        @foreach ($kurtarmaKodlari as $kod)
                            <li class="mono">{{ $kod }}</li>
                        @endforeach
                    </ul>

                    <p class="text-sm text-muted">Her kod bir kez kullanılır.</p>
                </x-ui.panel>
            @endif

            @if ($kurulu)
                <x-ui.panel title="Açık"
                            subtitle="Girişte parolanın ardından kod sorulur.">
                    <p class="text-sm text-muted">
                        Kalan kurtarma kodu: <strong>{{ $kalanKurtarma }}</strong>
                    </p>

                    <form method="POST" action="{{ route('account.two-step.disable') }}" class="form-narrow">
                        @csrf
                        @method('DELETE')

                        {{-- Kapatmak da parola ister: oturumu ele geçiren biri kapatamasın. --}}
                        <x-form.field name="password" label="Parolanız"
                                      hint="İkinci adımı kapatmak için parolanızı doğrulayın.">
                            <x-form.input name="password" type="password" autocomplete="current-password" />
                        </x-form.field>

                        <button type="submit" class="btn btn--danger btn--sm">İkinci adımı kapat</button>
                    </form>
                </x-ui.panel>

            @elseif ($kuruluyor)
                <x-ui.panel title="Uygulamaya ekleyin"
                            subtitle="Karekodu doğrulayıcı uygulamanızla okutun, sonra ürettiği kodu girin.">
                    <div class="two-step">
                        <div class="two-step__square">
                            {{-- Karekod tarayıcıda çizilir; anahtar sunucudan çıkıp başka bir yere uğramaz. --}}
                            <canvas id="totp-qr" data-uri="{{ $uri }}" width="200" height="200"></canvas>
                        </div>

                        <div class="two-step__text">
                            <p class="text-sm text-muted">Karekodu okutamıyorsanız anahtarı elle girin:</p>
                            <p class="mono setup-key">{{ trim(chunk_split($anahtar, 4, ' ')) }}</p>

                            <form method="POST" action="{{ route('account.two-step.confirm') }}" class="form-narrow">
                                @csrf

                                <x-form.field name="code" label="Uygulamadaki kod">
                                    <x-form.input name="code" inputmode="numeric" autocomplete="one-time-code" mono />
                                </x-form.field>

                                <x-form.field name="password" label="Parolanız"
                                              hint="Yeni anahtarı bağlamak için parolanızı doğrulayın.">
                                    <x-form.input name="password" type="password" autocomplete="current-password" />
                                </x-form.field>

                                <button type="submit" class="btn btn--primary btn--sm">Onayla ve aç</button>
                            </form>
                        </div>
                    </div>
                </x-ui.panel>

            @else
                <x-ui.panel title="Kapalı"
                            subtitle="Şu anda girişte yalnızca parola soruluyor.">
                    <form method="POST" action="{{ route('account.two-step.start') }}" class="form-narrow">
                        @csrf

                        <x-form.field name="password" label="Parolanız"
                                      hint="Kuruluma başlamak için parolanızı doğrulayın.">
                            <x-form.input name="password" type="password" autocomplete="current-password" />
                        </x-form.field>

                        <button type="submit" class="btn btn--primary btn--sm">İkinci adımı kur</button>
                    </form>
                </x-ui.panel>
            @endif
        </div>
    </div>
</x-layout.panel>
