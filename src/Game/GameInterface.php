<?php
namespace App\Game;

interface GameInterface
{
    public function getKey(): string;
    public function getName(): string;
    public function getUrl(): string;
    public function getDescription(): ?string;
    public static function getDescriptionInGame(): ?string;
    public function getImageUrl(): ?string;
    public function getMinBet(): ?int;
    public function getMaxBet(): ?int;
}
