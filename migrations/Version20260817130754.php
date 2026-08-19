<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260817130754 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE compte (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, pseudo VARCHAR(32) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_COMPTE_EMAIL (email), UNIQUE INDEX UNIQ_COMPTE_PSEUDO (pseudo), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE build_pokemon ADD position INT NOT NULL, ADD capacite1 VARCHAR(255) NOT NULL, ADD capacite2 VARCHAR(255) NOT NULL, ADD capacite3 VARCHAR(255) DEFAULT NULL, ADD capacite4 VARCHAR(255) DEFAULT NULL, ADD equipe_id INT NOT NULL, DROP capacite_1, DROP capacite_2, DROP capacite_3, DROP capacite_4, CHANGE iv_pv iv_pv INT NOT NULL, CHANGE iv_atq iv_atq INT NOT NULL, CHANGE iv_def iv_def INT NOT NULL, CHANGE iv_atq_spe iv_atq_spe INT NOT NULL, CHANGE iv_def_spe iv_def_spe INT NOT NULL, CHANGE iv_vitesse iv_vitesse INT NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon ADD CONSTRAINT FK_4F49ED7F6D861B89 FOREIGN KEY (equipe_id) REFERENCES equipe (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_4F49ED7F6D861B89 ON build_pokemon (equipe_id)');
        $this->addSql('ALTER TABLE equipe ADD created_at DATETIME NOT NULL, ADD compte_id INT NOT NULL, ADD tiers_id INT NOT NULL, DROP id_compte, DROP id_poke_1, DROP id_poke_2, DROP id_poke_3, DROP id_poke_4, DROP id_poke_5, DROP id_poke_6, DROP tiers_equipe');
        $this->addSql('ALTER TABLE equipe ADD CONSTRAINT FK_2449BA15F2C56620 FOREIGN KEY (compte_id) REFERENCES compte (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE equipe ADD CONSTRAINT FK_2449BA1568B77723 FOREIGN KEY (tiers_id) REFERENCES tiers (id)');
        $this->addSql('CREATE INDEX IDX_2449BA15F2C56620 ON equipe (compte_id)');
        $this->addSql('CREATE INDEX IDX_2449BA1568B77723 ON equipe (tiers_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE compte');
        $this->addSql('ALTER TABLE build_pokemon DROP FOREIGN KEY FK_4F49ED7F6D861B89');
        $this->addSql('DROP INDEX IDX_4F49ED7F6D861B89 ON build_pokemon');
        $this->addSql('ALTER TABLE build_pokemon ADD capacite_1 VARCHAR(255) NOT NULL, ADD capacite_2 VARCHAR(255) NOT NULL, ADD capacite_3 VARCHAR(255) NOT NULL, ADD capacite_4 VARCHAR(255) NOT NULL, DROP position, DROP capacite1, DROP capacite2, DROP capacite3, DROP capacite4, DROP equipe_id, CHANGE iv_pv iv_pv INT DEFAULT NULL, CHANGE iv_atq iv_atq INT DEFAULT NULL, CHANGE iv_def iv_def INT DEFAULT NULL, CHANGE iv_atq_spe iv_atq_spe INT DEFAULT NULL, CHANGE iv_def_spe iv_def_spe INT DEFAULT NULL, CHANGE iv_vitesse iv_vitesse INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipe DROP FOREIGN KEY FK_2449BA15F2C56620');
        $this->addSql('ALTER TABLE equipe DROP FOREIGN KEY FK_2449BA1568B77723');
        $this->addSql('DROP INDEX IDX_2449BA15F2C56620 ON equipe');
        $this->addSql('DROP INDEX IDX_2449BA1568B77723 ON equipe');
        $this->addSql('ALTER TABLE equipe ADD id_compte INT NOT NULL, ADD id_poke_1 INT NOT NULL, ADD id_poke_2 INT NOT NULL, ADD id_poke_3 INT NOT NULL, ADD id_poke_4 INT NOT NULL, ADD id_poke_5 INT NOT NULL, ADD id_poke_6 INT NOT NULL, ADD tiers_equipe INT NOT NULL, DROP created_at, DROP compte_id, DROP tiers_id');
    }
}
