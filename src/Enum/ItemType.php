<?php

namespace App\Enum;

enum ItemType: string
{
    case POUDRE_BLANCHE = 'POUDRE_BLANCHE';
    case BONBON_ENERGISANT = 'BONBON_ENERGISANT';
    case HERBE_SUSPECTE = 'HERBE_SUSPECTE';
    case CHAMPIGNON_LUISANT = 'CHAMPIGNON_LUISANT';
    case SIROPE_NOCTURNE = 'SIROPE_NOCTURNE';

    public function label(): string
    {
        return match ($this) {
            self::POUDRE_BLANCHE   => 'Poudre blanche',
            self::BONBON_ENERGISANT => 'Bonbon énergisant',
            self::HERBE_SUSPECTE   => 'Herbe suspecte',
            self::CHAMPIGNON_LUISANT => 'Champignon luisant',
            self::SIROPE_NOCTURNE  => 'Sirop nocturne',
        };
    }

    public function defaultPrice(): int
    {
        return match ($this) {
            self::POUDRE_BLANCHE     => 250,
            self::BONBON_ENERGISANT  => 400,
            self::HERBE_SUSPECTE     => 150,
            self::CHAMPIGNON_LUISANT => 300,
            self::SIROPE_NOCTURNE    => 500,
        };
    }

    public function imagePath(): string
    {
        // Place ces fichiers dans public/img/items/
        return match ($this) {
            self::POUDRE_BLANCHE     => 'img/items/sucre.webp',         // sucre Minecraft
            self::BONBON_ENERGISANT  => 'img/items/golden_apple.png',  // golden apple
            self::HERBE_SUSPECTE     => 'img/items/herb.png',
            self::CHAMPIGNON_LUISANT => 'img/items/glow_mushroom.png',
            self::SIROPE_NOCTURNE    => 'img/items/dark_syrup.png',
        };
    }
}
