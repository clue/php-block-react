<?php

namespace Clue\Tests\React\Block;

use React\Promise\Deferred;
use React\Promise;
use React\Promise\Timer\TimeoutException;
use Clue\React\Block;

class FunctionAwaitAnyTest extends TestCase
{
    public function testAwaitAnyEmpty()
    {
        $this->setExpectedException('UnderflowException');
        Block\awaitAny(array(), $this->loop);
    }

    public function testAwaitAnyFirstResolved()
    {
        $all = array(
            $this->createPromiseRejected(new \Exception('1')),
            $this->createPromiseResolved(2, 0.01),
            $this->createPromiseResolved(3, 0.02)
        );

        $this->assertEquals(2, Block\awaitAny($all, $this->loop));
    }

    public function testAwaitAnyFirstResolvedConcurrently()
    {
        $d1 = new Deferred();
        $d2 = new Deferred();
        $d3 = new Deferred();

        $this->loop->addTimer(0.01, function() use ($d1, $d2, $d3) {
            $d1->reject(new \Exception('1'));
            $d2->resolve(2);
            $d3->resolve(3);
        });

        $all = array(
            $d1->promise(),
            $d2->promise(),
            $d3->promise()
        );

        $this->assertEquals(2, Block\awaitAny($all, $this->loop));
    }

    public function testAwaitAnyAllRejected()
    {
        $all = array(
            $this->createPromiseRejected(new \Exception('1')),
            $this->createPromiseRejected(new \Exception('2'))
        );

        $this->setExpectedException('UnderflowException');
        Block\awaitAny($all, $this->loop);
    }

    public function testAwaitAnyInterrupted()
    {
        $promise = $this->createPromiseResolved(2, 0.02);
        $this->createTimerInterrupt(0.01);

        $this->assertEquals(2, Block\awaitAny(array($promise), $this->loop));
    }

    public function testAwaitAnyWithResolvedWillCancelPending()
    {
        $cancelled = false;
        $promise = new Promise\Promise(function () { }, function () use (&$cancelled) {
            $cancelled = true;
        });

        $all = array(
            Promise\resolve(2),
            $promise
        );

        $this->assertEquals(2, Block\awaitAny($all, $this->loop));
        $this->assertTrue($cancelled);
    }

    public function testAwaitAnyPendingWillThrowAndCallCancellerOnTimeout()
    {
        $cancelled = false;
        $promise = new Promise\Promise(function () { }, function () use (&$cancelled) {
            $cancelled = true;
        });

        try {
            Block\awaitAny(array($promise), $this->loop, 0.001);
        } catch (TimeoutException $expected) {
            $this->assertTrue($cancelled);
        }
    }

    /**
     * @requires PHP 7
     */
    public function testAwaitAnyPendingPromiseWithTimeoutAndCancellerShouldNotCreateAnyGarbageReferences()
    {
        if (class_exists('React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        gc_collect_cycles();

        $promise = new \React\Promise\Promise(function () { }, function () {
            throw new \RuntimeException();
        });
        try {
            Block\awaitAny(array($promise), $this->loop, 0.001);
        } catch (\Exception $e) {
            // no-op
        }
        unset($promise, $e);

        $this->assertEquals(0, gc_collect_cycles());
    }
}
