<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crée la table task avec les colonnes : id, titre, description,
 * statut (todo | in_progress | done), created_at, updated_at.
 */
final class Version20260617083124 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table task';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE task (
                id          INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                titre       VARCHAR(255) NOT NULL,
                description CLOB         DEFAULT NULL,
                statut      VARCHAR(20)  NOT NULL DEFAULT \'todo\',
                created_at  DATETIME     NOT NULL,
                updated_at  DATETIME     NOT NULL
            )'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE task');
    }
}
