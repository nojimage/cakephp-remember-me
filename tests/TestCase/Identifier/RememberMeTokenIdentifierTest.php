<?php
declare(strict_types=1);

namespace RememberMe\Test\TestCase\Identifier;

use Authentication\Identifier\Resolver\OrmResolver;
use Cake\I18n\FrozenTime;
use Cake\ORM\Entity;
use RememberMe\Identifier\RememberMeTokenIdentifier;
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
    public function tearDown(): void
    {
        FrozenTime::setTestNow();
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testIdentifyValid(): void
    {
        FrozenTime::setTestNow('2017-10-01 11:22:33');
        $resolver = $this->getMockBuilder(OrmResolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find'])
            ->getMock();

        $identifier = new RememberMeTokenIdentifier();
        $identifier->setResolver($resolver);

        $user = new Entity([
            'id' => 1,
            'username' => 'foo',
        ]);
        $user->setSource('AuthUsers');

        $resolver->expects($this->once())
            ->method('find')
            ->with([
                'username' => 'foo',
            ])
            ->willReturn($user);

        $result = $identifier->identify([
            'username' => 'foo',
            'series' => 'series_foo_1',
            'token' => 'logintoken1',
        ]);
        $this->assertSame($user, $result);
        $this->assertInstanceOf(RememberMeToken::class, $result['remember_me_token']);
    }

    /**
     * @return void
     */
    public function testIdentifyNotMatchSeries(): void
    {
        FrozenTime::setTestNow('2017-10-01 11:22:33');
        $resolver = $this->getMockBuilder(OrmResolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find'])
            ->getMock();

        $identifier = new RememberMeTokenIdentifier();
        $identifier->setResolver($resolver);

        $resolver->expects($this->once())
            ->method('find')
            ->with([
                'username' => 'foo',
            ])
            ->willReturn(null);

        $result = $identifier->identify([
            'username' => 'foo',
            'series' => 'invalid_series',
            'token' => 'logintoken1',
        ]);
        $this->assertNull($result);
    }

    /**
     * @return void
     */
    public function testIdentifyNotMatchToken(): void
    {
        FrozenTime::setTestNow('2017-10-01 11:22:33');
        $resolver = $this->getMockBuilder(OrmResolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find'])
            ->getMock();

        $identifier = new RememberMeTokenIdentifier();
        $identifier->setResolver($resolver);

        $resolver->expects($this->once())
            ->method('find')
            ->with([
                'username' => 'foo',
            ])
            ->willReturn(null);

        $result = $identifier->identify([
            'username' => 'foo',
            'series' => 'series_foo_1',
            'token' => 'invalid_token',
        ]);
        $this->assertNull($result);
    }

    /**
     * @return void
     */
    public function testIdentifyExpired(): void
    {
        FrozenTime::setTestNow('2017-10-01 11:22:34');
        $resolver = $this->getMockBuilder(OrmResolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find'])
            ->getMock();

        $identifier = new RememberMeTokenIdentifier();
        $identifier->setResolver($resolver);

        $user = new Entity([
            'id' => 1,
            'username' => 'foo',
        ]);
        $user->setSource('AuthUsers');

        $resolver->expects($this->once())
            ->method('find')
            ->with([
                'username' => 'foo',
            ])
            ->willReturn($user);

        $result = $identifier->identify([
            'username' => 'foo',
            'series' => 'series_foo_1',
            'token' => 'logintoken1',
        ]);
        $this->assertNull($result);
    }
}
