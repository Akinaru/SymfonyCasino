<?php

namespace App\Game;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RouletteGame implements GameInterface
{
    public function __construct(private UrlGeneratorInterface $router)
    {
    }

    public function getKey(): string
    {
        return 'roulette';
    }

    public function getName(): string
    {
        return '🎡 Roulette';
    }

    public function getUrl(): string
    {
        return $this->router->generate('app_game_roulette_index');
    }

    public function getDescription(): ?string
    {
        return "Pariez sur Rouge, Noir ou Vert comme à la roulette : même edge que la vraie roulette européenne, avec un 0 vert rare mais très bien payé.";
    }

    public static function getDescriptionInGame(): ?string
    {
        return "Roulette couleur simplifiée inspirée de la roulette européenne (37 cases : 18 rouges, 18 noires, 1 verte).
Choisissez un montant et une couleur :
- Rouge ou Noir : environ 48,65 % de chances, paiement x2 (mise + gain).
- Vert (0) : environ 2,7 % de chances, paiement x36.

Le tirage du numéro et de la couleur est effectué côté serveur, l’animation n’est qu’un habillage visuel. Joue responsable ✦ fixe-toi un budget et des pauses.";
    }

    public function getImageUrl(): ?string
    {
        return '/games/roulette.png';
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
