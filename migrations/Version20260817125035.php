<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260817125035 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE build_pokemon (id SERIAL NOT NULL, pokemon_url TEXT NOT NULL, objet VARCHAR(255) DEFAULT NULL, capacite_1 VARCHAR(255) NOT NULL, capacite_2 VARCHAR(255) NOT NULL, capacite_3 VARCHAR(255) NOT NULL, capacite_4 VARCHAR(255) NOT NULL, nature VARCHAR(255) NOT NULL, talent VARCHAR(255) NOT NULL, iv_pv INT DEFAULT NULL, iv_atq INT DEFAULT NULL, iv_def INT DEFAULT NULL, iv_atq_spe INT DEFAULT NULL, iv_def_spe INT DEFAULT NULL, iv_vitesse INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE equipe (id SERIAL NOT NULL, id_compte INT NOT NULL, titre VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, id_poke_1 INT NOT NULL, id_poke_2 INT NOT NULL, id_poke_3 INT NOT NULL, id_poke_4 INT NOT NULL, id_poke_5 INT NOT NULL, id_poke_6 INT NOT NULL, id_equipe_pokemon_champions VARCHAR(20) DEFAULT NULL, tiers_equipe INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE tiers (id SERIAL NOT NULL, nom_tiers VARCHAR(50) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE build_pokemon');
        $this->addSql('DROP TABLE equipe');
        $this->addSql('DROP TABLE tiers');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
