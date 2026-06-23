<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Library folder navigation: parent_path column, unique (source_id, path) index, saved_transfer_tokens.relative_path';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE library_items ADD parent_path VARCHAR(1024) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_library_source_parent ON library_items (source_id, parent_path)');

        $this->addSql('DROP INDEX idx_library_source_path');
        $this->addSql('CREATE UNIQUE INDEX uniq_library_source_path ON library_items (source_id, path)');

        $this->addSql("UPDATE library_items SET relative_path = substring(path FROM length((SELECT s.path FROM library_sources s WHERE s.id = library_items.source_id)) + 1) WHERE relative_path IS NULL");
        $this->addSql("UPDATE library_items SET parent_path = (SELECT s.path FROM library_sources s WHERE s.id = library_items.source_id) WHERE is_directory = false OR parent_path IS NULL");

        $this->addSql('ALTER TABLE saved_transfer_tokens ADD relative_path VARCHAR(1024) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_saved_token_relative ON saved_transfer_tokens (user_id, relative_path)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_saved_token_relative');
        $this->addSql('ALTER TABLE saved_transfer_tokens DROP relative_path');

        $this->addSql('DROP INDEX uniq_library_source_path');
        $this->addSql('CREATE INDEX idx_library_source_path ON library_items (source_id, path)');

        $this->addSql('DROP INDEX idx_library_source_parent');
        $this->addSql('ALTER TABLE library_items DROP parent_path');
    }
}