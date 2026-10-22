@props(['title' => null, 'crumbs' => []])

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
    <meta name="robots" content="noindex, nofollow">

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    {{-- Ana ekrana eklenen kısayol da simgeyi görsün. --}}
    <link rel="apple-touch-icon" href="{{ asset('favicon.svg') }}">

    {{-- Ad APP_NAME'den gelir; sabit yazılsaydı ad değiştiğinde sekme
         başlığı eski kalırdı. --}}
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>

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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap">

    {{--
        Panel KENDİ giriş noktasını yükler: Hızlı Erişim sayfasının biçemiyle
        aynı sayfada bulunsalardı `:root` değişkenleri birbirini ezerdi.
    --}}
    @vite(['resources/css/panel.css', 'resources/js/panel.js'])
</head>
<body>
<div class="shell">
    <x-nav.topbar :crumbs="$crumbs" />

    <x-ui.certificate-warning />
    <x-ui.update-warning />

    {{ $slot }}
</div>

<x-ui.toast-host />
<x-ui.confirm-modal />
</body>
</html>
