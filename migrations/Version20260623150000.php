<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'User-selected theme (longhorn, sunset, midori, rose-gold, crt, aurora)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD theme VARCHAR(32) NOT NULL DEFAULT 'longhorn'");
        $this->addSql("UPDATE users SET theme = 'longhorn' WHERE theme = '' OR theme IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP theme');
    }
}
