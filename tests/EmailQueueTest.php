<?php

namespace Taitava\SilverstripeEmailQueue\Tests;

use SilverStripe\Dev\SapphireTest;
use Taitava\SilverstripeEmailQueue\EmailQueue;
use Taitava\SilverstripeEmailQueue\EmailContact;
use Taitava\SilverstripeEmailQueue\QueueFactory;
use Taitava\SilverstripeEmailQueue\EmailQueueContact;

class EmailQueueTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        EmailQueue::class,
        EmailContact::class,
        EmailQueueContact::class
    ];

    public function testAddToQueueBasic()
    {
        $email = TestTemplate::create()
            ->setFrom(['from@test.com' => 'test sender'])
            ->setTo(['to@test.com' => 'test recipient'])
            ->setSubject("A test email")
            ->setBody("Test Body");

        $factory = QueueFactory::create($email)->addToQueue();
        $queued = $factory->getCurrQueueItem();
        $sent = $queued->Send();

        $this->assertTrue($sent);
        $this->assertEmailSent("to@test.com", "from@test.com", "/te.*l$/");

        $queued->delete();
    }
}
