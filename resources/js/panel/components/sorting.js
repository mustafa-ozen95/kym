import { on } from '../lib/dom.js';

/**
 * Satırları sürükleyerek sıralar.
 *
 * Kutucuklar ve salonlar Hızlı Erişim sayfasında bu sırayla diziliyor; sayı
 * kutusuna elle 10, 20, 30 yazmak sırayı değiştirmenin dolambaçlı yoluydu.
 *
 * Bırakma anında yeni sıra sunucuya gönderilir: tarayıcıda görünen sıra ile
 * kayıttaki sıra ayrılamaz.
 *
 * Dış kütüphane kullanılmaz; tarayıcının kendi sürükleme olayları yeterli.
 */

/*
 * Listenin DOĞRUDAN çocukları: iç içe listelerde (grup → alt grup) dıştaki
 * liste, içtekinin satırlarını kendi satırı sanmamalı — yoksa bir grup bir
 * alt grup satırının önüne sokulur ve tablo bozulurdu.
 */
function satirlar(liste) {
    return [...liste.children].filter((satir) => satir.matches('[data-order-id]'));
}

function sirali(liste) {
    return satirlar(liste).map((satir) => satir.dataset.orderId);
}

/**
 * Sürüklenen satırın imlece göre nereye bırakılacağını bulur.
 */
function hedefSatir(liste, y) {
    return satirlar(liste).filter((satir) => !satir.classList.contains('is-dragging')).reduce(
        (yakin, satir) => {
            const kutu = satir.getBoundingClientRect();
            const fark = y - kutu.top - kutu.height / 2;

            return fark < 0 && fark > yakin.fark ? { fark, satir } : yakin;
        },
        { fark: Number.NEGATIVE_INFINITY, satir: null },
    ).satir;
}

/*
 * BIRAKINCA ONAY SORULUR (pencereyle). Form onay
 * özniteliğiyle kurulur ve gönderimi confirm.js yakalar; vazgeçilirse
 * satırlar eski yerine döner — ekranda görünen sıra ile kayıttaki sıra
 * ayrılamaz.
 */
let bekleyen = null;

function geriAl() {
    if (!bekleyen) {
        return;
    }

    bekleyen.eskiSira.forEach((satir) => bekleyen.liste.appendChild(satir));
    bekleyen.form.remove();
    bekleyen = null;
}

function gonder(liste, eskiSira) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = liste.dataset.order;
    form.hidden = true;
    form.setAttribute('data-confirm', 'Yeni dizilim kaydedilecek; sayfa bu sırayla çizilir.');
    form.setAttribute('data-confirm-title', 'Sırayı kaydet');
    form.setAttribute('data-confirm-reasonless', '');

    // Sunucuya yazma isteği tek yerden geçsin diye normal form gönderimi
    // kullanılır: hata gösterimi ve yönlendirme aynı kalır.
    const alanlar = [
        ['_token', document.querySelector('meta[name="csrf-token"]')?.content ?? ''],
        ['_method', 'PUT'],
        /* Alan adı listeden gelir: aynı bileşen salonları da kutucukları da sıralıyor. */
        ...sirali(liste).map((kimlik) => [`${liste.dataset.orderField ?? 'order'}[]`, kimlik]),
    ];

    alanlar.forEach(([ad, deger]) => {
        const girdi = document.createElement('input');
        girdi.type = 'hidden';
        girdi.name = ad;
        girdi.value = deger;
        form.appendChild(girdi);
    });

    document.body.appendChild(form);
    bekleyen = { liste, eskiSira, form };

    /* Onaylanınca confirm.js formu gönderir; bekleyen kayıt temizlenir. */
    form.addEventListener('eys:onaylandi', () => {
        bekleyen = null;
    });

    /* requestSubmit "submit" olayını üretir; onay penceresi onu yakalar. */
    form.requestSubmit();
}

export function init() {
    let suruklenen = null;

    let eskiSira = [];

    on('dragstart', '[data-order-id]', (event, satir) => {
        suruklenen = satir;
        eskiSira = satirlar(satir.parentElement);
        satir.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
    });

    on('dragend', '[data-order-id]', (event, satir) => {
        satir.classList.remove('is-dragging');
    });

    on('dragover', '[data-order]', (event, liste) => {
        if (!suruklenen || !liste.contains(suruklenen)) {
            return;
        }

        // Varsayılan davranış bırakmayı engeller.
        event.preventDefault();

        const hedef = hedefSatir(liste, event.clientY);

        if (hedef) {
            liste.insertBefore(suruklenen, hedef);
        } else {
            liste.appendChild(suruklenen);
        }
    });

    on('drop', '[data-order]', (event, liste) => {
        if (!suruklenen || !liste.contains(suruklenen)) {
            return;
        }

        event.preventDefault();
        suruklenen = null;

        gonder(liste, eskiSira);
    });

    /* Vazgeçildi: pencere kapanır, satırlar eski yerine döner. */
    on('click', '[data-confirm-cancel]', geriAl);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            geriAl();
        }
    });
}
