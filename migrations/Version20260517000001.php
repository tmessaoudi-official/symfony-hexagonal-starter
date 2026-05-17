<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add role column to users table.
 *
 * WHY VARCHAR and not a DB enum?
 * - Matches the existing pattern for status — DB enums require DDL ALTER TABLE to add new values.
 * - VARCHAR + application-level enum (PHP UserRole) is more flexible.
 * - A CHECK constraint provides DB-level safety.
 *
 * WHY default 'regular'?
 * - All existing rows in production have no role value. Defaulting to Regular (least privilege)
 *   is the safe choice — no user accidentally gains elevated permissions.
 */
final class Version20260517000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add role column to users table (default: regular)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'regular'");
        $this->addSql("ALTER TABLE users ADD CONSTRAINT ck_users_role CHECK (role IN ('regular', 'moderator', 'admin'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP CONSTRAINT ck_users_role');
        $this->addSql('ALTER TABLE users DROP COLUMN role');
    }
}
