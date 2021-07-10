<?php

namespace Clue\Tests\React\Block;

use PHPUnit\Framework\TestCase as BaseTestCase;
use React\Promise\Deferred;

class TestCase extends BaseTestCase
{
    protected $loop;

    /**
     * @before
     */
    public function setUpLoop()
    {
        $this->loop = \React\EventLoop\Factory::create();
    }

    /**
     * Skips a test if the test suite is running with react/promise version
     * 3.0 or later.
     * 
     * @param string $reason
     *
     * @return void
     */
    protected function skipForPromise3($reason)
    {
        if (! interface_exists('React\Promise\CancellablePromiseInterface')) {
            $this->markTestSkipped('Test is not supported/required by the Promise v3 API: '.$reason);   
        }
    }

    protected function createPromiseResolved($value = null, $delay = 0.01)
    {
        $deferred = new Deferred();

        $this->loop->addTimer($delay, function () use ($deferred, $value) {
            $deferred->resolve($value);
        });

        return $deferred->promise();
    }

    protected function createPromiseRejected($value = null, $delay = 0.01)
    {
        $deferred = new Deferred();

        $this->loop->addTimer($delay, function () use ($deferred, $value) {
            $deferred->reject($value);
        });

        return $deferred->promise();
    }

    protected function createTimerInterrupt($delay = 0.01)
    {
        $loop = $this->loop;
        $loop->addTimer($delay, function () use ($loop) {
            $loop->stop();
        });
    }

    public function setExpectedException($exception, $exceptionMessage = '', $exceptionCode = null)
    {
        if (method_exists($this, 'expectException')) {
            // PHPUnit 5+
            $this->expectException($exception);
            if ($exceptionMessage !== '') {
                $this->expectExceptionMessage($exceptionMessage);
            }
            if ($exceptionCode !== null) {
                $this->expectExceptionCode($exceptionCode);
            }
        } else {
            // legacy PHPUnit 4
            parent::setExpectedException($exception, $exceptionMessage, $exceptionCode);
        }
    }

    public function assertEqualsDelta($expected, $actual, $delta)
    {
        if (method_exists($this, 'assertEqualsWithDelta')) {
            // PHPUnit 7.5+
            $this->assertEqualsWithDelta($expected, $actual, $delta);
        } else {
            // legacy PHPUnit 4 - PHPUnit 7.5
            $this->assertEquals($expected, $actual, '', $delta);
        }
    }
}
