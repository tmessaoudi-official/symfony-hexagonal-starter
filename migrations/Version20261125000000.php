<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drop the "(DC2Type:datetime_immutable)" column comments.
 *
 * WHY?
 * - DBAL 3 stored the Doctrine type in a column comment when the SQL type alone was ambiguous.
 *   DBAL 4 no longer reads or writes these comments, so it reports them as schema drift and
 *   doctrine:schema:validate fails until they are gone.
 * - Version20260101000000 is left untouched: a migration that may already have run somewhere
 *   is never edited, the fix is a new migration.
 */
final class Version20261125000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop DBAL 3 type comments on users timestamps (DBAL 4 does not use them)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('COMMENT ON COLUMN users.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN users.updated_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("COMMENT ON COLUMN users.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN users.updated_at IS '(DC2Type:datetime_immutable)'");
    }
}
