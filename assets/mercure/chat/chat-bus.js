import { mercureBus } from "../mercure-bus.js";

const TOPIC_CHAT = "https://casino.gallotta.fr/mercure/chat";

export function setupChatMercure() {
    const box = document.getElementById('chat-messages');
    if (!box) return;

    mercureBus.addTopic(TOPIC_CHAT);

    mercureBus.on('chat.message', (data) => {
        const msg = data.message;
        if (!msg) return;

        if (typeof window.renderChatMessage === 'function') {
            window.renderChatMessage(msg);
        }
    });

    mercureBus.on('chat.clear', () => {
        const box = document.getElementById('chat-messages');
        if (!box) return;
        box.innerHTML = '';
        box.scrollTop = 0;
    });

    mercureBus.connect();
}

window.renderChatMessage = function(msg) {
    const box = document.getElementById('chat-messages');
    if (!box) return;

    const currentUserId = window.currentUserId ?? 0;
    const isSystem = !!msg.isSystem;

    let html = '';

    if (isSystem) {
        const safeContent = String(msg.content ?? '').replace(/</g, "&lt;");
        html = `
            <div class="text-center text-muted small my-2">
                ${safeContent}
                ${msg.createdAt ? `<span class="ms-1 text-secondary">· ${msg.createdAt}</span>` : ''}
            </div>
        `;
    } else {
        const user = msg.user || {};
        const isMe = user.id === currentUserId;

        const safeContent = String(msg.content ?? '').replace(/</g, "&lt;");

        html = `
            <div class="d-flex mb-3 ${isMe ? 'justify-content-end' : ''}">
                ${isMe ? '' : `
                    <img src="${user.avatar || ''}" width="38" height="38" class="me-2 border rounded">
                `}
                <div class="${isMe ? 'text-end' : ''}">
                    <div class="small text-muted">${user.pseudo || 'Inconnu'} · ${msg.createdAt || ''}</div>
                    <div class="p-2 rounded ${isMe ? 'bg-light border' : 'bg-primary text-white'}">
                        ${safeContent}
                    </div>
                </div>
                ${isMe ? `
                    <img src="${user.avatar || ''}" width="38" height="38" class="ms-2 border rounded">
                ` : ''}
            </div>
        `;
    }

    box.insertAdjacentHTML('beforeend', html);
    box.scrollTop = box.scrollHeight;
};
