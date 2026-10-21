/**
 * Hesap menüsü.
 *
 * Dışarı tıklayınca ve Escape ile kapanır: açık kalan bir menü altındaki
 * içeriği tıklanamaz hâle getirir ve kullanıcı neden tepki almadığını
 * anlamaz.
 */
function liste() {
    return document.querySelector('[data-menu-liste]');
}

function kapat() {
    const kutu = liste();

    if (kutu && !kutu.hidden) {
        kutu.hidden = true;
        document.querySelector('[data-menu-ac]')?.setAttribute('aria-expanded', 'false');
    }
}

export function init() {
    document.addEventListener('click', (olay) => {
        const tetik = olay.target.closest('[data-menu-ac]');

        if (tetik) {
            olay.preventDefault();

            const kutu = liste();

            if (kutu) {
                kutu.hidden = !kutu.hidden;
                tetik.setAttribute('aria-expanded', kutu.hidden ? 'false' : 'true');
            }

            return;
        }

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
