import { $, on } from '../lib/dom.js';

/**
 * Form pencerelerini açıp kapatır.
 *
 * Açmak için: <button data-modal-open="blok-ekle">
 * Kapatmak için: data-modal-close, Escape veya zemine tıklama.
 *
 * Sunucudan doğrulama hatasıyla dönen pencere kendini açar (bkz. init).
 */

/** Pencere açıldıysa true; sayfada yoksa false. */
function open(id) {
    const box = $(`#${CSS.escape(id)}[data-modal]`);

    if (!box) {
        return false;
    }

    box.hidden = false;
    box.querySelector('input:not([type=hidden]), select, textarea')?.focus();

    return true;
}

/**
 * Pencere kapanınca İÇİ SIFIRLANIR.
 *
 * Kapatmak yalnızca gizleseydi yarım doldurulmuş bir form, bir daha
 * açıldığında olduğu gibi karşılardı ve kullanıcı onu fark etmeden
 * gönderebilirdi.
 *
 * `form.reset()` boşaltmaz, SUNUCUNUN ÇİZDİĞİ hâle döndürür.
 */
function close(box) {
    if (!box) {
        return;
    }

    box.hidden = true;

    box.querySelectorAll('form').forEach((form) => form.reset());

    box.querySelectorAll('.field__error').forEach((hata) => hata.remove());
    box.querySelectorAll('[aria-invalid="true"]').forEach((alan) => {
        alan.setAttribute('aria-invalid', 'false');
    });

    adresiTemizle();
}

/**
 * Pencereyi açan adres parçalarını siler.
 *
 * Pencerenin ADRESİ var (?pencere=duzenle&kayit=...) ve bu bilinçli: betik
 * yüklenmese de düzenleme açılabilsin diye. Ama pencere kapandıktan sonra o
 * adres yalan söylüyordu — sayfa yenilenince pencere yeniden açılıyor,
 * paylaşılan bağlantı kapalı olması gereken bir formu açıyordu.
 *
 * `replaceState`: geçmişe yeni bir adım eklenmez, yalnızca bulunulan adım
 * düzeltilir. Aksi hâlde "geri" tuşu pencereyi tekrar açardı.
 */
function adresiTemizle() {
    const adres = new URL(window.location.href);

    if (!adres.searchParams.has('pencere') && !adres.searchParams.has('kayit')) {
        return;
    }

    adres.searchParams.delete('pencere');
    adres.searchParams.delete('kayit');

    window.history.replaceState(window.history.state, '', adres.pathname + adres.search);
}

export function init() {
    /*
     * Tetikleyici bir bağlantıysa adresi de vardır: pencere sayfada BASILI
     * DEĞİLSE tıklama engellenmez ve tarayıcı o adrese gider. Böylece
     * "pencere kapandı, düğme çalışmıyor" durumu oluşmaz.
     */
    on('click', '[data-modal-open]', (event, trigger) => {
        if (open(trigger.dataset.modalOpen)) {
            event.preventDefault();
        }
    });

    on('click', '[data-modal-close]', (event, trigger) => {
        event.preventDefault();
        close(trigger.closest('[data-modal]'));
    });

    // Zemine tıklamak kapatır; kutunun içine tıklamak kapatmaz.
    on('click', '[data-modal]', (event, box) => {
        if (event.target === box) {
            close(box);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close($('[data-modal]:not([hidden])'));
        }
    });

    acikGeleniAc();
}

/**
 * Sunucudan AÇIK gelen pencereyi gösterir.
 *
 * Ayrı bir yöntem, çünkü sayfa geçişleri tarayıcıyı yenilemiyor: gövde
 * değiştiğinde `init` yeniden çalışmıyor ve `?pencere=duzenle` ile gelen
 * pencere gizli kalıyordu — bağlantıya tıklanıyor, hiçbir şey olmuyordu.
 */
export function acikGeleniAc() {
    $('[data-modal-acik]')?.removeAttribute('hidden');

    /*
     * DOĞRULAMA HATASI OLAN PENCERE KENDİLİĞİNDEN AÇILIR.
     *
     * Sunucu hatayı alanın altına yazıyor (bkz. form/field.blade.php) ama
     * pencere kapalı döndüğünde kullanıcı hiçbir şey görmüyor: form
     * gönderiliyor, sayfa yenileniyor, pencere kapanıyor ve ekranda "bir şey
     * olmadı" izlenimi kalıyor — oysa hata oradaydı, görünmüyordu.
     *
     * YUKARIDAKİ `data-modal-acik` YETMİYOR: onu her form ayrı ayrı
     * işaretlemek zorunda ve işaret genelde "adreste hangi pencere yazıyor"
     * bilgisine bağlanmış durumda, hataya değil. İşaretlemeyi unutan ya da
     * yönlendirmede adresi koruyamayan her form sessizce bu duruma düşüyordu.
     *
     * Kural GENELDİR: içinde hata olan pencere kendini açar. Yirmi forma tek
     * tek işaret koymak, birini unutmaya açık olurdu.
     */
    const hatali = $('[data-modal] .field__error')?.closest('[data-modal]');

    if (hatali) {
        hatali.hidden = false;
        hatali.querySelector('.field__error')
            ?.closest('.field')
            ?.querySelector('input, select, textarea')
            ?.focus();
    }
}
