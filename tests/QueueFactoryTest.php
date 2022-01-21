<?php

namespace Taitava\SilverstripeEmailQueue\Tests;

use SilverStripe\Dev\SapphireTest;
use Taitava\SilverstripeEmailQueue\EmailQueue;
use Taitava\SilverstripeEmailQueue\EmailContact;
use Taitava\SilverstripeEmailQueue\QueueFactory;
use Taitava\SilverstripeEmailQueue\EmailQueueContact;

class QueueFactoryTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        EmailQueue::class,
        EmailContact::class,
        EmailQueueContact::class
    ];

    public function testAddToQueueSimple()
    {
        $email = TestTemplate::create()
            ->setFrom('from@test.com')
            ->setTo('to@test.com')
            ->setSubject("A test email")
            ->setBody("Test Body");

        $factory = QueueFactory::create($email)->addToQueue();

        $this->assertEquals(1, EmailQueue::get()->count());
        $this->assertEquals(1, EmailQueue::QueuedEmails()->count());

        $queued = EmailQueue::QueuedEmails()->first();

        $this->assertEquals('from@test.com', $queued->From()->first()->Address);
        $this->assertEquals('', $queued->From()->first()->Name);
        $this->assertEquals('from@test.com', $queued->getFromString());
        $this->assertEquals('to@test.com', $queued->To()->first()->Address);
        $this->assertEquals('', $queued->To()->first()->Name);
        $this->assertEquals('to@test.com', $queued->getToString());
        $this->assertEquals('A test email', $queued->Subject);
        $this->assertEquals('Test Body', $queued->Body);

        $factory->getCurrQueueItem()->delete();
    }

    public function testAddToQueue()
    {
        $email = TestTemplate::create()
            ->setFrom(['from@test.com' => 'test sender'])
            ->setTo(['to@test.com' => 'test recipient'])
            ->setSubject("A test email")
            ->setBody("Test Body");

        $factory = QueueFactory::create($email)->addToQueue();

        $this->assertEquals(1, EmailQueue::get()->count());
        $this->assertEquals(1, EmailQueue::QueuedEmails()->count());

        $queued = EmailQueue::QueuedEmails()->first();

        $this->assertEquals('from@test.com', $queued->From()->first()->Address);
        $this->assertEquals('test sender', $queued->From()->first()->Name);
        $this->assertEquals('test sender <from@test.com>', $queued->getFromString());
        $this->assertEquals('to@test.com', $queued->To()->first()->Address);
        $this->assertEquals('test recipient', $queued->To()->first()->Name);
        $this->assertEquals('test recipient <to@test.com>', $queued->getToString());
        $this->assertEquals('A test email', $queued->Subject);
        $this->assertEquals('Test Body', $queued->Body);

        $factory->getCurrQueueItem()->delete();

        $email = TestTemplate::create()
            ->setFrom(['from2@test.com' => 'test sender two'])
            ->setTo(['to2@test.com' => 'test recipient two'])
            ->setCC(['cc@test.com' => 'test cc'])
            ->setBCC(['bcc@test.com' => 'test bcc'])
            ->setSubject("Another test email")
            ->setBody("Test Body Two");

        $factory = QueueFactory::create($email)->addToQueue();

        $this->assertEquals(1, EmailQueue::get()->count());
        $this->assertEquals(1, EmailQueue::QueuedEmails()->count());

        $queued = EmailQueue::QueuedEmails()->first();

        $this->assertEquals('from2@test.com', $queued->From()->first()->Address);
        $this->assertEquals('test sender two', $queued->From()->first()->Name);
        $this->assertEquals('test sender two <from2@test.com>', $queued->getFromString());
        $this->assertEquals('to2@test.com', $queued->To()->first()->Address);
        $this->assertEquals('test recipient two', $queued->To()->first()->Name);
        $this->assertEquals('test recipient two <to2@test.com>', $queued->getToString());
        $this->assertEquals('cc@test.com', $queued->CC()->first()->Address);
        $this->assertEquals('test cc', $queued->CC()->first()->Name);
        $this->assertEquals('test cc <cc@test.com>', $queued->getCCString());
        $this->assertEquals('bcc@test.com', $queued->BCC()->first()->Address);
        $this->assertEquals('test bcc', $queued->BCC()->first()->Name);
        $this->assertEquals('test bcc <bcc@test.com>', $queued->getBCCString());
        $this->assertEquals('Another test email', $queued->Subject);
        $this->assertEquals('Test Body Two', $queued->Body);

        $factory->getCurrQueueItem()->delete();
    }
}
