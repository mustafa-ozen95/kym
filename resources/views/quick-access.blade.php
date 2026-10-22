<!doctype html>
<html lang="tr" data-theme="{{ \App\Support\Theme::secilen() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        {{-- Ekran kilidi (bkz. session-lock.js): hareketsiz sekme bu süre sonunda oturumu kapatır. --}}
        <meta name="session-timeout" content="{{ (int) config('session.lifetime') }}">
        <meta name="session-logout" content="{{ route('logout') }}">
        <meta name="session-login" content="{{ route('quick-access') }}">
    @endauth
    <title>Hızlı Erişim</title>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    {{-- Ana ekrana eklenen kısayol da simgeyi görsün. --}}
    <link rel="apple-touch-icon" href="{{ asset('favicon.svg') }}">

    {{--
        TEMA SUNUCUDA ÇÖZÜLÜR.

        Seçim ÇEREZDE, localStorage'da değil: localStorage adres başınadır ve
        merkez ile paneller ayrı köklerdir. Çerez üst alan adına yazılınca
        (bkz. config/theme.php) hepsi aynı seçimi okur.

        `<head>` içinde satır içi bir betik kullanılmaz: içerik CSP'si satır
        içi betikleri engeller. Çerezi PHP de okuyabildiği için `<html>` doğru
        değerle basılır; modül betikleri ertelense de sayfa bir kare bile
        yanlış temada çizilmez.
    --}}
    <meta name="theme-domain" content="{{ config('theme.cookie_domain') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div class="head">
    {{-- Salonun kendi sayfasındayken ana sayfaya dönüş; ana sayfada çizilmez. --}}
    @if ($anaSayfa)
        <a class="link back" href="{{ $anaSayfa }}">
            <svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true">
                <path d="M10 3L5 8l5 5" fill="none" stroke="currentColor" stroke-width="1.7"
                      stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            Hızlı Erişim
        </a>
    @endif

    {{-- Salon sayfasında başlık o salonun adı; ana sayfada genel soru. --}}
    <h1>{{ $baslik ?? 'Hangi sisteme gidiyorsun?' }}</h1>
    <p>{{ $altBaslik ?? 'İç ve dış sistemler, kabin kabin.' }}</p>
    <time id="clock"></time>
    @auth
        {{--
            Hesap menüsü: çıkış tek başına dışarıda durmuyor, hesap işleriyle
            birlikte kimlik göstergesinin altında.
        --}}
        <span class="session" data-menu>
            <button type="button" class="link session__button" data-menu-open
                    aria-haspopup="true" aria-expanded="false" aria-label="Hesap menüsü">
                <span class="session__badge" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                <svg viewBox="0 0 16 16" width="11" height="11" aria-hidden="true">
                    <path d="M4 6l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

            <span class="session__list" data-menu-list hidden>
                <span class="session__who">{{ auth()->user()->name }}</span>

                {{-- Yönetim de menünün içinde: başlıkta tek bir kimlik göstergesi kalsın. --}}
                @if ($yonetimAdresi)
                    <a href="{{ $yonetimAdresi }}">Yönetim</a>
                @endif

                <a href="{{ route('account.profile') }}">Hesabım</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Çıkış</button>
                </form>
            </span>
        </span>
    @else
        {{--
            Başlıkta YALNIZCA "Giriş" durur.

            Kayıt, giriş penceresinin içindeki "Hesabım yok" bağlantısından
            açılıyor; ikisini yan yana koymak başlığı kalabalıklaştırıyor ve
            günlük kullanımda basılan düğme zaten giriş.
        --}}
        <span class="session">
            <a class="link" href="{{ route('quick-access', ['window' => 'login']) }}"
               data-window-open="login">Giriş</a>
        </span>
    @endauth

    <button class="toggle" id="theme" type="button" aria-label="Temayı değiştir">
        <span class="t-dark"><svg width="15" height="15" viewBox="0 0 16 16"><circle cx="8" cy="8" r="3.2"/><path d="M8 1v1.8M8 13.2V15M1 8h1.8M13.2 8H15M3.1 3.1l1.3 1.3M11.6 11.6l1.3 1.3M12.9 3.1l-1.3 1.3M4.4 11.6l-1.3 1.3"/></svg></span>
        <span class="t-light"><svg width="15" height="15" viewBox="0 0 16 16"><path d="M13.5 9.6A6 6 0 1 1 6.4 2.5a4.8 4.8 0 0 0 7.1 7.1z"/></svg></span>
    </button>
</div>

<main id="halls"></main>

{{--
    Bildirim PANELDEKİYLE AYNI BİLEŞENDEN çıkar.

    Burada ayrı bir kutu vardı ve iki ayrı kural doğuruyordu; panel bir
    türlü, bu sayfa başka türlü bildiriyordu. Sözleşme tek: sunucu mesajı
    `data-flash` ile bırakır, ortak betik gösterir (js/shared/toast.js).
--}}
<x-ui.toast-host />

@guest
    @include('partials.login-window')

    {{-- Kayıt kapalıysa pencere hiç basılmaz: gizlemek kapatmak değildir. --}}
    @if ($kayitAcik)
        @include('partials.registration-window')
    @endif

    @include('partials.verification-window')
@endguest

{{--
    Kutucuk listesi SUNUCUDAN gelir.

    Çizim betiğinde sabit bir dizi tutulmaz; bir sistem eklemek kod
    düzenlemek demek olmasın. Veri buradan geçer, çizim ona bakar.
--}}
<script type="application/json" id="system-data">@json($sistemler)</script>
<script type="application/json" id="group-data">@json($gruplar)</script>

</body>
</html>
