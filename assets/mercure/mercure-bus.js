// assets/mercure/mercure-bus.js

class MercureBus {
    constructor(hubPath = '/.well-known/mercure') {
        this.hubPath = hubPath;
        this.topics = new Set();
        this.handlersByType = new Map();
        this.eventSource = null;
        this.connected = false;
    }

    /**
     * Ajoute un topic à écouter.
     * Peut être appelé plusieurs fois avant ou après connect().
     */
    addTopic(topic) {
        this.topics.add(topic);
        // Si on est déjà connecté, on pourrait éventuellement reconnecter
        // mais pour commencer on fixe les topics au premier connect().
    }

    /**
     * Enregistre un handler pour un type d'événement (ex: 'partie.created').
     */
    on(type, handler) {
        if (!this.handlersByType.has(type)) {
            this.handlersByType.set(type, new Set());
        }
        this.handlersByType.get(type).add(handler);
    }

    /**
     * Ouvre la connexion EventSource si ce n'est pas déjà fait.
     */
    connect() {
        if (this.connected || this.eventSource) {
            return;
        }

        const origin = window.location.origin;
        const url = new URL(this.hubPath, origin);

        // Ajout de tous les topics en query params
        for (const topic of this.topics) {
            url.searchParams.append('topic', topic);
        }

        const es = new EventSource(url.toString());

        es.onopen = () => {
            this.connected = true;
            console.log('[MercureBus] connexion ouverte', { url: url.toString() });
        };

        es.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                const type = data.type || null;

                if (!type) {
                    console.warn('[MercureBus] message sans type', data);
                    return;
                }

                const handlers = this.handlersByType.get(type);
                if (!handlers || handlers.size === 0) {
                    // personne n'écoute ce type : normal dans certains cas
                    return;
                }

                for (const handler of handlers) {
                    try {
                        handler(data);
                    } catch (e) {
                        console.error('[MercureBus] erreur handler', e);
                    }
                }
            } catch (e) {
                console.error('[MercureBus] erreur parsing message', e, event.data);
            }
        };

        es.onerror = (event) => {
            console.error('[MercureBus] erreur EventSource', event);
            // Tu pourras plus tard ajouter une logique de reconnexion.
        };

        this.eventSource = es;
    }
}

// Instance globale qu'on peut réutiliser partout
export const mercureBus = new MercureBus();
