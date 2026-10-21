@props(['ad', 'baslik', 'acik' => false])

{{--
    Pencere.

    ADRESİ VARDIR: ?pencere={{ $ad }} ile doğrudan açılır ve doğrulama
    hatasında sunucu onu açık olarak geri gönderir. Betik yüklenmemişse de
    çalışır — o zaman sayfa yenilenerek açılır, kapanır.
--}}
<div class="pencere" id="pencere-{{ $ad }}" data-pencere="{{ $ad }}" role="dialog" aria-modal="true"
     aria-labelledby="pencere-{{ $ad }}-baslik" @unless ($acik) hidden @endunless>
    <div class="pencere__zemin" data-pencere-kapat></div>

    <div class="pencere__kutu">
        <div class="pencere__ust">
            <h2 id="pencere-{{ $ad }}-baslik">{{ $baslik }}</h2>
            <a class="pencere__kapat" href="{{ route('hizli-erisim') }}" data-pencere-kapat
               aria-label="Pencereyi kapat">×</a>
        </div>

        <div class="pencere__govde">{{ $slot }}</div>
    </div>
</div>
