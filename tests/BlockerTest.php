<?php

use Clue\React\Block\Blocker;
use React\Promise\Deferred;

class BlockerTest extends TestCase
{
    const TIMEOUT_EXCEPTION_CLASS = 'Clue\React\Block\TimeoutException';

    private $loop;
    private $block;

    public function setUp()
    {
        $this->loop = React\EventLoop\Factory::create();
        $this->block = new Blocker($this->loop);
    }

    public function testWait()
    {
        $time = microtime(true);
        $this->block->wait(0.2);
        $time = microtime(true) - $time;

        $this->assertEquals(0.2, $time, '', 0.1);
    }

    public function testAwaitOneRejected()
    {
        $promise = $this->createPromiseRejected(new Exception('test'));

        $this->setExpectedException('Exception', 'test');
        $this->block->awaitOne($promise);
    }

    public function testAwaitOneResolved()
    {
        $promise = $this->createPromiseResolved(2);

        $this->assertEquals(2, $this->block->awaitOne($promise));
    }

    public function testAwaitOneInterrupted()
    {
        $promise = $this->createPromiseResolved(2, 0.02);
        $this->createTimerInterrupt(0.01);

        $this->assertEquals(2, $this->block->awaitOne($promise));
    }

    public function testAwaitOneTimedOut()
    {
        $promise = $this->createPromiseResolved(2, 0.02);

        $this->setExpectedException(self::TIMEOUT_EXCEPTION_CLASS);
        $this->block->awaitOne($promise, 0.01);
    }

    public function testAwaitOneTimeoutCleanedUp()
    {
        $promise = $this->createPromiseResolved(1, 0.01);
        $this->assertEquals(1, $this->block->awaitOne($promise, 0.02));

        $loop = $this->loop;
        $timerInvoked = false;
        $loop->addTimer(0.02, function () use (&$timerInvoked, $loop) {
            $timerInvoked = true;
            $loop->stop();
        });

        $loop->run();
        $this->assertTrue($timerInvoked);
    }

    /**
     * @expectedException UnderflowException
     */
    public function testAwaitRaceEmpty()
    {
        $this->block->awaitRace(array());
    }

    public function testAwaitRaceFirstResolved()
    {
        $all = array(
            $this->createPromiseRejected(1),
            $this->createPromiseResolved(2, 0.01),
            $this->createPromiseResolved(3, 0.02)
        );

        $this->assertEquals(2, $this->block->awaitRace($all));
    }

    public function testAwaitRaceFirstResolvedConcurrently()
    {
        $d1 = new Deferred();
        $d2 = new Deferred();
        $d3 = new Deferred();

        $this->loop->addTimer(0.01, function() use ($d1, $d2, $d3) {
            $d1->reject(1);
            $d2->resolve(2);
            $d3->resolve(3);
        });

        $all = array(
            $d1->promise(),
            $d2->promise(),
            $d3->promise()
        );

        $this->assertEquals(2, $this->block->awaitRace($all));
    }

    public function testAwaitRaceAllRejected()
    {
        $all = array(
            $this->createPromiseRejected(1),
            $this->createPromiseRejected(2)
        );

        $this->setExpectedException('UnderflowException');
        $this->block->awaitRace($all);
    }

    public function testAwaitRaceInterrupted()
    {
        $promise = $this->createPromiseResolved(2, 0.02);
        $this->createTimerInterrupt(0.01);

        $this->assertEquals(2, $this->block->awaitRace(array($promise)));
    }

    public function testAwaitRaceOneTimedOut()
    {
        $all = array(
            $this->createPromiseResolved(1, 0.03),
            $this->createPromiseResolved(2, 0.01),
            $this->createPromiseResolved(3, 0.03),
        );

        $this->assertEquals(2, $this->block->awaitRace($all, 0.2));
    }

    public function testAwaitRaceAllTimedOut()
    {
        $all = array(
            $this->createPromiseResolved(1, 0.03),
            $this->createPromiseResolved(2, 0.02),
            $this->createPromiseResolved(3, 0.03),
        );

        $this->setExpectedException(self::TIMEOUT_EXCEPTION_CLASS);
        $this->block->awaitRace($all, 0.01);
    }

    public function testAwaitRaceTimeoutCleanedUp()
    {
        $promise = $this->createPromiseResolved(1, 0.01);
        $this->assertEquals(1, $this->block->awaitRace(array($promise), 0.02));

        $loop = $this->loop;
        $timerInvoked = false;
        $loop->addTimer(0.02, function () use (&$timerInvoked, $loop) {
            $timerInvoked = true;
            $loop->stop();
        });

        $loop->run();
        $this->assertTrue($timerInvoked);
    }

    public function testAwaitAllEmpty()
    {
        $this->assertEquals(array(), $this->block->awaitAll(array()));
    }

    public function testAwaitAllAllResolved()
    {
        $all = array(
            'first' => $this->createPromiseResolved(1),
            'second' => $this->createPromiseResolved(2)
        );

        $this->assertEquals(array('first' => 1, 'second' => 2), $this->block->awaitAll($all));
    }

    public function testAwaitAllRejected()
    {
        $all = array(
            $this->createPromiseResolved(1),
            $this->createPromiseRejected(new Exception('test'))
        );

        $this->setExpectedException('Exception', 'test');
        $this->block->awaitAll($all);
    }

    public function testAwaitAllOnlyRejected()
    {
        $all = array(
            $this->createPromiseRejected(new Exception('first')),
            $this->createPromiseRejected(new Exception('second'))
        );

        $this->setExpectedException('Exception', 'first');
        $this->block->awaitAll($all);
    }

    public function testAwaitAllInterrupted()
    {
        $promise = $this->createPromiseResolved(2, 0.02);
        $this->createTimerInterrupt(0.01);

        $this->assertEquals(array(2), $this->block->awaitAll(array($promise)));
    }

    public function testAwaitAllOneTimedOut()
    {
        $all = array(
            $this->createPromiseResolved(1, 0.01),
            $this->createPromiseResolved(2, 0.03),
            $this->createPromiseResolved(3, 0.01),
        );

        $this->setExpectedException(self::TIMEOUT_EXCEPTION_CLASS);
        $this->block->awaitAll($all, 0.02);
    }

    public function testAwaitAllTimeoutCleanedUp()
    {
        $promise = $this->createPromiseResolved(1, 0.01);
        $this->assertEquals(array(1), $this->block->awaitAll(array($promise), 0.02));

        $loop = $this->loop;
        $timerInvoked = false;
        $loop->addTimer(0.02, function () use (&$timerInvoked, $loop) {
            $timerInvoked = true;
            $loop->stop();
        });

        $loop->run();
        $this->assertTrue($timerInvoked);
    }

    private function createPromiseResolved($value = null, $delay = 0.01)
    {
        $deferred = new Deferred();

        $this->loop->addTimer($delay, function () use ($deferred, $value) {
            $deferred->resolve($value);
        });

        return $deferred->promise();
    }

    private function createPromiseRejected($value = null, $delay = 0.01)
    {
        $deferred = new Deferred();

        $this->loop->addTimer($delay, function () use ($deferred, $value) {
            $deferred->reject($value);
        });

        return $deferred->promise();
    }

    private function createTimerInterrupt($delay = 0.01)
    {
        $loop = $this->loop;
        $loop->addTimer($delay, function () use ($loop) {
            $loop->stop();
        });
    }
}
