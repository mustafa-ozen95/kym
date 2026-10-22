/**
 * Pencereler.
 *
 * Pencerenin ADRESİ VARDIR: açılınca adres çubuğuna ?window=login yazılır,
 * kapanınca silinir. Bu süs değil, gereklilik:
 *
 *  - Doğrulama hatasında sunucu sayfayı AYNI adrese döndürür ve pencere açık
 *    geri gelir; kullanıcının yazdıkları kaybolmaz.
 *  - Pencereye doğrudan bağlantı verilebilir.
 *  - Betik hiç yüklenmezse bağlantılar zaten o adrese gider; pencere sunucu
 *    tarafından açık çizilir. Yani betik olmadan da çalışır.
 */

const ADRES_ANAHTARI = 'window';

function pencere(ad) {
    return document.querySelector(`[data-window="${ad}"]`);
}

function adresYaz(ad) {
    const adres = new URL(window.location.href);

    if (ad) {
        adres.searchParams.set(ADRES_ANAHTARI, ad);
    } else {
        adres.searchParams.delete(ADRES_ANAHTARI);
    }

    window.history.replaceState({}, '', adres);
}

function ac(ad) {
    const kutu = pencere(ad);

    if (!kutu) {
        return false;
    }

    kapatHepsi();
    kutu.hidden = false;
    adresYaz(ad);
    kutu.querySelector('input:not([type=hidden]):not([type=checkbox])')?.focus();

    return true;
}

function kapatHepsi() {
    document.querySelectorAll('[data-window]').forEach((kutu) => {
        kutu.hidden = true;
    });
}

function kapat() {
    kapatHepsi();
    adresYaz(null);
}

export function init() {
    document.addEventListener('click', (olay) => {
        const acan = olay.target.closest('[data-window-open]');

        if (acan && ac(acan.dataset.windowOpen)) {
            olay.preventDefault();

            return;
        }

        if (olay.target.closest('[data-window-close]')) {
            olay.preventDefault();
            kapat();
        }
    });

    document.addEventListener('keydown', (olay) => {
        if (olay.key === 'Escape') {
            kapat();
        }
    });

    /*
     * Sunucu pencereyi açık gönderdiyse adres zaten doğrudur; JS'in yeniden
     * yazmasına gerek yok. Yalnızca odak verilir.
     */
    document.querySelector('[data-window]:not([hidden])')
        ?.querySelector('input:not([type=hidden]):not([type=checkbox])')?.focus();
}

