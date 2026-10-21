<x-window ad="kayit" baslik="Kayıt ol" :acik="$pencere === 'kayit'">
    {{-- Kaydın açık olması güvenli, çünkü hesap yetkisiz doğuyor. --}}
    <p class="pencere__lead">
        Hesap açıldıktan sonra yönetici onayı bekler; onaya kadar hiçbir sisteme erişemez.
    </p>

    @if ($errors->any())
        <div class="pencere__uyari">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('kayit') }}">
        @csrf

        <div class="field">
            <label class="field__label" for="kayit-ad">Ad</label>
            <input class="input" id="kayit-ad" name="name" type="text"
                   value="{{ old('name') }}" required autocomplete="name"
                   aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
        </div>

        <div class="field">
            <label class="field__label" for="kayit-email">E-posta</label>
            <input class="input input--mono" id="kayit-email" name="email" type="email"
                   value="{{ old('email') }}" required autocomplete="email"
                   aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
        </div>

        <div class="field">
            <label class="field__label" for="kayit-parola">Parola<x-form.password-hint /></label>
            <input class="input" id="kayit-parola" name="password" type="password"
                   required autocomplete="new-password"
                   aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
        </div>

        <div class="field">
            <label class="field__label" for="kayit-parola-tekrar">Parola (tekrar)</label>
            <input class="input" id="kayit-parola-tekrar" name="password_confirmation" type="password"
                   required autocomplete="new-password">

            {{--
                Yalnızca EKSİK olan yazılır; kurallar etiketin yanındaki "?"
                iminde. Kabul kararı yine RegistrationRequest'in.
            --}}
            <x-form.password-meter alan="kayit-parola" tekrar="kayit-parola-tekrar" />
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary btn--block">Hesap aç</button>
        </div>
    </form>

    <p class="pencere__foot">
        Hesabınız var mı?
        <a href="{{ route('hizli-erisim', ['pencere' => 'giris']) }}" data-pencere-ac="giris">Giriş yapın</a>.
    </p>
</x-window>
