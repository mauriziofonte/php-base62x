<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Test\Unit\Laravel;

use Mfonte\Base62x\Base62x;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Mfonte\Base62x\Test\Unit\TestCase;
use Mfonte\Base62x\Laravel\Base62xFactory;
use Mfonte\Base62x\Exception\CryptException;
use Mfonte\Base62x\Laravel\Casts\Base62xCast;
use Mfonte\Base62x\Laravel\Rules\Base62xRule;
use Mfonte\Base62x\Laravel\Base62xServiceProvider;
use Mfonte\Base62x\Laravel\Facades\Base62x as Base62xFacade;

/**
 * @requires extension sodium
 */
class LaravelIntegrationTest extends TestCase
{
    private const KEY = 'base64:0123456789abcdefghijklmnopqrstuvwxyzABCDEFG=';

    /**
     * @var Container
     */
    private $app;

    protected function setUp(): void
    {
        $this->app = new Container();
        $this->app->instance('config', new Repository(['base62x' => ['key' => self::KEY]]));

        $provider = new Base62xServiceProvider($this->app); // @phpstan-ignore-line a bare container is enough for these tests
        $provider->register();
        $provider->boot();

        Facade::setFacadeApplication($this->app); // @phpstan-ignore-line the facade only needs ArrayAccess
        Facade::clearResolvedInstances();
    }

    protected function tearDown(): void
    {
        Base62xCast::$factoryResolver = null;
        Facade::clearResolvedInstances();
    }

    public function testFactoryIsBoundAndConfigured(): void
    {
        $factory = $this->app->make('base62x');
        self::assertInstanceOf(Base62xFactory::class, $factory);
        self::assertSame($factory, $this->app->make(Base62xFactory::class));

        $encrypted = $factory->encrypt(['foo' => 'bar']);
        self::assertTrue(Base62x::isValid($encrypted));
        self::assertSame(['foo' => 'bar'], $factory->decrypt($encrypted));

        // authenticated encryption with the configured key
        self::assertSame(['foo' => 'bar'], Base62x::decode($encrypted)->decrypt(self::KEY)->get());
    }

    public function testDefaultConfigurationIsMerged(): void
    {
        $config = $this->app->make('config');

        self::assertSame(Base62x::CIPHER_XCHACHA20_POLY1305, $config->get('base62x.cipher'));
        self::assertSame('gzip', $config->get('base62x.compression'));
    }

    public function testFacade(): void
    {
        $packed = Base62xFacade::pack('payload');

        self::assertSame('payload', Base62xFacade::unpack($packed));
        self::assertSame('payload', Base62xFacade::decode(Base62xFacade::encode('payload')->get())->get());
        self::assertTrue(Base62xFacade::isValid($packed));
    }

    public function testFactoryWithoutKey(): void
    {
        $this->expectException(CryptException::class);

        (new Base62xFactory(null))->encrypt('payload');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function castModeProvider(): iterable
    {
        yield 'plain' => ['plain'];
        yield 'compressed' => ['compressed'];
        yield 'encrypted' => ['encrypted'];
    }

    /**
     * @dataProvider castModeProvider
     */
    public function testCast(string $mode): void
    {
        $cast = new Base62xCast($mode);

        foreach (['a string', ['an' => 'array']] as $value) {
            $stored = $cast->set(null, 'attribute', $value, []);
            self::assertIsString($stored);
            self::assertTrue(Base62x::isValid($stored));
            self::assertSame($value, $cast->get(null, 'attribute', $stored, []));
        }

        self::assertNull($cast->set(null, 'attribute', null, []));
        self::assertNull($cast->get(null, 'attribute', null, []));
    }

    public function testEncryptedCastCannotBeReadWithoutTheKey(): void
    {
        $stored = (string) (new Base62xCast('encrypted'))->set(null, 'attribute', 'top secret', []);

        $this->expectException(CryptException::class);
        Base62x::decode($stored)->get();
    }

    public function testInvalidCastMode(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Base62xCast('whatever');
    }

    public function testRule(): void
    {
        $rule = new Base62xRule();

        self::assertTrue($rule->passes('token', Base62x::encode('foo')->get()));
        self::assertFalse($rule->passes('token', 'not valid!'));
        self::assertFalse($rule->passes('token', ['array']));
        self::assertFalse($rule->passes('token', null));
        self::assertStringContainsString(':attribute', $rule->message());
    }
}
