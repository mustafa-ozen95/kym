<x-window name="verify" title="İkinci adım" :open="$window === 'verify'">
    <p class="window__lead">
        Doğrulayıcı uygulamanızdaki altı haneli kodu girin. Telefonunuza erişemiyorsanız
        kurtarma kodlarınızdan birini kullanabilirsiniz.
    </p>

    @error('code')
        <div class="window__warning">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('login.verify') }}">
        @csrf

        <div class="field">
            <label class="field__label" for="verify-code">Kod</label>
            {{-- inputmode: telefonda doğrudan sayı tuşları açılsın. --}}
            <input class="input input--mono" id="verify-code" name="code" type="text"
                   inputmode="numeric" autocomplete="one-time-code" autofocus required
                   aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary btn--block">Doğrula</button>
        </div>
    </form>

    <p class="window__footer">
        <a href="{{ route('quick-access', ['window' => 'login']) }}" data-window-open="login">Baştan giriş yapın</a>.
    </p>
</x-window>
