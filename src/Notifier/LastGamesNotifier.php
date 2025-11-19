<?php
// src/Notifier/LastGamesNotifier.php

namespace App\Notifier;

use App\Entity\Partie;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class LastGamesNotifier
{
    /**
     * Topic Mercure sur lequel on publie les dernières parties.
     */
    private const TOPIC_LAST_GAMES = 'https://casino.gallotta.fr/mercure/last-games';

    public function __construct(
        private HubInterface $hub,
    ) {
    }

    public function notifyPartie(Partie $partie): void
    {
        $utilisateur = $partie->getUtilisateur();

        $payload = [
            'type'   => 'partie.created',
            'partie' => [
                'id'           => $partie->getId(),
                'user_id'      => $utilisateur?->getId(),
                'game_key'     => $partie->getGameKey(),
                'mise'         => $partie->getMise(),
                'gain'         => $partie->getGain(),
                'resultat_net' => $partie->getResultatNet(),
                'issue'        => $partie->getIssue()->value,
                'debut_le'     => $partie->getDebutLe()->format(\DateTimeInterface::ATOM),
                'fin_le'       => $partie->getFinLe()?->format(\DateTimeInterface::ATOM),
            ],
        ];

        $update = new Update(
            self::TOPIC_LAST_GAMES,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        $this->hub->publish($update);
    }
}
