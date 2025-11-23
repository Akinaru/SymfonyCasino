import { mercureBus } from '../mercure-bus.js';

const TOPIC_LAST_GAMES = 'https://casino.gallotta.fr/mercure/last-games';

function formatDateTime(value) {
    if (!value) return '';

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
    const d = pad(date.getDate());
    const m = pad(date.getMonth() + 1);
    const y = date.getFullYear();
    const h = pad(date.getHours());
    const i = pad(date.getMinutes());

    return `${d}/${m}/${y} ${h}:${i}`;
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

    const height = typeof p.height === 'number' ? p.height : 0;
    const cashedOut = !!p.cashed_out;

    const dateValue =
        p.debut_le ||
        p.debutLe ||
        p.started_at ||
        p.startedAt ||
        null;
    const dateStr = formatDateTime(dateValue);

    let netHtml;
    if (net > 0) {
        netHtml = `<span class="text-success balance justify-content-end">+${net}</span>`;
    } else if (net < 0) {
        netHtml = `<span class="text-danger balance justify-content-end">${net}</span>`;
    } else {
        netHtml = `<span class="balance justify-content-end">0</span>`;
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
      ${height > 0 ? `${height} / 9` : '—'}
      ${cashedOut ? '<span class="badge bg-success ms-1">Cashout</span>' : ''}
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

function setupTowerLastGames() {
    const panel = document.getElementById('tower-last-games-panel');
    const list  = document.getElementById('tower-last-games-list');

    if (!panel || !list) return;
    if (panel.dataset.wired === '1') return;
    panel.dataset.wired = '1';

    mercureBus.addTopic(TOPIC_LAST_GAMES);

    mercureBus.on('partie.created', (data) => {
        const p = data.partie;
        if (!p) return;
        if (p.game_key && p.game_key !== 'tower') return;

        const tr = createRow(p);
        list.prepend(tr);

        while (list.children.length > 10) {
            list.removeChild(list.lastElementChild);
        }
    });

    mercureBus.connect();
}

document.addEventListener('DOMContentLoaded', setupTowerLastGames);
document.addEventListener('turbo:load', setupTowerLastGames);
