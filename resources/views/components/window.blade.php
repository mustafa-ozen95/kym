@props(['name', 'title', 'open' => false])

{{--
    Pencere.

    ADRESİ VARDIR: ?window={{ $name }} ile doğrudan açılır ve doğrulama
    hatasında sunucu onu açık olarak geri gönderir. Betik yüklenmemişse de
    çalışır — o zaman sayfa yenilenerek açılır, kapanır.
--}}
<div class="window" id="window-{{ $name }}" data-window="{{ $name }}" role="dialog" aria-modal="true"
     aria-labelledby="window-{{ $name }}-title" @unless ($open) hidden @endunless>
    <div class="window__backdrop" data-window-close></div>

    <div class="window__box">
        <div class="window__header">
            <h2 id="window-{{ $name }}-title">{{ $title }}</h2>
            <a class="window__close" href="{{ route('quick-access') }}" data-window-close
               aria-label="Pencereyi kapat">×</a>
        </div>

        <div class="window__body">{{ $slot }}</div>
    </div>
</div>
