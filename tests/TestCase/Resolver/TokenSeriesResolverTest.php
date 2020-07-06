<?php

namespace RememberMe\Test\TestCase\Resolver;

use Cake\Datasource\EntityInterface;
use RememberMe\Model\Table\RememberMeTokensTable;
use RememberMe\Resolver\TokenSeriesResolver;
use RememberMe\Test\TestCase\RememberMeTestCase as TestCase;

/**
 * Class TokenSeriesResolverTest
 */
class TokenSeriesResolverTest extends TestCase
{
    /**
     * @var TokenSeriesResolver
     */
    private $resolver;

    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->resolver = new TokenSeriesResolver([
            'userModel' => 'AuthUsers',
        ]);
    }

    /**
     * @return void
     */
    public function tearDown()
    {
        unset($this->resolver);
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testInitializeConfig()
    {
        $this->assertSame('RememberMe.RememberMeTokens', $this->resolver->getConfig('tokenStorageModel'));
        $this->assertSame('remember_me_token', $this->resolver->getConfig('userTokenFieldName'));
    }

    /**
     * @return void
     */
    public function testFindExists()
    {
        $result = $this->resolver->find(['username' => 'foo', 'series' => 'series_foo_1']);

        $this->assertInstanceOf(EntityInterface::class, $result);
        $this->assertSame('foo', $result->username);
        $this->assertSame('series_foo_1', $result->remember_me_token->series);

        //
        $result = $this->resolver->find(['username' => 'foo', 'series' => 'series_foo_2']);

        $this->assertInstanceOf(EntityInterface::class, $result);
        $this->assertSame('foo', $result->username);
        $this->assertSame('series_foo_2', $result->remember_me_token->series);
    }

    /**
     * @return void
     */
    public function testFindNotExists()
    {
        $result = $this->resolver->find(['username' => 'foo', 'series' => 'invalid_series']);

        $this->assertNull($result);
    }

    /**
     * @return void
     */
    public function testGetUserTokenFieldName()
    {
        $this->assertSame('remember_me_token', $this->resolver->getUserTokenFieldName());
    }

    /**
     * @return void
     */
    public function testGetTokenStorage()
    {
        $this->assertInstanceOf(RememberMeTokensTable::class, $this->resolver->getTokenStorage());
    }
}
