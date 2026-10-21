<x-window ad="dogrulama" baslik="İkinci adım" :acik="$pencere === 'dogrulama'">
    <p class="pencere__lead">
        Doğrulayıcı uygulamanızdaki altı haneli kodu girin. Telefonunuza erişemiyorsanız
        kurtarma kodlarınızdan birini kullanabilirsiniz.
    </p>

    @error('kod')
        <div class="pencere__uyari">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('giris.dogrulama') }}">
        @csrf

        <div class="field">
            <label class="field__label" for="dogrulama-kod">Kod</label>
            {{-- inputmode: telefonda doğrudan sayı tuşları açılsın. --}}
            <input class="input input--mono" id="dogrulama-kod" name="kod" type="text"
                   inputmode="numeric" autocomplete="one-time-code" autofocus required
                   aria-invalid="{{ $errors->has('kod') ? 'true' : 'false' }}">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary btn--block">Doğrula</button>
        </div>
    </form>

    <p class="pencere__foot">
        <a href="{{ route('hizli-erisim', ['pencere' => 'giris']) }}" data-pencere-ac="giris">Baştan giriş yapın</a>.
    </p>
</x-window>
