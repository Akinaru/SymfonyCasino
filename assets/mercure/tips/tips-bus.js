import { mercureBus } from "../mercure-bus.js";

const TOPIC_TIPS = "https://casino.gallotta.fr/mercure/tips";

function refreshHeaderBalanceLive() {
    const containers = document.querySelectorAll('[id="header-balance-live"]');
    if (!containers.length) return;

    containers.forEach((container) => {
        const btn = container.querySelector('[data-live-action-param="refresh"]');
        if (!btn) return;

        btn.click();
    });
}

export function setupTipsMercure() {
    const alertContainer = document.getElementById('tip-alert-container');
    if (!alertContainer) {
        return;
    }

    mercureBus.addTopic(TOPIC_TIPS);

    mercureBus.on('tip.received', (data) => {
        const tip = data.tip;
        if (!tip) return;

        const currentUserId = window.currentUserId ?? 0;

        const from   = tip.from || {};
        const to     = tip.to || {};
        const amountNum = typeof tip.amount === 'number'
            ? tip.amount
            : parseFloat(tip.amount);
        const amount = isNaN(amountNum) ? null : amountNum;

        const isReceiver = to.id === currentUserId;
        const isSender   = from.id === currentUserId;

        if (!isReceiver && !isSender) {
            return;
        }

        refreshHeaderBalanceLive();

        // Alerte pour le RECEVEUR
        if (isReceiver) {
            const prettyAmount = amount !== null
                ? (amount.toFixed ? amount.toFixed(2) : amount)
                : tip.amount;

            const html = `
                <div class="alert alert-success alert-dismissible fade show mb-2" role="alert">
                    <div class="d-flex align-items-center">
                        ${from.avatar ? `<img src="${from.avatar}" alt="" width="32" height="32" class="rounded-circle me-2 border">` : ''}
                        <div>
                            <strong>${from.pseudo || 'Un joueur'}</strong>
                            t'a envoyé un tip de <strong>${prettyAmount} €</strong> 🎁
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            `;
            alertContainer.insertAdjacentHTML('beforeend', html);
        }

        if (isSender) {
            const prettyAmount = amount !== null
                ? (amount.toFixed ? amount.toFixed(2) : amount)
                : tip.amount;

            const html = `
                <div class="alert alert-info alert-dismissible fade show mb-2" role="alert">
                    <div class="d-flex align-items-center">
                        ${to.avatar ? `<img src="${to.avatar}" alt="" width="32" height="32" class="rounded-circle me-2 border">` : ''}
                        <div>
                            Tu as envoye un tip de <strong class="balance balance-inline">${prettyAmount}</strong>
                            a <strong>${to.pseudo || 'un joueur'}</strong> !
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            `;
            alertContainer.insertAdjacentHTML('beforeend', html);
        }
    });

    mercureBus.connect();
}
