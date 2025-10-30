<?php

namespace App\Game;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MineGame implements GameInterface
{
    public function __construct(private UrlGeneratorInterface $router) {}
    public function getKey(): string { return 'mine'; }
    public function getName(): string { return '💣 Mine'; }
    public function getUrl(): string
    {
        return $this->router->generate('app_game_dice_index');
    }
    public function getDescription(): ?string { return 'Choisit les bonnes cases sans exploser.'; }
    public static function getDescriptionInGame(): ?string { return ''; }
    public function getImageUrl() : ?string { return 'https://i.ytimg.com/vi/sxmGEDFiRAQ/maxresdefault.jpg'; }
    public function getMinBet(): ?int { return 1; }
    public function getMaxBet(): ?int { return 1000; }
}
