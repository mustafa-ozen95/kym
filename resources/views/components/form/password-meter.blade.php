{{--
    Boş bir uyarı satırı: eksik varsa JS doldurur, yoksa gizli kalır
    (bkz. password.js). Metinler sunucudan gelir; tarayıcı kural bilmez.
--}}
<p class="password-missing" data-password-meter data-field="{{ $alan }}"
   @if ($tekrar) data-repeat="{{ $tekrar }}" @endif
   data-length="{{ $uzunluk }}"
   data-criteria="{{ json_encode($olcutler, JSON_UNESCAPED_UNICODE) }}"
   aria-live="polite" hidden></p>
