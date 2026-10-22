import { on } from '../lib/dom.js';

/**
 * Üst menüdeki hesap menüsü.
 *
 * Menü DIŞARI TIKLAYINCA ve Escape ile kapanır: açık kalan bir menü, altında
 * kalan içeriği tıklanamaz hâle getiriyor ve kullanıcı neden tepki almadığını
 * anlamıyordu.
 */
function liste() {
    return document.querySelector('[data-menu-list]');
}

function dugme() {
    return document.querySelector('[data-menu-open]');
}

function kapat() {
    const kutu = liste();

    if (kutu && !kutu.hidden) {
        kutu.hidden = true;
        dugme()?.setAttribute('aria-expanded', 'false');
    }
}

export function init() {
    on('click', '[data-menu-open]', (olay, tetik) => {
        olay.preventDefault();

        const kutu = liste();

        if (!kutu) {
            return;
        }

        kutu.hidden = !kutu.hidden;
        tetik.setAttribute('aria-expanded', kutu.hidden ? 'false' : 'true');
    });

    document.addEventListener('click', (olay) => {
        if (!olay.target.closest('[data-menu]')) {
            kapat();
        }
    });

    document.addEventListener('keydown', (olay) => {
        if (olay.key === 'Escape') {
            kapat();
        }
    });
}
