{{--
    Boş bir uyarı satırı: eksik varsa JS doldurur, yoksa gizli kalır
    (bkz. password.js). Metinler sunucudan gelir; tarayıcı kural bilmez.
--}}
<p class="parola-eksik" data-parola-olcut data-alan="{{ $alan }}"
   @if ($tekrar) data-tekrar="{{ $tekrar }}" @endif
   data-uzunluk="{{ $uzunluk }}"
   data-olcutler="{{ json_encode($olcutler, JSON_UNESCAPED_UNICODE) }}"
   aria-live="polite" hidden></p>
