<?php

namespace App\Game;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MinesGame implements GameInterface
{
    public const GRID_SIZE   = 25;
    public const MIN_MINES   = 1;
    public const MAX_MINES   = 24;
    public const DEFAULT_RTP = 0.99;

    public function __construct(
        private UrlGeneratorInterface $router
    ) {
    }

    public function getKey(): string
    {
        return 'mines';
    }

    public function getName(): string
    {
        return '💣 Mines';
    }

    public function getUrl(): string
    {
        return $this->router->generate('app_game_mines_index');
    }

    public function getDescription(): ?string
    {
        return "Grille 5×5, entre 1 et 24 mines. Choisis le nombre de mines, mise, puis révèle des cases : chaque diamant augmente ton multiplicateur, mais une seule mine fait tout exploser. Tu peux encaisser à tout moment.";
    }

    public static function getDescriptionInGame(): ?string
    {
        return "Choisis ta mise et le nombre de mines, trouve des diamants pour augmenter le multiplicateur et encaisse avant d’exploser.";
    }



    public function getImageUrl(): ?string
    {
        return 'https://mediumrare.imgix.net/15a51a2ae2895872ae2b600fa6fe8d7f8d32c9814766b66ddea2b288d04ba89c?q=85';
    }

    public function getMinBet(): ?int
    {
        return 1;
    }

    public function getMaxBet(): ?int
    {
        return 1000000;
    }

    public static function getMultiplier(int $mines, int $diamondsFound, ?float $rtp = null): float
    {
        $rtp ??= self::DEFAULT_RTP;

        if ($mines < self::MIN_MINES || $mines > self::MAX_MINES) {
            throw new \InvalidArgumentException(sprintf('Nombre de mines invalide (%d).', $mines));
        }

        $maxDiamonds = self::GRID_SIZE - $mines;
        if ($diamondsFound < 1 || $diamondsFound > $maxDiamonds) {
            throw new \InvalidArgumentException(sprintf(
                'Nombre de diamants trouvés invalide (%d) pour %d mines (max %d).',
                $diamondsFound,
                $mines,
                $maxDiamonds
            ));
        }

        $multiplier = 1.0;

        for ($i = 0; $i < $diamondsFound; $i++) {
            $numerator   = self::GRID_SIZE - $i;
            $denominator = (self::GRID_SIZE - $mines) - $i;
            $multiplier *= $numerator / $denominator;
        }

        $multiplier *= $rtp;

        return round($multiplier, 4);
    }

    public static function getPayoutRow(int $mines, ?float $rtp = null): array
    {
        $rtp ??= self::DEFAULT_RTP;

        if ($mines < self::MIN_MINES || $mines > self::MAX_MINES) {
            throw new \InvalidArgumentException(sprintf('Nombre de mines invalide (%d).', $mines));
        }

        $maxDiamonds = self::GRID_SIZE - $mines;
        $row = [];

        for ($k = 1; $k <= $maxDiamonds; $k++) {
            $row[$k] = self::getMultiplier($mines, $k, $rtp);
        }

        return $row;
    }

    public static function getPayoutTable(?float $rtp = null): array
    {
        $rtp ??= self::DEFAULT_RTP;

        $table = [];
        for ($m = self::MIN_MINES; $m <= self::MAX_MINES; $m++) {
            $table[$m] = self::getPayoutRow($m, $rtp);
        }

        return $table;
    }
}
