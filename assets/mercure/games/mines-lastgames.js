// assets/js/game/mines-lastgames.js
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

    const minesRaw =
        typeof p.mines === 'number'
            ? p.mines
            : typeof p.mines === 'string'
                ? parseInt(p.mines, 10)
                : null;
    const mines = Number.isFinite(minesRaw) ? minesRaw : null;

    const revealedRaw =
        typeof p.revealedCount === 'number'
            ? p.revealedCount
            : typeof p.revealed_count === 'number'
                ? p.revealed_count
                : 0;
    const revealedCount = Number.isFinite(revealedRaw) ? revealedRaw : 0;

    const username = p.username || (p.user_id ? `J${p.user_id}` : 'Joueur ?');
    const avatarUrl = p.avatar_url || p.avatarUrl || 'https://mc-heads.net/avatar';

    const isWin = typeof p.isWin === 'boolean' ? p.isWin : net > 0;

    const dateValue =
        p.debut_le ||
        p.debutLe ||
        p.started_at ||
        p.startedAt ||
        null;
    const dateStr = formatDateTime(dateValue);

    let netHtml;
    if (net > 0) {
        netHtml = `<span class="text-success balance">+${net}</span>`;
    } else if (net < 0) {
        netHtml = `<span class="text-danger balance">${net}</span>`;
    } else {
        netHtml = `<span class="balance">0</span>`;
    }

    tr.innerHTML = `
    <td>
      <img src="${avatarUrl}" alt="Avatar" class="rounded"
           style="width:40px;height:40px;image-rendering:pixelated;">
    </td>
    <td class="small">
      ${username}
    </td>
    <td class="small">
      ${mines !== null ? mines : '-'}
    </td>
    <td class="small">
      ${revealedCount}
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

    return tr;
}

function setupMinesLastGames() {
    const panel = document.getElementById('mines-last-games-panel');
    const list = document.getElementById('mines-last-games-list');

    if (!panel || !list) return;
    if (panel.dataset.wired === '1') return;
    panel.dataset.wired = '1';

    // Nettoyage éventuel d'éléments non <tr>
    Array.from(list.children).forEach((child) => {
        if (child.tagName !== 'TR') {
            child.remove();
        }
    });

    mercureBus.addTopic(TOPIC_LAST_GAMES);

    mercureBus.on('partie.created', (data) => {
        const p = data.partie;
        if (!p) return;
        if (p.game_key && p.game_key !== 'mines') return;

        const tr = createRow(p);
        list.prepend(tr);

        while (list.children.length > 10) {
            list.removeChild(list.lastElementChild);
        }
    });

    mercureBus.connect();
}

document.addEventListener('DOMContentLoaded', setupMinesLastGames);
document.addEventListener('turbo:load', setupMinesLastGames);
