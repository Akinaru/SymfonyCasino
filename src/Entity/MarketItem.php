<?php

namespace App\Entity;

use App\Enum\ItemType;
use App\Repository\MarketItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MarketItemRepository::class)]
#[ORM\Table(name: 'market_item')]
class MarketItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Type d'item (enum)
    #[ORM\Column(type: Types::STRING, length: 64, enumType: ItemType::class)]
    private ItemType $type;

    // Nom libre saisi par l'admin (peut différer du label enum)
    #[ORM\Column(type: Types::STRING, length: 120)]
    private string $name;

    // Prix demandé pour CETTE annonce (par défaut = defaultPrice() de l'enum)
    #[ORM\Column(type: Types::INTEGER)]
    private int $price;

    // Propriétaire (null => en vente sur le marché)
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $owner = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(ItemType $type)
    {
        $this->type = $type;
        $this->name = $type->label();
        $this->price = $type->defaultPrice();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getType(): ItemType { return $this->type; }
    public function setType(ItemType $type): self { $this->type = $type; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getPrice(): int { return $this->price; }
    public function setPrice(int $price): self { $this->price = $price; return $this; }

    public function getOwner(): ?Utilisateur { return $this->owner; }
    public function setOwner(?Utilisateur $owner): self { $this->owner = $owner; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isOnMarket(): bool { return $this->owner === null; }

    public function getImagePath(): string { return $this->type->imagePath(); }

    public function getTypeLabel(): string { return $this->type->label(); }
}
