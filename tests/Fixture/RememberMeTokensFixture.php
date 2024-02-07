<?php
declare(strict_types=1);

namespace RememberMe\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * RememberMeTokensFixture
 */
class RememberMeTokensFixture extends TestFixture
{
    public function init(): void
    {
        $this->records[] = [
            'id' => 1,
            'created' => '2017-09-01 11:22:33',
            'modified' => '2017-09-01 11:22:33',
            'model' => 'AuthUsers',
            'foreign_id' => '1',
            'series' => 'series_foo_1',
            'token' => 'logintoken1',
            'expires' => '2017-10-01 11:22:33',
        ];
        $this->records[] = [
            'id' => 2,
            'created' => '2017-09-02 11:22:33',
            'modified' => '2017-09-02 11:22:33',
            'model' => 'AuthUsers',
            'foreign_id' => 1,
            'series' => 'series_foo_2',
            'token' => 'logintoken2',
            'expires' => '2017-10-02 11:22:33',
        ];
        $this->records[] = [
            'id' => 3,
            'created' => '2017-09-01 11:22:33',
            'modified' => '2017-09-01 11:22:33',
            'model' => 'AuthUsers',
            'foreign_id' => 2,
            'series' => 'series_bar_1',
            'token' => 'logintoken3',
            'expires' => '2017-10-01 11:22:33',
        ];
        $this->records[] = [
            'id' => 4,
            'created' => '2017-09-02 11:22:33',
            'modified' => '2017-09-02 11:22:33',
            'model' => 'AuthUsers',
            'foreign_id' => 2,
            'series' => 'series_bar_2',
            'token' => 'logintoken4',
            'expires' => '2017-10-02 11:22:33',
        ];
        $this->records[] = [
            'id' => 5,
            'created' => '2017-09-01 11:22:33',
            'modified' => '2017-09-01 11:22:33',
            'model' => 'AuthUsers',
            'foreign_id' => 3,
            'series' => 'series_boo_1',
            'token' => 'logintoken5',
            'expires' => '2017-10-01 11:22:33',
        ];
        $this->records[] = [
            'id' => 6,
            'created' => '2017-09-02 11:22:33',
            'modified' => '2017-09-02 11:22:33',
            'model' => 'AuthUsers',
            'foreign_id' => 3,
            'series' => 'series_boo_2',
            'token' => 'logintoken6',
            'expires' => '2017-10-02 11:22:33',
        ];
        parent::init();
    }
}
