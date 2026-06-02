<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add saved_transfer_tokens table so owners can recover share links';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE saved_transfer_tokens (id VARCHAR(32) NOT NULL, user_id VARCHAR(32) NOT NULL, transfer_id VARCHAR(32) NOT NULL, raw_token VARCHAR(255) NOT NULL, label VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_transfer ON saved_transfer_tokens (user_id, transfer_id)');
        $this->addSql('CREATE INDEX idx_saved_token_user ON saved_transfer_tokens (user_id)');
        $this->addSql('ALTER TABLE saved_transfer_tokens ADD CONSTRAINT fk_saved_token_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE saved_transfer_tokens ADD CONSTRAINT fk_saved_token_transfer FOREIGN KEY (transfer_id) REFERENCES transfers (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE saved_transfer_tokens');
    }
}
