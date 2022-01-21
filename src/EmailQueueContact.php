<?php

namespace Taitava\SilverstripeEmailQueue;

use SilverStripe\ORM\DataObject;

class EmailQueueContact extends DataObject
{
    private static $table_name = "EmailQueue_Contacts";

    private static $has_one = [
        'Contact'   => EmailContact::class,
        'From'      => EmailQueue::class,
        'To'        => EmailQueue::class,
        'CC'        => EmailQueue::class,
        'BCC'       => EmailQueue::class
    ];
}