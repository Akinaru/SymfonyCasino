<?php

namespace App\Game;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PlinkoGame implements GameInterface
{
    public function __construct(private UrlGeneratorInterface $router)
    {
    }

    public function getKey(): string
    {
        return 'plinko';
    }

    public function getName(): string
    {
        return '📎 Plinko';
    }

    public function getUrl(): string
    {
        return $this->router->generate('app_game_plinko_index');
    }

    public function getDescription(): ?string
    {
        return "Plinko : lâche une bille sur un plateau de 16 rangées de clous. Elle rebondit à gauche ou à droite avant de finir dans une case multiplicatrice. Petits multiplicateurs fréquents au centre, gros multiplicateurs rares sur les bords.";
    }

    public static function getDescriptionInGame(): ?string
    {
        return "Choisis ta mise puis lâche une bille sur un plateau fixe de 16 rangées. Chaque rangée fait partir la bille à gauche ou à droite (modèle Galton / binomial), ce qui donne une forte concentration de résultats au centre, et des résultats extrêmes rares sur les bords.

Les multipliers utilisés sont inspirés des Plinko en ligne classiques (RTP typique annoncé entre ~95 % et ~99 %, selon les casinos et les niveaux de risque). Ici on est sur un plateau \"risque moyen\" : centre plutôt safe mais parfois < 1x, bords plus volatils avec de gros multiplicateurs.

Joue responsable ✦ fixe-toi un budget et des pauses.";
    }

    public function getImageUrl(): ?string
    {
        // À adapter si tu crées une cover dédiée
        return '/games/plinko.png';
    }

    public function getMinBet(): ?int
    {
        return 1;
    }

    public function getMaxBet(): ?int
    {
        return 1_000_000;
    }
}
