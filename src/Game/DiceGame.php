<?php

namespace App\Game;

final class DiceGame implements GameInterface
{
    public function getKey(): string { return 'dice'; }
    public function getName(): string { return 'Dice'; }
    public function getDescription(): ?string { return 'Roll a die: higher roll wins.'; }
    public function getMinBet(): ?int { return 1; }
    public function getMaxBet(): ?int { return 1000; }
}
