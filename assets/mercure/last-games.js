// assets/mercure/last-games.js
import { mercureBus } from './mercure-bus.js';

// Même topic que dans LastGamesNotifier côté PHP
const TOPIC_LAST_GAMES = 'https://casino.gallotta.fr/mercure/last-games';

function setupLastGames() {
    const panel = document.getElementById('last-games-panel');
    const list = document.getElementById('last-games-list');

    // Si la page n'a pas le panneau, on ne fait rien
    if (!panel || !list) {
        return;
    }

    // On déclare le topic à écouter
    mercureBus.addTopic(TOPIC_LAST_GAMES);

    // On enregistre un handler pour le type "partie.created"
    mercureBus.on('partie.created', (data) => {
        const p = data.partie;
        if (!p) return;

        const issueLabel = p.issue === 'gagne' ? '✅ Gagné' : '❌ Perdu';

        const li = document.createElement('li');
        li.textContent = `#${p.id} - Joueur ${p.user_id} - Jeu ${p.game_key} - Mise ${p.mise} - Gain ${p.gain} (${issueLabel})`;

        list.prepend(li);

        // Optionnel : limiter à 10 lignes
        while (list.children.length > 10) {
            list.removeChild(list.lastChild);
        }
    });

    // On ouvre la connexion (si pas déjà ouverte)
    mercureBus.connect();
}

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    setupLastGames();
});
