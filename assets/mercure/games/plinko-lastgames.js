// assets/mercure/games/plinko-lastgames.js
import { mercureBus } from '../mercure-bus.js';

const TOPIC_LAST_GAMES = 'https://casino.gallotta.fr/mercure/last-games';

function formatDateTime(value) {
    if (!value) {
        return '';
    }

    let date;
    if (value instanceof Date) {
        date = value;
    } else if (typeof value === 'string') {
        const parsed = new Date(value);
        if (Number.isNaN(parsed.getTime())) {
            return '';
        }
        date = parsed;
    } else {
        return '';
    }

    const pad = (n) => (n < 10 ? '0' + n : '' + n);

    const day = pad(date.getDate());
    const month = pad(date.getMonth() + 1);
    const year = date.getFullYear();
    const hours = pad(date.getHours());
    const minutes = pad(date.getMinutes());

    return `${day}/${month}/${year} ${hours}:${minutes}`;
}

function createRow(p) {
    const tr = document.createElement('tr');

    const netRaw =
        typeof p.resultat_net === 'number'
            ? p.resultat_net
            : typeof p.resultatNet === 'number'
                ? p.resultatNet
                : 0;
    const net = Number.isFinite(netRaw) ? netRaw : 0;

    const miseRaw =
        typeof p.mise === 'number'
            ? p.mise
            : typeof p.bet === 'number'
                ? p.bet
                : 0;
    const mise = Number.isFinite(miseRaw) ? miseRaw : 0;

    const username = p.username || (p.user_id ? `J${p.user_id}` : 'Joueur ?');
    const avatarUrl = p.avatar_url || p.avatarUrl || 'https://mc-heads.net/avatar';

    const isWin = typeof p.isWin === 'boolean' ? p.isWin : net > 0;

    const rows = p.rows ?? null;
    const position = p.position ?? null;
    const multiplier = typeof p.multiplier === 'number' ? p.multiplier : null;

    const dateValue =
        p.debut_le ||
        p.debutLe ||
        p.started_at ||
        p.startedAt ||
        null;
    const dateStr = formatDateTime(dateValue);

    const netHtml =
        net > 0
            ? `<span class="text-success balance justify-content-end">+${net}</span>`
            : `<span class="balance justify-content-end">0</span>`;

    const slotInfo = (rows !== null && position !== null)
        ? `Case ${position + 1}/${rows + 1}`
        : '—';

    const multInfo = multiplier !== null
        ? `${multiplier.toFixed(2)}x`
        : '—';

    tr.innerHTML = `
        <td>
          <img src="${avatarUrl}" alt="Avatar" class="rounded"
               style="width:40px;height:40px;image-rendering:pixelated;">
        </td>
        <td class="small">
          ${username}
        </td>
        <td class="small">
          <div class="d-flex flex-column">
            <span>${slotInfo}</span>
            <small class="text-white-50">${multInfo}</small>
          </div>
        </td>
        <td class="small">
          ${dateStr}
        </td>
        <td class="small text-start">
          <span class="balance">${mise}</span>
        </td>
        <td class="small text-end">
          ${netHtml}
        </td>
    `;

    if (isWin) {
        tr.classList.add('table-success-subtle');
    }

    return tr;
}

function setupPlinkoLastGames() {
    const panel = document.getElementById('plinko-last-games-panel');
    const list = document.getElementById('plinko-last-games-list');

    if (!panel || !list) return;
    if (panel.dataset.wired === '1') return;
    panel.dataset.wired = '1';

    Array.from(list.children).forEach((child) => {
        if (child.tagName !== 'TR') {
            child.remove();
        }
    });

    mercureBus.addTopic(TOPIC_LAST_GAMES);

    mercureBus.on('partie.created', (data) => {
        const p = data.partie;
        if (!p) return;
        if (p.game_key && p.game_key !== 'plinko') return;

        const tr = createRow(p);
        list.prepend(tr);

        while (list.children.length > 10) {
            list.removeChild(list.lastElementChild);
        }
    });

    mercureBus.connect();
}

document.addEventListener('DOMContentLoaded', setupPlinkoLastGames);
document.addEventListener('turbo:load', setupPlinkoLastGames);
