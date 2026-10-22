<x-window name="login" title="Giriş" :open="$window === 'login'">
    {{--
        Gövde, NYS ve EYS giriş ekranıyla AYNI: aynı alan etiketi, aynı
        girdi ölçüsü, aynı birincil düğme. Paneller arasında geçen kişi her
        seferinde başka bir forma bakmasın.
    --}}
    <p class="window__lead">Kimlik Yönetim Merkezi — devam etmek için oturum açın.</p>

    @error('email')
        <div class="window__warning">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="field">
            <label class="field__label" for="login-email">E-posta</label>
            <input class="input input--mono" id="login-email" name="email" type="email"
                   value="{{ old('email') }}" required autocomplete="username"
                   aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
        </div>

        <div class="field">
            <label class="field__label" for="login-password">Parola</label>
            <input class="input" id="login-password" name="password" type="password"
                   required autocomplete="current-password"
                   aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
        </div>

        {{--
            "BU CİHAZDA AÇIK TUT" YOK.

            Kalıcı bir hatırlama çerezi, oturum hareketsizlikten düşse bile
            kullanıcıyı sessizce geri içeri alırdı; hareketsizlik süresi hiçbir
            şey ifade etmezdi.

            Burası bütün panellerin giriş kapısı; açık unutulmuş bir oturum,
            kapıyı açık bırakmakla aynı şey.
        --}}
        <div class="form-actions">
            <button type="submit" class="btn btn--primary btn--block">Oturum aç</button>
        </div>
    </form>

    {{--
        Kayıt bağlantısı formun ALTINDA, .gate__foot yerinde: kayıt açıksa
        bağlantının kendisi orada durur.
    --}}
    {{--
        Kayıt kapalıyken bağlantı çizilmez; yerine ne yapılacağı yazılır.
        Çalışmayan bir bağlantı, olmayan bir özellikten kötüdür.
    --}}
    <p class="window__footer">
        @if ($kayitAcik)
            Hesabınız yok mu?
            <a href="{{ route('quick-access', ['window' => 'register']) }}" data-window-open="register">Hesap açın</a>.
        @else
            Hesap açma isteği için sistem yöneticinize başvurun.
        @endif
    </p>
</x-window>
