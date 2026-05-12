<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the users table.
 *
 * WHY use a GUID (UUID) as primary key instead of auto-increment?
 * - UUIDs can be generated without a DB round-trip (see UserId::generate()).
 * - They're safe to expose in URLs (no sequential enumeration vulnerability).
 * - They work well in distributed systems where multiple services create records.
 *
 * Performance note: UUID PKs in PostgreSQL use UUID type natively, which is indexed
 * efficiently. In MySQL, use CHAR(36) or BINARY(16) depending on your version.
 *
 * WHY store status as VARCHAR not a custom DB enum?
 * - DB enums require DDL ALTER TABLE to add new values — painful with migrations.
 * - VARCHAR + application-level enum (PHP UserStatus) is more flexible.
 * - A CHECK constraint provides the DB-level safety we'd otherwise get from a DB enum.
 */
final class Version20260101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id         UUID         NOT NULL,
                email      VARCHAR(254) NOT NULL,
                name       VARCHAR(100) NOT NULL,
                status     VARCHAR(20)  NOT NULL DEFAULT 'active',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT pk_users PRIMARY KEY (id),
                CONSTRAINT uq_users_email UNIQUE (email),
                CONSTRAINT ck_users_status CHECK (status IN ('active', 'inactive'))
            )
        SQL);

        $this->addSql('CREATE INDEX idx_users_created_at ON users (created_at)');
        $this->addSql('COMMENT ON COLUMN users.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN users.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE users');
    }
}
