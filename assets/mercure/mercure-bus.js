class MercureBus {
    constructor(hubPath = '/.well-known/mercure') {
        this.hubPath = hubPath;
        this.topics = new Set();
        this.handlersByType = new Map();
        this.eventSource = null;
    }

    addTopic(topic) {
        const sizeBefore = this.topics.size;
        this.topics.add(topic);

        if (this.eventSource && this.topics.size !== sizeBefore) {
            this.connect();
        }
    }

    on(type, handler) {
        if (!this.handlersByType.has(type)) {
            this.handlersByType.set(type, new Set());
        }
        this.handlersByType.get(type).add(handler);
    }

    connect() {
        const origin = window.location.origin;
        const url = new URL(this.hubPath, origin);

        for (const topic of this.topics) {
            url.searchParams.append('topic', topic);
        }

        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }

        const es = new EventSource(url.toString());

        es.onopen = () => {
            console.log('[MercureBus] connexion ouverte', {
                url: url.toString(),
                topics: Array.from(this.topics),
            });
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
        };

        this.eventSource = es;
    }
}

export const mercureBus = new MercureBus();
