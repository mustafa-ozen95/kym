/**
 * Kutucuk renginin noktası.
 *
 * Renk eskiden Blade'de `style="--nokta: #rrggbb"` olarak basılıyordu.
 * İçerik CSP'si satır içi stil özniteliklerini engelliyor ve böyle bir kural
 * SESSİZCE düşer: nokta rengini kaybeder, kimse bir hata görmez. Buradan
 * yazmak CSP'ye takılmaz — CSSOM üzerinden yapılan değişiklik satır içi stil
 * sayılmaz.
 *
 * Değer yine sunucudan geliyor; yalnızca taşıma yolu değişti.
 */
export function init() {
    document.querySelectorAll('[data-nokta]').forEach((nokta) => {
        const renk = nokta.dataset.nokta;

        /* Sunucu doğruluyor ama biçimi burada da denetlenir: CSS'e ne
           yazdığımızı bilmek istiyoruz. */
        if (/^#[0-9a-fA-F]{6}$/.test(renk ?? '')) {
            nokta.style.setProperty('--nokta', renk);
        }
    });
}
