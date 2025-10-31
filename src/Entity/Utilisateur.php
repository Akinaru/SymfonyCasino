<?php

namespace App\Entity;

use App\Enum\TransactionType;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $pseudo = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $balance = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $wagger = 0;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(?string $pseudo): self
    {
        $this->pseudo = $pseudo;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getAvatarUrl(): string
    {
        $base = 'https://mc-heads.net/avatar';
        $avatar = $this->avatar ?? null;

        if (is_string($avatar) && $avatar !== '') {
            return $base . '/' . rawurlencode($avatar);
        }

        return $base;
    }

    public function getBalance(): float
    {
        return $this->balance / 100;
    }

    public function setBalance(float $euros): self
    {
        $this->balance = (int) round($euros * 100);
        return $this;
    }

    public function getWagger(): float
    {
        return $this->wagger / 100;
    }

    public function setWagger(float $euros): self
    {
        $this->wagger = (int) round($euros * 100);
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Compte le nombre de paris (transactions de type MISE).
     * @param iterable<Transaction> $transactions
     */
    public function getBetsCountFrom(iterable $transactions): int
    {
        $c = 0;
        foreach ($transactions as $t) {
            if ($t->getType() === TransactionType::MISE) {
                $c++;
            }
        }
        return $c;
    }

    /**
     * Taux de victoire (%) = nb(GAIN) / nb(MISE) * 100.
     * @param iterable<Transaction> $transactions
     */
    public function getWinRateFrom(iterable $transactions): float
    {
        $bets = 0;
        $wins = 0;

        foreach ($transactions as $t) {
            $type = $t->getType();
            if ($type === TransactionType::MISE) {
                $bets++;
            } elseif ($type === TransactionType::GAIN) {
                $wins++;
            }
        }

        if ($bets === 0) {
            return 0.0;
        }

        return ($wins / $bets) * 100.0;
    }

    /**
     * Mise moyenne (en unités de ta colonne "montant", ici euros entiers).
     * Moyenne des |montant| pour les MISE (les mises sont négatives en base).
     * @param iterable<Transaction> $transactions
     */
    public function getAverageBetFrom(iterable $transactions): float
    {
        $sum = 0.0;
        $n   = 0;

        foreach ($transactions as $t) {
            if ($t->getType() === TransactionType::MISE) {
                $sum += abs($t->getMontant());
                $n++;
            }
        }

        if ($n === 0) {
            return 0.0;
        }

        return $sum / $n;
    }

    /**
     * Plus gros gain (maximum des montants pour type GAIN).
     * @param iterable<Transaction> $transactions
     */
    public function getBiggestWinFrom(iterable $transactions): int
    {
        $max = 0;
        foreach ($transactions as $t) {
            if ($t->getType() === TransactionType::GAIN) {
                $m = $t->getMontant(); // positif
                if ($m > $max) {
                    $max = $m;
                }
            }
        }
        return $max;
    }

    /**
     * Jeu favori (laisse à null pour le moment comme demandé).
     * @param iterable<Transaction> $transactions
     */
    public function getFavoriteGameKeyFrom(iterable $transactions): ?string
    {
        return null;
    }

    /**
     * Dernier jeu (laisse à null pour le moment).
     * @param iterable<Transaction> $transactions
     */
    public function getLastGameKeyFrom(iterable $transactions): ?string
    {
        return null;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }
}
