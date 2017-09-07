<?php

namespace RememberMe\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
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
    public function setUp()
    {
        parent::setUp();
        $this->RememberMeTokens = TableRegistry::get('RememberMeTokens', ['className' => RememberMeTokensTable::class]);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->RememberMeTokens);

        parent::tearDown();
    }

    /**
     * Test initialize method
     *
     * @return void
     */
    public function testInitialize()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test validationDefault method
     *
     * @return void
     */
    public function testValidationDefault()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     */
    public function testBuildRules()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
