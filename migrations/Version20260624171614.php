<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624171614 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Owner library access log (owner-direct download/preview)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE owner_access_logs (
            id VARCHAR(32) NOT NULL,
            user_id VARCHAR(32) NOT NULL,
            source_id VARCHAR(32) NOT NULL,
            path VARCHAR(1024) NOT NULL,
            action VARCHAR(16) NOT NULL,
            size_bytes BIGINT DEFAULT NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_owner_log_user ON owner_access_logs (user_id, created_at)');
        $this->addSql('CREATE INDEX idx_owner_log_source ON owner_access_logs (source_id, created_at)');
        $this->addSql('CREATE INDEX idx_owner_log_created ON owner_access_logs (created_at)');
        $this->addSql('ALTER TABLE owner_access_logs ADD CONSTRAINT fk_owner_log_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE owner_access_logs ADD CONSTRAINT fk_owner_log_source FOREIGN KEY (source_id) REFERENCES library_sources (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE owner_access_logs');
    }
}
