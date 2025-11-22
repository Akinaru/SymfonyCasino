<?php

namespace App\Game;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DiceGame implements GameInterface
{
    public function __construct(private UrlGeneratorInterface $router) {}
    public function getKey(): string { return 'dice'; }
    public function getName(): string { return '🎲 Dice'; }
    public function getUrl(): string
    {
        return $this->router->generate('app_game_dice_index');
    }
    public function getDescription(): ?string
    {
        return "Un tirage ultra simple : Diamant (tu gagnes) ou Saut de lave (tu perds). Mise, lance, résultat immédiat.";
    }

    public static function getDescriptionInGame(): ?string
    {
        return "Choisis ta mise puis lance la manche : le serveur tire aléatoirement « Diamant » ou « Saut de lave ».
Si c’est « Diamant », tu encaisses (ex. x2 de ta mise) immédiatement ; si c’est « Saut de lave », la mise est perdue.
Limites de table affichées dans le panneau, animation purement visuelle, résultat garanti côté serveur. Joue responsable ✦ fixe-toi un budget et des pauses.";
    }
    public function getImageUrl() : ?string { return 'https://mediumrare.imgix.net/30688668d7d2d48d472edd0f1e2bca0758e7ec51cbab8c04d8b7f157848640e0'; }
    public function getMinBet(): ?int { return 1; }
    public function getMaxBet(): ?int { return 1000000; }
}
