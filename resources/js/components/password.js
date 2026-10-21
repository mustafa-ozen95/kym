/**
 * Parolada eksik kalan ölçütlerin canlı uyarısı.
 *
 * Kural ve metinler SUNUCUDAN gelir (bkz. PasswordRule, PasswordMeter): en az
 * kaç karakter istendiği ve her ölçütün adı elementin kendi niteliklerinde
 * durur. Burada yalnızca hangisinin eksik olduğu seçilir — kabul kararı
 * sunucunun, bu yalnızca kullanıcıyı formu gönderip hata almaktan kurtarır.
 *
 * YALNIZCA EKSİKLER yazılır: karşılanan ölçüt sessizce kaybolur, hepsi
 * karşılanınca satır hiç görünmez.
 */

const DENETIMLER = {
    uzunluk: (deger, { uzunluk }) => deger.length >= uzunluk,
    harf: (deger) => /\p{L}/u.test(deger),
    rakam: (deger) => /\d/.test(deger),
    eslesme: (deger, { tekrar }) => deger !== '' && deger === tekrar,
};

function olcutler(satir) {
    try {
        return JSON.parse(satir.dataset.olcutler || '{}');
    } catch {
        return {};
    }
}

function yaz(satir) {
    const alan = document.getElementById(satir.dataset.alan);

    if (!alan) {
        return;
    }

    const tekrarAlani = satir.dataset.tekrar
        ? document.getElementById(satir.dataset.tekrar)
        : null;

    const baglam = {
        uzunluk: Number(satir.dataset.uzunluk) || 0,
        tekrar: tekrarAlani ? tekrarAlani.value : '',
    };

    /* Parola boşken uyarı yok: kullanıcı daha yazmadı. */
    const eksikler = alan.value === ''
        ? []
        : Object.entries(olcutler(satir))
            .filter(([anahtar]) => {
                const denetim = DENETIMLER[anahtar];

                /*
                 * Eşleşme, ikinci alana dokunulmadan önce eksik sayılmaz:
                 * parolayı yazan herkes bir an için "iki parola aynı değil"
                 * uyarısı görürdü.
                 */
                if (anahtar === 'eslesme' && baglam.tekrar === '') {
                    return false;
                }

                return denetim ? !denetim(alan.value, baglam) : false;
            })
            .map(([, metin]) => metin);

    satir.textContent = eksikler.join(' · ');
    satir.hidden = eksikler.length === 0;
}

export function init() {
    const satirlar = () => document.querySelectorAll('[data-parola-olcut]');

    document.addEventListener('input', (olay) => {
        if (!(olay.target instanceof HTMLInputElement)) {
            return;
        }

        satirlar().forEach((satir) => {
            if (olay.target.id === satir.dataset.alan || olay.target.id === satir.dataset.tekrar) {
                yaz(satir);
            }
        });
    });

    /* Tarayıcı parolayı kendisi doldurmuş olabilir. */
    satirlar().forEach(yaz);
}
