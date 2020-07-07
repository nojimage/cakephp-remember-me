<?php

namespace RememberMe\Test\TestCase\Identifier;

use ArrayObject;
use Cake\I18n\FrozenTime;
use RememberMe\Identifier\RememberMeTokenIdentifier;
use RememberMe\Identifier\Resolver\TokenSeriesResolverInterface;
use RememberMe\Model\Entity\RememberMeToken;
use RememberMe\Test\TestCase\RememberMeTestCase as TestCase;

/**
 * Class RememberMeTokenIdentifierTest
 */
class RememberMeTokenIdentifierTest extends TestCase
{
    /**
     * @return void
     */
    public function tearDown()
    {
        FrozenTime::setTestNow();
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testIdentifyValid()
    {
        FrozenTime::setTestNow(FrozenTime::now());
        $resolver = $this->createMock(TokenSeriesResolverInterface::class);
        $resolver->method('getUserTokenFieldName')->willReturn('remember_me_token');

        $identifier = new RememberMeTokenIdentifier();
        $identifier->setResolver($resolver);

        $rememberMeToken = new RememberMeToken([
            'token' => 'logintoken',
            'expires' => FrozenTime::now(),
        ]);
        $user = new ArrayObject([
            'username' => 'foo',
            'remember_me_token' => $rememberMeToken,
        ]);

        $resolver->expects($this->once())
            ->method('find')
            ->with([
                'username' => 'foo',
                'series' => 'series_foo_1',
            ])
            ->willReturn($user);

        $result = $identifier->identify([
            'username' => 'foo',
            'series' => 'series_foo_1',
            'token' => 'logintoken',
        ]);
        $this->assertSame($user, $result);
    }

    /**
     * @return void
     */
    public function testIdentifyNotMatchSeries()
    {
        FrozenTime::setTestNow(FrozenTime::now());
        $resolver = $this->createMock(TokenSeriesResolverInterface::class);

        $identifier = new RememberMeTokenIdentifier();
        $identifier->setResolver($resolver);

        $resolver->expects($this->once())
            ->method('find')
            ->with([
                'username' => 'foo',
                'series' => 'series_foo_1',
            ])
            ->willReturn(null);

        $result = $identifier->identify([
            'username' => 'foo',
            'series' => 'series_foo_1',
            'token' => 'logintoken',
        ]);
        $this->assertNull($result);
    }
}
