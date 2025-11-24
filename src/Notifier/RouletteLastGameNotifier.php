<?php

namespace App\Notifier;

use App\Entity\Partie;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class RouletteLastGameNotifier
{
    private const TOPIC_LAST_GAMES = 'https://casino.gallotta.fr/mercure/last-games';

    public function __construct(
        private HubInterface $hub,
    ) {
    }

    public function notifyPartie(
        Partie $partie,
        int $number,
        string $betColor,
        string $resultColor,
        int $multiplier
    ): void {
        $user = $partie->getUtilisateur();

        $payload = [
            'type' => 'partie.created',
            'partie' => [
                'id'           => $partie->getId(),
                'user_id'      => $user?->getId(),
                'game_key'     => $partie->getGameKey(),
                'mise'         => $partie->getMise(),
                'gain'         => $partie->getGain(),
                'resultat_net' => $partie->getResultatNet(),
                'issue'        => $partie->getIssue()->value,
                'debut_le'     => $partie->getDebutLe()->format(DATE_ATOM),
                'fin_le'       => $partie->getFinLe()?->format(DATE_ATOM),

                'username'     => $user?->getPseudo() ?: ($user?->getEmail() ?? 'Inconnu'),
                'avatar_url'   => $user?->getAvatarUrl() ?? 'https://mc-heads.net/avatar',

                'number'       => $number,
                'bet_color'    => $betColor,
                'result_color' => $resultColor,
                'multiplier'   => $multiplier,
                'isWin'        => $partie->getResultatNet() > 0,
            ],
        ];

        $update = new Update(
            self::TOPIC_LAST_GAMES,
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        );

        $this->hub->publish($update);
    }
}
