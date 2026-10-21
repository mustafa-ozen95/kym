import './components/hizli-erisim.js';
import * as oturumKilidi from './ortak/oturum-kilidi.js';
import * as toast from './ortak/toast.js';
import * as menu from './components/menu.js';
import * as parola from './components/parola.js';
import * as pencere from './components/pencere.js';

/**
 * Uygulama girişi.
 *
 * Buradaki modüller yalnızca DOM, olay ve arayüz işleriyle ilgilenir; veri
 * sunucudan gelir, doğrulama ve yetki sunucuda yapılır.
 */
pencere.init();
toast.init();
menu.init();
parola.init();

/* Ekran kilidi: hareketsiz sekme oturumu kapatır (bkz. oturum-kilidi.js). */
oturumKilidi.init();
