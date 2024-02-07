<?php
/** @noinspection AutoloadingIssuesInspection */
/** @noinspection PhpUnused */
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Create users table for test
 */
class CreateUsers extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('users');
        $table->addTimestamps('created', 'updated');

        $table->addColumn('username', 'string');
        $table->addColumn('password', 'string');

        $table->create();
    }
}
