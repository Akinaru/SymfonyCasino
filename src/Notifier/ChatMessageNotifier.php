<?php

namespace App\Notifier;

use App\Entity\Message;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class ChatMessageNotifier
{
    private const TOPIC_CHAT = 'https://casino.gallotta.fr/mercure/chat';

    public function __construct(
        private HubInterface $hub
    ) {}

    public function notify(Message $message): void
    {
        $user = $message->getUser();

        $payload = [
            'type'    => 'chat.message',
            'message' => [
                'id'        => $message->getId(),
                'content'   => $message->getContent(),
                'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i'),
                'isSystem'  => $message->isSystem(),
                'user'      => $user ? [
                    'id'     => $user->getId(),
                    'pseudo' => $user->getPseudo() ?? $user->getEmail(),
                    'avatar' => $user->getAvatarUrl(),
                ] : null,
            ],
        ];

        $update = new Update(
            self::TOPIC_CHAT,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        $this->hub->publish($update);
    }

    public function notifyClear(): void
    {
        $payload = [
            'type' => 'chat.clear',
        ];

        $update = new Update(
            self::TOPIC_CHAT,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        $this->hub->publish($update);
    }
}
