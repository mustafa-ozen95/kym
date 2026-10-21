import * as oturumKilidi from './ortak/oturum-kilidi.js';
import * as toast from './ortak/toast.js';
import * as confirmDialog from './panel/components/confirm.js';
import * as copy from './panel/components/copy.js';
import * as dialog from './panel/components/dialog.js';
import * as karekod from './panel/components/karekod.js';
import * as kullaniciMenu from './panel/components/kullanici-menu.js';
import * as navigation from './panel/components/navigation.js';
import * as renkNoktasi from './panel/components/renk-noktasi.js';
import * as siralama from './panel/components/siralama.js';
import * as theme from './panel/components/theme.js';

/**
 * Panel girişi — EYS ile aynı bileşenler.
 *
 * İKİ AYRI KURULUM var, çünkü sayfa geçişleri tarayıcıyı yenilemiyor
 * (bkz. panel/components/navigation.js):
 *
 *  - BİR KEZ: olayları document üzerinde dinleyen bileşenler. İkinci kez
 *    kurulsalardı her tıklama iki kez işlenirdi.
 *  - HER ÇİZİMDE: düğümün kendisine bakan işler (yeni gelen içerikteki
 *    bildirim, karekod tuvali).
 *
 * navigation EN SONA kurulur: bağlantıyı önce pencere ve onay bileşenleri
 * görmeli, geçiş yalnızca onlar karışmadıysa devralınmalı.
 */
theme.init();

function herCizimde() {
    toast.init();
    karekod.init();

    /* Renk noktaları: yeni gelen içerikte de boyanmalı. */
    renkNoktasi.init();

    /*
     * Sunucudan açık gelen pencere HER ÇİZİMDE gösterilir.
     *
     * `dialog.init` yalnızca bir kez çalışıyor (olayları document üzerinde
     * dinliyor); açık gelen pencereyi de orada göstermek, sayfa geçişinden
     * sonra gelen `?pencere=duzenle` adresinin hiçbir şey yapmaması demekti.
     */
    dialog.acikGeleniAc();
}

document.addEventListener('DOMContentLoaded', () => {
    /* Ekran kilidi: hareketsiz sekme oturumu kapatır (bkz. oturum-kilidi.js). Bir kez. */
    oturumKilidi.init();
    dialog.init();
    confirmDialog.init();
    copy.init();
    kullaniciMenu.init();
    siralama.init();
    navigation.init();

    herCizimde();
});

document.addEventListener('kimlik:sayfa', herCizimde);
