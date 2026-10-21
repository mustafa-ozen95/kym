import './components/quick-access.js';
import * as oturumKilidi from './shared/session-lock.js';
import * as toast from './shared/toast.js';
import * as menu from './components/menu.js';
import * as parola from './components/password.js';
import * as pencere from './components/window.js';

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

/* Ekran kilidi: hareketsiz sekme oturumu kapatır (bkz. session-lock.js). */
oturumKilidi.init();
