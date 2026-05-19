<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260519202612 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate from single-file transfers to multi-file transfer_files table';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('transfer_files')) {
            $this->addSql('CREATE TABLE transfer_files (id VARCHAR(32) NOT NULL, transfer_id VARCHAR(32) NOT NULL, original_filename VARCHAR(255) NOT NULL, stored_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(255) NOT NULL, size_bytes BIGINT NOT NULL, is_text BOOLEAN DEFAULT false NOT NULL, text_content TEXT DEFAULT NULL, download_count INT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE INDEX IDX_A5B1B501537048AF ON transfer_files (transfer_id)');
            $this->addSql('ALTER TABLE transfer_files ADD CONSTRAINT FK_A5B1B501537048AF FOREIGN KEY (transfer_id) REFERENCES transfers (id) ON DELETE CASCADE NOT DEFERRABLE');
        }

        $this->addSql('ALTER TABLE download_logs ADD transfer_file_id VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE download_logs ADD CONSTRAINT FK_E57FFD602C7E0B27 FOREIGN KEY (transfer_file_id) REFERENCES transfer_files (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_E57FFD602C7E0B27 ON download_logs (transfer_file_id)');

        $this->addSql('ALTER TABLE transfers ADD total_size_bytes BIGINT DEFAULT 0 NOT NULL');

        $this->addSql("
            INSERT INTO transfer_files (id, transfer_id, original_filename, stored_filename, mime_type, size_bytes, is_text, download_count, created_at)
            SELECT
                substr(md5(random()::text || clock_timestamp()::text), 1, 32),
                t.id,
                COALESCE(t.original_filename, 'unknown_file'),
                t.stored_filename,
                COALESCE(t.mime_type, 'application/octet-stream'),
                t.size_bytes,
                COALESCE(t.is_text, false),
                t.download_count,
                t.created_at
            FROM transfers t
            WHERE t.original_filename IS NOT NULL
        ");

        $this->addSql("
            UPDATE transfers SET total_size_bytes = COALESCE(size_bytes, 0)
        ");

        $this->addSql('ALTER TABLE transfers DROP COLUMN IF EXISTS original_filename');
        $this->addSql('ALTER TABLE transfers DROP COLUMN IF EXISTS stored_filename');
        $this->addSql('ALTER TABLE transfers DROP COLUMN IF EXISTS mime_type');
        $this->addSql('ALTER TABLE transfers DROP COLUMN IF EXISTS size_bytes');
        $this->addSql('ALTER TABLE transfers DROP COLUMN IF EXISTS is_text');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transfers ADD original_filename VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE transfers ADD stored_filename VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE transfers ADD mime_type VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE transfers ADD size_bytes BIGINT DEFAULT 0');
        $this->addSql('ALTER TABLE transfers ADD is_text BOOLEAN DEFAULT false NOT NULL');

        $this->addSql("
            UPDATE transfers t SET
                original_filename = tf.original_filename,
                stored_filename = tf.stored_filename,
                mime_type = tf.mime_type,
                size_bytes = tf.size_bytes,
                is_text = tf.is_text
            FROM transfer_files tf
            WHERE tf.transfer_id = t.id
            AND tf.id = (
                SELECT MIN(tf2.id) FROM transfer_files tf2 WHERE tf2.transfer_id = t.id
            )
        ");

        $this->addSql('ALTER TABLE download_logs DROP CONSTRAINT FK_E57FFD602C7E0B27');
        $this->addSql('DROP INDEX IDX_E57FFD602C7E0B27');
        $this->addSql('ALTER TABLE download_logs DROP transfer_file_id');
        $this->addSql('ALTER TABLE transfer_files DROP CONSTRAINT FK_A5B1B501537048AF');
        $this->addSql('DROP TABLE transfer_files');
        $this->addSql('ALTER TABLE transfers DROP total_size_bytes');
    }
}