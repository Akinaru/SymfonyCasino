<?php

namespace App\Notifier;

use App\Entity\Partie;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class SlotLastGameNotifier
{
    private const TOPIC_LAST_GAMES = 'https://casino.gallotta.fr/mercure/last-games';

    public function __construct(
        private HubInterface $hub,
    ) {
    }

    public function notifyPartie(Partie $partie): void
    {
        $utilisateur = $partie->getUtilisateur();

        $grid = null;
        if ($partie->getMetaJson()) {
            $meta = json_decode($partie->getMetaJson(), true);
            if (is_array($meta) && isset($meta['grid']) && is_array($meta['grid'])) {
                $grid = $meta['grid'];
            }
        }

        $username = $utilisateur?->getPseudo() ?? ($utilisateur ? 'J'.$utilisateur->getId() : 'Joueur ?');
        $avatarUrl = $utilisateur ? $utilisateur->getAvatarUrl() : 'https://mc-heads.net/avatar';

        $payload = [
            'type'   => 'partie.created',
            'partie' => [
                'id'           => $partie->getId(),
                'user_id'      => $utilisateur?->getId(),
                'username'     => $username,
                'avatar_url'   => $avatarUrl,
                'game_key'     => $partie->getGameKey(),
                'mise'         => $partie->getMise(),
                'gain'         => $partie->getGain(),
                'resultat_net' => $partie->getResultatNet(),
                'issue'        => $partie->getIssue()->value,
                'debut_le'     => $partie->getDebutLe()->format(\DateTimeInterface::ATOM),
                'fin_le'       => $partie->getFinLe()?->format(\DateTimeInterface::ATOM),
                'grid'         => $grid,
                'isWin'        => $partie->getResultatNet() > 0,
            ],
        ];

        $update = new Update(
            self::TOPIC_LAST_GAMES,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        $this->hub->publish($update);
    }
}
