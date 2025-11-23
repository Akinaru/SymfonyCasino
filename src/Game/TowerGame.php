<?php

namespace App\Game;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TowerGame implements GameInterface
{
    public function __construct(private UrlGeneratorInterface $router) {}

    public function getKey(): string
    {
        return 'tower';
    }

    public function getName(): string
    {
        return '🗼 Tower';
    }

    public function getUrl(): string
    {
        return $this->router->generate('app_game_tower_index');
    }

    public function getDescription(): ?string
    {
        return "Monte une tour de 9 étages : à chaque niveau, une seule case sûre, deux cases piégées. Choisis une case, grimpe ou explose. Encaisse quand tu veux.";
    }

    public static function getDescriptionInGame(): ?string
    {
        return "Tour 3 colonnes × 9 étages. À chaque étage, tu choisis une case parmi 3 :
- si tu tombes sur l’émeraude, tu montes d’un niveau et ton multiplicateur augmente ;
- si tu tombes sur une bombe, ta mise est perdue et la partie se termine.

Tu peux encaisser à tout moment : ton gain = mise × multiplicateur actuel.
Les multiplicateurs montent avec chaque étage validé. Les tirages sont faits côté serveur ; l’animation est purement visuelle. Joue responsable ✦ fixe-toi un budget et des pauses.";
    }

    public function getImageUrl(): ?string
    {
        return '/games/tower.png';
    }

    public function getMinBet(): ?int
    {
        return 1;
    }

    public function getMaxBet(): ?int
    {
        return 1000000;
    }
}
