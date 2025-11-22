import { mercureBus } from "../mercure-bus.js";

const TOPIC_CHAT = "https://casino.gallotta.fr/mercure/chat";

export function setupChatMercure() {
    const box = document.getElementById('chat-messages');
    if (!box) return;

    mercureBus.addTopic(TOPIC_CHAT);

    mercureBus.on('chat.message', (data) => {
        const msg = data.message;
        if (!msg) return;

        renderChatMessage(msg);
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
    const isMe = msg.user.id === currentUserId;

    const html = `
        <div class="d-flex mb-3 ${isMe ? 'justify-content-end' : ''}">
            ${isMe ? '' : `
                <img src="${msg.user.avatar}" width="38" height="38" class="me-2 border rounded">
            `}
            <div class="${isMe ? 'text-end' : ''}">
                <div class="small text-muted">${msg.user.pseudo} · ${msg.createdAt}</div>
                <div class="p-2 rounded ${isMe ? 'bg-light border' : 'bg-primary text-white'}">
                    ${msg.content.replace(/</g, "&lt;")}
                </div>
            </div>
            ${isMe ? `
                <img src="${msg.user.avatar}" width="38" height="38" class="ms-2 border rounded">
            ` : ''}
        </div>
    `;

    box.insertAdjacentHTML('beforeend', html);
    box.scrollTop = box.scrollHeight;
};
