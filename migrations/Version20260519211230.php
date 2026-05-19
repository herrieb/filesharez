<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260519211230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add file_requests table and transfer sender/file_request fields';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE file_requests (id VARCHAR(32) NOT NULL, token_hash VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, max_files INT DEFAULT 10 NOT NULL, max_file_size_bytes BIGINT DEFAULT 1073741824 NOT NULL, max_total_size_bytes BIGINT DEFAULT 5368709120 NOT NULL, password_hash VARCHAR(255) DEFAULT NULL, is_active BOOLEAN DEFAULT true NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id VARCHAR(32) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CAD559CCB3BC57DA ON file_requests (token_hash)');
        $this->addSql('CREATE INDEX IDX_CAD559CCA76ED395 ON file_requests (user_id)');
        $this->addSql('ALTER TABLE file_requests ADD CONSTRAINT FK_CAD559CCA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE transfers ADD sender_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE transfers ADD sender_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE transfers ADD file_request_id VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE transfers ADD CONSTRAINT FK_802A3918FB966313 FOREIGN KEY (file_request_id) REFERENCES file_requests (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_802A3918FB966313 ON transfers (file_request_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE file_requests DROP CONSTRAINT FK_CAD559CCA76ED395');
        $this->addSql('DROP TABLE file_requests');
        $this->addSql('ALTER TABLE transfers DROP CONSTRAINT FK_802A3918FB966313');
        $this->addSql('DROP INDEX IDX_802A3918FB966313');
        $this->addSql('ALTER TABLE transfers DROP sender_name');
        $this->addSql('ALTER TABLE transfers DROP sender_email');
        $this->addSql('ALTER TABLE transfers DROP file_request_id');
    }
}
