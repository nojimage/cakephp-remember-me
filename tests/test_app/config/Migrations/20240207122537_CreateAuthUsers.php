<?php
/** @noinspection AutoloadingIssuesInspection */
/** @noinspection PhpUnused */
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Create auth_users table for test
 */
class CreateAuthUsers extends AbstractMigration
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
        $table = $this->table('auth_users');

        $table->addColumn('username', 'string', [
            'default' => null,
            'limit' => 190,
            'null' => false,
        ]);
        $table->addColumn('password', 'string', [
            'limit' => 255,
            'null' => true,
            'default' => null,
        ]);
        $table->addIndex(['username'], ['unique' => true, 'name' => 'U_username']);

        $table->create();
    }
}
