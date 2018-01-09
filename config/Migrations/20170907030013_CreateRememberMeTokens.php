<?php

use Migrations\AbstractMigration;

class CreateRememberMeTokens extends AbstractMigration
{

    /**
     * Change Method.
     *
     * More information on this method is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     * @return void
     */
    public function change()
    {
        $table = $this->table('remember_me_tokens');
        $table->addTimestamps('created', 'modified');

        $table->addColumn('model', 'string', [
            'default' => null,
            'limit' => 64,
            'null' => false,
        ]);
        $table->addColumn('foreign_id', 'string', [
            'default' => null,
            'limit' => 36,
            'null' => false,
        ]);
        $table->addColumn('series', 'string', [
            'default' => null,
            'limit' => 64,
            'null' => false,
        ]);
        $table->addColumn('token', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('expires', 'timestamp', [
            'default' => '0000-00-00 00:00:00',
            'null' => false,
        ]);

        $table->addIndex([
            'model',
            'foreign_id',
            'series',
        ], [
            'name' => 'U_token_identifier',
            'unique' => true,
        ]);

        $table->create();
    }
}
