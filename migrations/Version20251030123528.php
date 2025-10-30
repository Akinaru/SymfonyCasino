<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251030123528 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE partie (id SERIAL NOT NULL, utilisateur_id INT NOT NULL, game_key VARCHAR(100) NOT NULL, mise INT DEFAULT 0 NOT NULL, gain INT DEFAULT 0 NOT NULL, resultat_net INT DEFAULT 0 NOT NULL, issue VARCHAR(10) DEFAULT \'perdu\' NOT NULL, debut_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, fin_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, meta_json TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_59B1F3DFB88E14F ON partie (utilisateur_id)');
        $this->addSql('CREATE TABLE transaction (id SERIAL NOT NULL, utilisateur_id INT NOT NULL, partie_id INT DEFAULT NULL, game_key VARCHAR(100) DEFAULT NULL, type VARCHAR(20) NOT NULL, montant INT NOT NULL, solde_avant INT NOT NULL, solde_apres INT NOT NULL, cree_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_723705D1FB88E14F ON transaction (utilisateur_id)');
        $this->addSql('CREATE INDEX IDX_723705D1E075F7A4 ON transaction (partie_id)');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3DFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1E075F7A4 FOREIGN KEY (partie_id) REFERENCES partie (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE partie DROP CONSTRAINT FK_59B1F3DFB88E14F');
        $this->addSql('ALTER TABLE transaction DROP CONSTRAINT FK_723705D1FB88E14F');
        $this->addSql('ALTER TABLE transaction DROP CONSTRAINT FK_723705D1E075F7A4');
        $this->addSql('DROP TABLE partie');
        $this->addSql('DROP TABLE transaction');
    }
}
