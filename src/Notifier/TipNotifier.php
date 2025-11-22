<?php

namespace App\Notifier;

use App\Entity\Utilisateur;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class TipNotifier
{
    private const TOPIC_TIPS = 'https://casino.gallotta.fr/mercure/tips';

    public function __construct(
        private HubInterface $hub
    ) {}

    public function notifyTip(Utilisateur $from, Utilisateur $to, float $amount): void
    {
        $payload = [
            'type' => 'tip.received',
            'tip'  => [
                'from' => [
                    'id'     => $from->getId(),
                    'pseudo' => $from->getPseudo() ?? $from->getEmail(),
                    'avatar' => $from->getAvatarUrl(),
                ],
                'to'   => [
                    'id'     => $to->getId(),
                    'pseudo' => $to->getPseudo() ?? $to->getEmail(),
                    'avatar' => $to->getAvatarUrl(),
                ],
                'amount'    => $amount,
                'createdAt' => (new \DateTimeImmutable())->format('Y-m-d H:i'),
            ],
        ];

        $update = new Update(
            self::TOPIC_TIPS,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        $this->hub->publish($update);
    }
}
