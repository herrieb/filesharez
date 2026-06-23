<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Resumable uploads (tus): upload_sessions table + users.reserved_bytes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD reserved_bytes BIGINT NOT NULL DEFAULT 0');

        $this->addSql('CREATE TABLE upload_sessions (
            id VARCHAR(32) NOT NULL,
            user_id VARCHAR(32) NOT NULL,
            original_filename VARCHAR(512) NOT NULL,
            mime_type VARCHAR(255) DEFAULT NULL,
            size_bytes BIGINT NOT NULL,
            offset_bytes BIGINT NOT NULL DEFAULT 0,
            temp_path VARCHAR(1024) NOT NULL,
            metadata TEXT DEFAULT NULL,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_upload_session_user ON upload_sessions (user_id)');
        $this->addSql('CREATE INDEX idx_upload_session_expires ON upload_sessions (expires_at)');
        $this->addSql('ALTER TABLE upload_sessions ADD CONSTRAINT fk_upload_session_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE upload_sessions');
        $this->addSql('ALTER TABLE users DROP reserved_bytes');
    }
}
