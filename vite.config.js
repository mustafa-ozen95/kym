import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

/*
 * Tailwind KULLANILMIYOR.
 *
 * Sayfanın kendi biçemi var (kabin çizimleri, şerit animasyonları) ve EYS ile
 * network aynı düzeni kullanıyor: app.css yalnızca içe aktarım barındırır,
 * kurallar kendi dosyalarında durur. Tailwind burada ölü ağırlık olurdu.
 */
export default defineConfig({
    plugins: [
        laravel({
            /*
             * İKİ AYRI GİRİŞ NOKTASI: Hızlı Erişim sayfasının kendi biçemi
             * var, panel ise EYS'nin tasarım sistemini kullanıyor. Aynı
             * sayfada yüklenselerdi `:root` değişkenleri birbirini ezerdi.
             */
            input: [
                'resources/css/app.css', 'resources/js/app.js',
                'resources/css/panel.css', 'resources/js/panel.js',
            ],
            refresh: true,
        }),
    ],
});
