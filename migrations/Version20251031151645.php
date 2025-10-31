<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251031151645 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE market_item (id SERIAL NOT NULL, owner_id INT DEFAULT NULL, type VARCHAR(64) NOT NULL, name VARCHAR(120) NOT NULL, price INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5017DCAE7E3C61F9 ON market_item (owner_id)');
        $this->addSql('COMMENT ON COLUMN market_item.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE market_item ADD CONSTRAINT FK_5017DCAE7E3C61F9 FOREIGN KEY (owner_id) REFERENCES utilisateur (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE market_item DROP CONSTRAINT FK_5017DCAE7E3C61F9');
        $this->addSql('DROP TABLE market_item');
    }
}
