<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260820150830 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE classement_entry (id INT AUTO_INCREMENT NOT NULL, pokemon_slug VARCHAR(100) NOT NULL, rang INT NOT NULL, tiers_id INT NOT NULL, INDEX IDX_6032549968B77723 (tiers_id), UNIQUE INDEX UNIQ_CLASSEMENT_TIERS_RANG (tiers_id, rang), UNIQUE INDEX UNIQ_CLASSEMENT_TIERS_POKEMON (tiers_id, pokemon_slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE classement_entry ADD CONSTRAINT FK_6032549968B77723 FOREIGN KEY (tiers_id) REFERENCES tiers (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE classement_entry DROP FOREIGN KEY FK_6032549968B77723');
        $this->addSql('DROP TABLE classement_entry');
    }
}
