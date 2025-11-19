<?php

namespace App\Game;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SlotGame implements GameInterface
{
    public function __construct(private UrlGeneratorInterface $router) {}
    public function getKey(): string { return 'slot'; }
    public function getName(): string { return '🎰 Slot'; }
    public function getUrl(): string
    {
        return $this->router->generate('app_game_slots_index');
    }
    public function getDescription(): ?string { return 'Tire les 3 items similaire pour toucher le gros lot.'; }
    public static function getDescriptionInGame(): ?string { return ''; }
    public function getImageUrl() : ?string { return 'https://i.ytimg.com/vi/sxmGEDFiRAQ/maxresdefault.jpg'; }
    public function getMinBet(): ?int { return 1; }
    public function getMaxBet(): ?int { return 1000000; }
}
