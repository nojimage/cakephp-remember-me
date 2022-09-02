<?php
declare(strict_types=1);

namespace RememberMe\Test\TestCase\Model\Table;

use Cake\I18n\FrozenTime;
use Cake\TestSuite\TestCase;
use RememberMe\Model\Table\RememberMeTokensTable;

/**
 * RememberMe\Model\Table\RememberMeTokensTable Test Case
 */
class RememberMeTokensTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var RememberMeTokensTable
     */
    public $RememberMeTokens;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.RememberMe.RememberMeTokens',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->RememberMeTokens = $this->getTableLocator()->get('RememberMeTokens', ['className' => RememberMeTokensTable::class]);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->RememberMeTokens);

        parent::tearDown();
    }

    /**
     * Test initialize method
     *
     * @return void
     */
    public function testInitialize(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test validationDefault method
     *
     * @return void
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    public function testDropExpired(): void
    {
        FrozenTime::setTestNow('2017-10-01 11:22:33');
        $deleteCount = $this->RememberMeTokens->dropExpired();
        $this->assertSame(0, $deleteCount, 'not change');
        $this->assertCount(6, $this->RememberMeTokens->find()->all(), 'not change');

        FrozenTime::setTestNow('2017-10-01 11:22:34');
        $deleteCount = $this->RememberMeTokens->dropExpired();
        $this->assertSame(3, $deleteCount);
        $this->assertCount(3, $this->RememberMeTokens->find()->all());
    }

    public function testDropExpiredWithArgs(): void
    {
        FrozenTime::setTestNow('2017-10-01 11:22:34');
        $deleteCount = $this->RememberMeTokens->dropExpired('Users');
        $this->assertSame(0, $deleteCount, 'not matching');
        $this->assertCount(6, $this->RememberMeTokens->find()->all(), 'not matching');

        $deleteCount = $this->RememberMeTokens->dropExpired('AuthUsers', 2);
        $this->assertSame(1, $deleteCount);
        $this->assertCount(5, $this->RememberMeTokens->find()->all());

        $deleteCount = $this->RememberMeTokens->dropExpired('AuthUsers');
        $this->assertSame(2, $deleteCount);
        $this->assertCount(3, $this->RememberMeTokens->find()->all());
    }
}
