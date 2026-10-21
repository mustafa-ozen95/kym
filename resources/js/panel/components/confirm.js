import { $, on } from '../lib/dom.js';

/**
 * Tehlikeli işlemler için onay penceresi.
 *
 * Kullanımı — herhangi bir form veya bağlantıya öznitelik eklemek yeterlidir:
 *   data-confirm="Bu kayıt silinecek."
 *   data-confirm-title="Kaydı sil"
 *   data-confirm-variant="danger"
 *   data-confirm-verify="kabin-1"   (kullanıcının yazarak doğrulaması istenir)
 *   data-confirm-reasonless      (gerekçe istenmez — yalnızca okuyan işlemler)
 *
 * Gerekçe varsayılan olarak ZORUNLUDUR ve forma "reason" alanı olarak
 * eklenir; sunucu tarafında da aranır, yani pencereyi atlamak işe yaramaz.
 */

let pending = null;

function dialog() {
    return $('[data-confirm-dialog]');
}

function open(trigger) {
    const box = dialog();

    if (!box) {
        return false;
    }

    pending = trigger;

    box.querySelector('[data-confirm-title]').textContent =
        trigger.dataset.confirmTitle || 'Onay gerekiyor';
    box.querySelector('[data-confirm-message]').textContent = trigger.dataset.confirm;

    const verify = trigger.dataset.confirmVerify ?? '';
    const verifyField = box.querySelector('[data-confirm-verify-field]');
    const input = box.querySelector('[data-confirm-input]');
    const accept = box.querySelector('[data-confirm-accept]');
    const reasonField = box.querySelector('[data-confirm-reason-field]');
    const reason = box.querySelector('[data-confirm-reason]');

    // Gerekçe yalnızca durumu değiştirmeyen işlemlerde istenmez.
    const reasonless = trigger.dataset.confirmReasonless !== undefined;

    verifyField.hidden = verify === '';
    reasonField.hidden = reasonless;
    box.querySelector('[data-confirm-expected]').textContent = verify;
    input.value = '';
    reason.value = '';

    accept.className = `btn btn--${trigger.dataset.confirmVariant === 'danger' ? 'danger' : 'primary'}`;

    const tazele = () => {
        const dogrulandi = verify === '' || input.value.trim() === verify;
        const gerekceli = reasonless || reason.value.trim().length >= 3;

        accept.disabled = !dogrulandi || !gerekceli;
    };

    input.oninput = tazele;
    reason.oninput = tazele;
    tazele();

    box.hidden = false;
    (reasonless ? (verify === '' ? accept : input) : reason).focus();

    return true;
}

function close() {
    const box = dialog();

    if (box) {
        box.hidden = true;
    }

    pending = null;
}

function proceed() {
    const trigger = pending;
    const gerekce = dialog()?.querySelector('[data-confirm-reason]')?.value?.trim() ?? '';

    close();

    if (!trigger) {
        return;
    }

    // İkinci turda onay istenmesin diye öznitelik kaldırılır.
    delete trigger.dataset.confirm;

    if (trigger.tagName === 'FORM') {
        // Gerekçe forma taşınır; sunucu onu arar.
        if (gerekce !== '') {
            const alan = document.createElement('input');

            alan.type = 'hidden';
            alan.name = 'reason';
            alan.value = gerekce;
            trigger.appendChild(alan);
        }

        /*
         * Gönderimden ÖNCE haber verilir; formu dinleyen bileşenler (ör.
         * sorting.js) son hazırlığı burada yapar. submit() "submit" olayını TETİKLEMEZ,
         * requestSubmit() ise toplu güncelleme formunu bozuyor: o formun
         * varsayılan düğmesi seçim boşken kapalı durur ve gönderim sessizce
         * düşer. Bu yüzden kendi olayımız.
         */
        trigger.dispatchEvent(new CustomEvent('eys:onaylandi', { bubbles: true }));

        trigger.submit();

        return;
    }

    trigger.click();
}

export function init() {
    on('submit', 'form[data-confirm]', (event, form) => {
        if (open(form)) {
            event.preventDefault();
        }
    });

    on('click', 'a[data-confirm], button[data-confirm]', (event, element) => {
        if (open(element)) {
            event.preventDefault();
        }
    });

    on('click', '[data-confirm-accept]', proceed);
    on('click', '[data-confirm-cancel]', close);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !dialog()?.hidden) {
            close();
        }
    });
}
