<x-window name="register" title="Kayıt ol" :open="$window === 'register'">
    {{-- Kaydın açık olması güvenli, çünkü hesap yetkisiz doğuyor. --}}
    <p class="window__lead">
        Hesap açıldıktan sonra yönetici onayı bekler; onaya kadar hiçbir sisteme erişemez.
    </p>

    @if ($errors->any())
        <div class="window__warning">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="field">
            <label class="field__label" for="register-name">Ad</label>
            <input class="input" id="register-name" name="name" type="text"
                   value="{{ old('name') }}" required autocomplete="name"
                   aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
        </div>

        <div class="field">
            <label class="field__label" for="register-email">E-posta</label>
            <input class="input input--mono" id="register-email" name="email" type="email"
                   value="{{ old('email') }}" required autocomplete="email"
                   aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
        </div>

        <div class="field">
            <label class="field__label" for="register-password">Parola<x-form.password-hint /></label>
            <input class="input" id="register-password" name="password" type="password"
                   required autocomplete="new-password"
                   aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
        </div>

        <div class="field">
            <label class="field__label" for="register-password-repeat">Parola (tekrar)</label>
            <input class="input" id="register-password-repeat" name="password_confirmation" type="password"
                   required autocomplete="new-password">

            {{--
                Yalnızca EKSİK olan yazılır; kurallar etiketin yanındaki "?"
                iminde. Kabul kararı yine RegistrationRequest'in.
            --}}
            <x-form.password-meter alan="register-password" tekrar="register-password-repeat" />
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary btn--block">Hesap aç</button>
        </div>
    </form>

    <p class="window__footer">
        Hesabınız var mı?
        <a href="{{ route('quick-access', ['window' => 'login']) }}" data-window-open="login">Giriş yapın</a>.
    </p>
</x-window>
