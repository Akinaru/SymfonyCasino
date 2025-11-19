// assets/mercure/games/slots-lastgames.js
import { mercureBus } from '../mercure-bus.js';

const TOPIC_LAST_GAMES = 'https://casino.gallotta.fr/mercure/last-games';

// Sera rempli avec les bonnes URLs (fingerprintées) au setup
let SLOT_IMAGES = {};

function buildGridHtml(grid) {
    if (!Array.isArray(grid)) {
        return '<span class="text-white-50 small">–</span>';
    }

    let html = '<div class="slot-mini-grid d-inline-grid" style="display:inline-grid;grid-template-columns:repeat(3,20px);gap:2px;">';
    for (let r = 0; r < 3; r++) {
        const row = grid[r] || [];
        for (let c = 0; c < 3; c++) {
            const sym = row[c] || 'slot8';
            const src = SLOT_IMAGES[sym] || SLOT_IMAGES.slot8 || '/img/slots/slot8.png';
            html += `<img src="${src}" alt="" style="width:40px;height:40px;">`;
        }
    }
    html += '</div>';

    return html;
}

function createRow(p) {
    const tr = document.createElement('tr');

    const net = typeof p.resultat_net === 'number'
        ? p.resultat_net
        : (typeof p.resultNet === 'number' ? p.resultNet : 0);

    const netSign = net > 0 ? '+' : '';
    const netClass = net >= 0 ? 'text-success' : 'text-danger';

    const username = p.username || (p.user_id ? `J${p.user_id}` : 'Joueur ?');
    const avatarUrl = p.avatar_url || 'https://mc-heads.net/avatar';

    tr.innerHTML = `
    <td>
      <img src="${avatarUrl}" alt="Avatar" class="rounded"
           style="width:20px;height:20px;">
    </td>
    <td class="small">
      ${username}
    </td>
    <td>
      ${buildGridHtml(p.grid)}
    </td>
    <td class="small text-end">
      <span class="${netClass} balance">${netSign}${net}</span>
    </td>
  `;

    return tr;
}

function setupSlotsLastGames() {
    const panel = document.getElementById('last-games-panel');
    const list = document.getElementById('last-games-list');

    if (!panel || !list) {
        return;
    }

    // 🔹 Récupérer les URLs fingerprintées depuis les data-attributes
    SLOT_IMAGES = {
        slot1: panel.dataset.slot1 || '/img/slots/slot1.png',
        slot2: panel.dataset.slot2 || '/img/slots/slot2.png',
        slot3: panel.dataset.slot3 || '/img/slots/slot3.png',
        slot4: panel.dataset.slot4 || '/img/slots/slot4.png',
        slot5: panel.dataset.slot5 || '/img/slots/slot5.png',
        slot6: panel.dataset.slot6 || '/img/slots/slot6.png',
        slot7: panel.dataset.slot7 || '/img/slots/slot7.png',
        slot8: panel.dataset.slot8 || '/img/slots/slot8.png',
    };

    // 🔹 Nettoyage des vieux <li> éventuels
    Array.from(list.children).forEach((child) => {
        if (child.tagName !== 'TR') {
            child.remove();
        }
    });

    mercureBus.addTopic(TOPIC_LAST_GAMES);

    mercureBus.on('partie.created', (data) => {
        const p = data.partie;
        if (!p) return;
        if (p.game_key !== 'slots') return;

        const tr = createRow(p);

        list.prepend(tr);

        while (list.children.length > 10) {
            list.removeChild(list.lastElementChild);
        }
    });

    mercureBus.connect();
}

document.addEventListener('DOMContentLoaded', setupSlotsLastGames);
