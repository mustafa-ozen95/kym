@if ($goster)
    {{--
        YALNIZCA ayar.guncelleme YETKİSİ OLANA: güncellemeyi o başlatır. Ayrıntı ve yeni
        commit'lerin listesi Ayarlar → Güncelleme'de.
    --}}
    <div class="alert alert--info alert--spaced" role="status">
        <strong>Panelin yeni sürümü var.</strong>
        Çalışan sürüm {{ $geride }} commit geride.
        <a href="{{ route('admin.update') }}">Ayarlar → Güncelleme</a>
    </div>
@endif
