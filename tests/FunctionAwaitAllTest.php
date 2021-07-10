<?php

namespace Clue\Tests\React\Block;

use React\Promise;
use React\Promise\Timer\TimeoutException;
use Clue\React\Block;

class FunctionAwaitAllTest extends TestCase
{
    public function testAwaitAllEmpty()
    {
        $this->assertEquals(array(), Block\awaitAll(array(), $this->loop));
    }

    public function testAwaitAllAllResolved()
    {
        $all = array(
            'first' => $this->createPromiseResolved(1),
            'second' => $this->createPromiseResolved(2)
        );

        $this->assertEquals(array('first' => 1, 'second' => 2), Block\awaitAll($all, $this->loop));
    }

    public function testAwaitAllRejected()
    {
        $all = array(
            $this->createPromiseResolved(1),
            $this->createPromiseRejected(new \Exception('test'))
        );

        $this->setExpectedException('Exception', 'test');
        Block\awaitAll($all, $this->loop);
    }

    public function testAwaitAllRejectedWithFalseWillWrapInUnexpectedValueException()
    {
        $this->skipForPromise3('Promises must reject with an exception, so this case cannot happen.');

        $all = array(
            $this->createPromiseResolved(1),
            Promise\reject(false)
        );

        $this->setExpectedException('UnexpectedValueException');
        Block\awaitAll($all, $this->loop);
    }

    public function testAwaitAllOnlyRejected()
    {
        $all = array(
            $this->createPromiseRejected(new \Exception('first')),
            $this->createPromiseRejected(new \Exception('second'))
        );

        $this->setExpectedException('Exception', 'first');
        Block\awaitAll($all, $this->loop);
    }

    public function testAwaitAllInterrupted()
    {
        $promise = $this->createPromiseResolved(2, 0.02);
        $this->createTimerInterrupt(0.01);

        $this->assertEquals(array(2), Block\awaitAll(array($promise), $this->loop));
    }

    public function testAwaitAllWithRejectedWillCancelPending()
    {
        $cancelled = false;
        $promise = new Promise\Promise(function () { }, function () use (&$cancelled) {
            $cancelled = true;
        });

        $all = array(
            Promise\reject(new \Exception('test')),
            $promise
        );

        try {
            Block\awaitAll($all, $this->loop);
            $this->fail();
        } catch (\Exception $expected) {
            $this->assertEquals('test', $expected->getMessage());
            $this->assertTrue($cancelled);
        }
    }

    public function testAwaitAllPendingWillThrowAndCallCancellerOnTimeout()
    {
        $cancelled = false;
        $promise = new Promise\Promise(function () { }, function () use (&$cancelled) {
            $cancelled = true;
        });

        try {
            Block\awaitAll(array($promise), $this->loop, 0.001);
        } catch (TimeoutException $expected) {
            $this->assertTrue($cancelled);
        }
    }

    /**
     * @requires PHP 7
     */
    public function testAwaitAllPendingPromiseWithTimeoutAndCancellerShouldNotCreateAnyGarbageReferences()
    {
        if (class_exists('React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        gc_collect_cycles();
        gc_collect_cycles(); // clear twice to avoid leftovers in PHP 7.4 with ext-xdebug and code coverage turned on

        $promise = new \React\Promise\Promise(function () { }, function () {
            throw new \RuntimeException();
        });
        try {
            Block\awaitAll(array($promise), $this->loop, 0.001);
        } catch (\Exception $e) {
            // no-op
        }
        unset($promise, $e);

        $this->assertEquals(0, gc_collect_cycles());
    }
}
