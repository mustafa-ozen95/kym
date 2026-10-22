@if ($goster)
    {{--
        Yalnızca yöneticiye: sertifikayı yenileyecek kişi odur, diğerleri
        için yapabilecekleri bir şey olmayan bir uyarı gürültüdür.

        Yenileme PANELDEN yapılır; uyarı da oraya götürür.

        "Süre dolarsa tarayıcı paneli tümüyle açmaz" denmez: bu yalnız HSTS
        açıkken doğrudur. HSTS varsayılan olarak kapalı (bkz.
        config/security.php) — süresi dolmuş sertifikada tarayıcı uyarı
        gösterip geçmeye izin verir.
    --}}
    <div class="alert alert--warning alert--spaced" role="alert">
        <strong>TLS sertifikasının süresi doluyor.</strong>
        {{ $durum['kalan_gun'] }} gün kaldı ({{ $durum['bitis'] }}).
        <a href="{{ route('admin.certificate') }}">Ayarlar → Sertifika</a>
        ekranından yeni tur başlatın: TXT kaydı orada görünür, DNS'e ekleyip
        onaylamanız yeter.
    </div>
@endif
