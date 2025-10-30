<?php
namespace App\Game;

interface GameInterface
{
    public function getKey(): string;        // ex: 'blackjack'
    public function getName(): string;        // ex: 'Blackjack'
    public function getUrl(): string;
    public function getDescription(): ?string;
    public static function getDescriptionInGame(): ?string;
    public function getImageUrl(): ?string;
    public function getMinBet(): ?int;      // optionnel (null si non défini)
    public function getMaxBet(): ?int;      // optionnel (null si non défini)
}
