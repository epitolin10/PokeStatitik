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
        $this->addSql('CREATE TABLE compte (id SERIAL NOT NULL, email VARCHAR(180) NOT NULL, pseudo VARCHAR(32) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_COMPTE_EMAIL ON compte (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_COMPTE_PSEUDO ON compte (pseudo)');
        $this->addSql('ALTER TABLE build_pokemon ADD position INT NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon ADD capacite1 VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon ADD capacite2 VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon ADD capacite3 VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE build_pokemon ADD capacite4 VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE build_pokemon ADD equipe_id INT NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon DROP capacite_1');
        $this->addSql('ALTER TABLE build_pokemon DROP capacite_2');
        $this->addSql('ALTER TABLE build_pokemon DROP capacite_3');
        $this->addSql('ALTER TABLE build_pokemon DROP capacite_4');
        $this->addSql('ALTER TABLE build_pokemon ALTER iv_pv SET NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon ALTER iv_atq SET NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon ALTER iv_def SET NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon ALTER iv_atq_spe SET NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon ALTER iv_def_spe SET NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon ALTER iv_vitesse SET NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon ADD CONSTRAINT FK_4F49ED7F6D861B89 FOREIGN KEY (equipe_id) REFERENCES equipe (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_4F49ED7F6D861B89 ON build_pokemon (equipe_id)');
        $this->addSql('ALTER TABLE equipe ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE equipe ADD compte_id INT NOT NULL');
        $this->addSql('ALTER TABLE equipe ADD tiers_id INT NOT NULL');
        $this->addSql('ALTER TABLE equipe DROP id_compte');
        $this->addSql('ALTER TABLE equipe DROP id_poke_1');
        $this->addSql('ALTER TABLE equipe DROP id_poke_2');
        $this->addSql('ALTER TABLE equipe DROP id_poke_3');
        $this->addSql('ALTER TABLE equipe DROP id_poke_4');
        $this->addSql('ALTER TABLE equipe DROP id_poke_5');
        $this->addSql('ALTER TABLE equipe DROP id_poke_6');
        $this->addSql('ALTER TABLE equipe DROP tiers_equipe');
        $this->addSql('ALTER TABLE equipe ADD CONSTRAINT FK_2449BA15F2C56620 FOREIGN KEY (compte_id) REFERENCES compte (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE equipe ADD CONSTRAINT FK_2449BA1568B77723 FOREIGN KEY (tiers_id) REFERENCES tiers (id)');
        $this->addSql('CREATE INDEX IDX_2449BA15F2C56620 ON equipe (compte_id)');
        $this->addSql('CREATE INDEX IDX_2449BA1568B77723 ON equipe (tiers_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE build_pokemon DROP CONSTRAINT FK_4F49ED7F6D861B89');
        $this->addSql('DROP INDEX IDX_4F49ED7F6D861B89');
        $this->addSql('ALTER TABLE build_pokemon ADD capacite_1 VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon ADD capacite_2 VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon ADD capacite_3 VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon ADD capacite_4 VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon DROP position');
        $this->addSql('ALTER TABLE build_pokemon DROP capacite1');
        $this->addSql('ALTER TABLE build_pokemon DROP capacite2');
        $this->addSql('ALTER TABLE build_pokemon DROP capacite3');
        $this->addSql('ALTER TABLE build_pokemon DROP capacite4');
        $this->addSql('ALTER TABLE build_pokemon DROP equipe_id');
        $this->addSql('ALTER TABLE build_pokemon ALTER iv_pv DROP NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon ALTER iv_atq DROP NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon ALTER iv_def DROP NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon ALTER iv_atq_spe DROP NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon ALTER iv_def_spe DROP NOT NULL');
        $this->addSql('ALTER TABLE build_pokemon ALTER iv_vitesse DROP NOT NULL');
        $this->addSql('ALTER TABLE equipe DROP CONSTRAINT FK_2449BA15F2C56620');
        $this->addSql('ALTER TABLE equipe DROP CONSTRAINT FK_2449BA1568B77723');
        $this->addSql('DROP INDEX IDX_2449BA15F2C56620');
        $this->addSql('DROP INDEX IDX_2449BA1568B77723');
        $this->addSql('ALTER TABLE equipe ADD id_compte INT NOT NULL');
        $this->addSql('ALTER TABLE equipe ADD id_poke_1 INT NOT NULL');
        $this->addSql('ALTER TABLE equipe ADD id_poke_2 INT NOT NULL');
        $this->addSql('ALTER TABLE equipe ADD id_poke_3 INT NOT NULL');
        $this->addSql('ALTER TABLE equipe ADD id_poke_4 INT NOT NULL');
        $this->addSql('ALTER TABLE equipe ADD id_poke_5 INT NOT NULL');
        $this->addSql('ALTER TABLE equipe ADD id_poke_6 INT NOT NULL');
        $this->addSql('ALTER TABLE equipe ADD tiers_equipe INT NOT NULL');
        $this->addSql('ALTER TABLE equipe DROP created_at');
        $this->addSql('ALTER TABLE equipe DROP compte_id');
        $this->addSql('ALTER TABLE equipe DROP tiers_id');
        $this->addSql('DROP TABLE compte');
    }
}
