<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add library_sources and library_items tables; mark transfers as library-backed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE library_sources (id VARCHAR(32) NOT NULL, owner_id VARCHAR(32) NOT NULL, name VARCHAR(255) NOT NULL, path VARCHAR(1024) NOT NULL, is_active BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_scanned_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, item_count INT DEFAULT 0 NOT NULL, total_size_bytes BIGINT DEFAULT 0 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_library_owner ON library_sources (owner_id)');
        $this->addSql('ALTER TABLE library_sources ADD CONSTRAINT fk_library_owner FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('CREATE TABLE library_items (id VARCHAR(32) NOT NULL, source_id VARCHAR(32) NOT NULL, path VARCHAR(1024) NOT NULL, name VARCHAR(512) NOT NULL, relative_path VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(255) DEFAULT NULL, size_bytes BIGINT DEFAULT 0 NOT NULL, is_directory BOOLEAN DEFAULT false NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_library_source_path ON library_items (source_id, path)');
        $this->addSql('ALTER TABLE library_items ADD CONSTRAINT fk_library_item_source FOREIGN KEY (source_id) REFERENCES library_sources (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('ALTER TABLE transfers ADD is_from_library BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE transfers ADD library_item_id VARCHAR(32) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_transfer_library_item ON transfers (library_item_id)');
        $this->addSql('ALTER TABLE transfers ADD CONSTRAINT fk_transfer_library_item FOREIGN KEY (library_item_id) REFERENCES library_items (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transfers DROP CONSTRAINT fk_transfer_library_item');
        $this->addSql('DROP INDEX idx_transfer_library_item');
        $this->addSql('ALTER TABLE transfers DROP library_item_id');
        $this->addSql('ALTER TABLE transfers DROP is_from_library');
        $this->addSql('DROP TABLE library_items');
        $this->addSql('DROP TABLE library_sources');
    }
}
