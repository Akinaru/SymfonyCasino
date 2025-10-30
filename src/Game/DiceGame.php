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
    public function getDescription(): ?string { return 'Tire les dés et fait obtiens un nombre au dessus de 3 pour gagner.'; }
    public static function getDescriptionInGame(): ?string { return 'Lance le dé et obitens un numéro superieur à 3 pour gagner.'; }
    public function getImageUrl() : ?string { return 'https://i.redd.it/1wsjdtem9jef1.jpeg'; }
    public function getMinBet(): ?int { return 1; }
    public function getMaxBet(): ?int { return 1000; }
}
