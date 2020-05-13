<?php

namespace Amp\Test;

use Amp\DisposedException;
use Amp\Loop;
use Amp\Promise;
use Amp\Stream;
use Amp\Success;
use PHPUnit\Framework\TestCase;

class Yielder implements Stream
{
    use \Amp\Internal\Yielder {
        stream as public;
    }
}

class YielderTraitTest extends TestCase
{
    /** @var Yielder */
    private $source;

    public function setUp()
    {
        $this->source = new Yielder;
    }

    public function testYield()
    {
        Loop::run(function () {
            $value = 'Yielded Value';

            $promise = $this->source->yield($value);
            $stream = $this->source->stream();

            $this->assertSame([$value, 0], yield $stream->continue());

            $this->assertInstanceOf(Promise::class, $promise);
            $this->assertNull(yield $promise);
        });
    }

    /**
     * @depends testYield
     */
    public function testYieldAfterComplete()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Streams cannot yield values after calling complete');

        $this->source->complete();
        $this->source->yield(1);
    }

    /**
     * @depends testYield
     */
    public function testYieldingNull()
    {
        Loop::run(function () {
            $this->source->yield(null);
            $this->assertSame([null, 0], yield $this->source->stream()->continue());
        });
    }

    /**
     * @depends testYield
     */
    public function testYieldingPromise()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Streams cannot yield promises');

        $this->source->yield(new Success);
    }

    public function testDoubleComplete()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Stream has already been completed');

        $this->source->complete();
        $this->source->complete();
    }

    public function testDoubleFail()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Stream has already been completed');

        $this->source->fail(new \Exception);
        $this->source->fail(new \Exception);
    }

    public function testDoubleStart()
    {
        $stream = $this->source->stream();

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('A stream may be started only once');

        $stream = $this->source->stream();
    }

    public function testYieldAfterContinue()
    {
        Loop::run(function () {
            $value = 'Yielded Value';

            $stream = $this->source->stream();

            $promise = $stream->continue();
            $this->assertInstanceOf(Promise::class, $promise);

            $this->assertNull(yield $this->source->yield($value));

            $this->assertSame([$value, 0], yield $promise);
        });
    }

    public function testContinueAfterComplete()
    {
        Loop::run(function () {
            $stream = $this->source->stream();

            $this->source->complete();

            $promise = $stream->continue();
            $this->assertInstanceOf(Promise::class, $promise);

            $this->assertNull(yield $promise);
        });
    }

    public function testContinueAfterFail()
    {
        Loop::run(function () {
            $stream = $this->source->stream();

            $this->source->fail(new \Exception('Stream failed'));

            $promise = $stream->continue();
            $this->assertInstanceOf(Promise::class, $promise);

            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('Stream failed');

            yield $promise;
        });
    }


    public function testCompleteAfterContinue()
    {
        Loop::run(function () {
            $stream = $this->source->stream();

            $promise = $stream->continue();
            $this->assertInstanceOf(Promise::class, $promise);

            $this->source->complete();

            $this->assertNull(yield $promise);
        });
    }

    public function testDestroyingStreamRelievesBackPressure()
    {
        $stream = $this->source->stream();

        $invoked = 0;
        $onResolved = function () use (&$invoked) {
            $invoked++;
        };

        foreach (\range(1, 5) as $value) {
            $promise = $this->source->yield($value);
            $promise->onResolve($onResolved);
        }

        $this->assertSame(0, $invoked);

        unset($stream); // Should relieve all back-pressure.

        $this->assertSame(5, $invoked);

        $this->source->complete(); // Should not throw.

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Stream has already been completed');

        $this->source->complete(); // Should throw.
    }

    public function testYieldAfterDisposal()
    {
        $this->expectException(DisposedException::class);
        $this->expectExceptionMessage('The stream has been disposed');

        Loop::run(function () {
            $stream = $this->source->stream();
            $promise = $this->source->yield(1);
            unset($stream);
            $this->assertNull(yield $promise);
            yield $this->source->yield(1);
        });
    }
}
