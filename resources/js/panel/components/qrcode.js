import QRCode from 'qrcode';

/**
 * TOTP karekodunu çizer.
 *
 * Karekod TARAYICIDA üretilir: gizli anahtarın bir görüntü servisine ya da
 * başka bir sunucuya uğraması gerekmiyor — zaten uğramamalı, çünkü o anahtarı
 * ele geçiren herkes geçerli kod üretebilir.
 */
export function init() {
    const tuval = document.getElementById('totp-qr');

    if (!tuval?.dataset.uri) {
        return;
    }

    QRCode.toCanvas(tuval, tuval.dataset.uri, {
        width: 200,
        margin: 1,
        /* Koyu/açık tema fark etmesin diye karekod kendi zeminini taşır. */
        color: { dark: '#141618', light: '#ffffff' },
    }).catch(() => {
        /* Çizilemezse elle girilecek anahtar zaten ekranda duruyor. */
    });
}
