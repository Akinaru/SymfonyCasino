<?php
// src/Game/GameRegistry.php
namespace App\Game;

final class GameRegistry
{
    /** @var array<string, GameInterface> */
    private array $games = [];

    public function __construct(iterable $games)
    {
        foreach ($games as $game) {
            $this->games[$game->getKey()] = $game;
        }
    }

    /** @return GameInterface[] */
    public function all(): array { return array_values($this->games); }

    public function byKey(string $key): ?GameInterface { return $this->games[$key] ?? null; }

    /** @return string[] */
    public function keys(): array { return array_keys($this->games); }

    public function name(?string $key): ?string
    {
        if (!$key) return null;
        return $this->games[$key]->getName() ?? null;
    }
}
