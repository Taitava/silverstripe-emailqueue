<?php

namespace Taitava\SilverstripeEmailQueue;

use DateTime;
use LogicException;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Control\Email\Email;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\ORM\ManyManyThroughList;
use SilverStripe\ORM\Queries\SQLUpdate;
use SilverStripe\ORM\SS_List;

/**
 * Class EmailQueue
 *
 * @property string $FromString
 * @property string $ToString
 * @property string $CCString
 * @property string $BCCString
 * @property string $Subject
 * @property string $Body
 * @property string $Status Either 'queued', 'in-progress', 'sent' or 'failed'.
 * @property string $EmailClass A sub class name of EmailTemplate
 * @property string $UniqueString This can be used to check if a certain kind of email message is already sent.
 * @property string $SendingSchedule This is a date time value that can be used to delay sending.
 *
 * @method ManyManyThroughList From
 * @method ManyManyThroughList To
 * @method ManyManyThroughList CC
 * @method ManyManyThroughList BCC
 */
class EmailQueue extends DataObject
{
    private static $table_name = "EmailQueue";

    private static $singular_name = 'Sähköpostiviesti';

    private static $plural_name = 'Sähköpostiviestit';

    private static $db = [
        'Subject'           => 'Text',
        'Body'              => 'Text',
        'Status'            => "Enum('queued,in-progress,sent,failed','queued')",
        'EmailClass'        => 'Varchar(255)',
        'UniqueString'      => 'Varchar(255)',
        'SendingSchedule'   => 'Datetime',
    ];

    private static $many_many = [
        'From'  => [
            'through' => EmailQueueContact::class,
            'from' => 'From',
            'to' => 'Contact'
        ],
        'To'    => [
            'through' => EmailQueueContact::class,
            'from' => 'To',
            'to' => 'Contact'
        ],
        'CC'    => [
            'through' => EmailQueueContact::class,
            'from' => 'CC',
            'to' => 'Contact'
        ],
        'BCC'   => [
            'through' => EmailQueueContact::class,
            'from' => 'BCC',
            'to' => 'Contact'
        ],
    ];

    private static $casting = [
        'FromString' => 'Varchar',
        'ToString' => 'Varchar',
        'CCString' => 'Varchar',
        'BCCString' => 'Varchar'
    ];

    private static $summary_fields = [
        'FromString',
        'ToString',
        'CCString',
        'BCCString',
        'Subject',
        'SendingSchedule'
    ];

    /**
     * Get a list of RFC addresses from the provided
     * list
     * 
     * @param ManyManyThroughList $list
     * 
     * @return string[]
     */
    protected function getRFCArray(ManyManyThroughList $list): array
    {
        $return = [];

        /** @var EmailQueueContact $contact */
        foreach ($list as $contact) {
            $return[] = $contact->getRFC5322();
        }

        return $return;
    }

    public function getFromString(): string
    {
        return implode(
            ',',
            $this->getRFCArray($this->From())
        );
    }

    public function getToString(): string
    {
        return implode(
            ',',
            $this->getRFCArray($this->To())
        );
    }

    public function getCCString(): string
    {
        return implode(
            ',',
            $this->getRFCArray($this->CC())
        );
    }

    public function getBCCString(): string
    {
        return implode(
            ',',
            $this->getRFCArray($this->BCC())
        );
    }

    public function getFromArray()
    {
        return $this
            ->From()
            ->map('Address', 'Name')
            ->toArray();
    }

    public function getToArray()
    {
        return $this
            ->To()
            ->map('Address', 'Name')
            ->toArray();
    }

    public function getCCArray()
    {
        return $this
            ->CC()
            ->map('Address', 'Name')
            ->toArray();
    }

    public function getBCCArray()
    {
        return $this
            ->BCC()
            ->map('Address', 'Name')
            ->toArray();
    }

    public function populateDefaults()
    {
        parent::populateDefaults();

        $this->SendingSchedule = (new DateTime)->getTimestamp(); // Current time

        return $this;
    }

    public function Send()
    {
        $email = Email::create()
            ->setFrom($this->getFromArray())
            ->setTo($this->getToArray())
            ->setSubject($this->Subject)
            ->setBody($this->Body);

        if ($this->CC()->exists()) {
            $email->setCC($this->getCCArray());
        }

        if ($this->BCC()->exists()) {
            $email->setBCC($this->getBCCArray());
        }

        // Call extensions and give them a chance to cancel
        // the sending
        $onBeforeSend_results = $this->extend('onBeforeSend', $email);
        $send = true;

        array_walk(
            $onBeforeSend_results,
            function ($result) use (&$send) {
                if (!$result) {
                    $send = false;
                }
            }
        );

        if (!$send) {
            return false;
        }

        // Send the email message
        $succeeded = (bool) $email->send();

        // Update status
        if ($succeeded) {
            $this->Status = 'sent';
            $this->write();
            $this->extend('onAfterSendingSucceeded', $email);
        } else {
            $this->Status = 'failed';
            $this->write();
            $this->extend('onAfterSendingFailed', $email);
        }

        $this->extend('onAfterSendingSucceededOrFailed', $email, $succeeded);
        return $succeeded;
    }

    /**
     * A fast way and simple way to change the value of the Status field. EmailQueueProcessor::run() uses this.
     *
     * @param string $status
     */
    public function UpdateStatus($status)
    {
        $update = new SQLUpdate(
            EmailQueue::class,
            ['Status' => $status],
            ['ID' => $this->ID]
        );
        $update->execute();
        $this->Status = $status;
        //TODO: Now SilverStripe thinks that the Status field is 'changed', i.e. has a new value which should be written to the database. This is not a big issue, I think it would most likely affect just a possible write() call, which would include the Status field unnecessarily. If this is wanted to be improved, one could find a way to mark the Status field as "not changed".
    }

    /**
     * @param  null $limit
     * @return DataList|SS_Limitable|EmailQueue[]
     */
    public static function QueuedEmails($limit = null)
    {
        $emails = static::get()->filter('Status', 'queued');

        if ($limit) {
            $emails = $emails->limit($limit);
        }

        return $emails;
    }

    /**
     * Returns a list of email messages that can be sent NOW.
     *
     * @param  null $limit
     * @return DataList
     * @throws Exception
     */
    public static function SendableEmails($limit = null)
    {
        $current_time = (new DateTime)->getTimestamp();
        return static::QueuedEmails($limit)
            ->filter('SendingSchedule:LessThanOrEqual', $current_time);
    }

    /**
     * Returns a list of email messages that SHOULD NOT be sent yet, because they are scheduled to be sent later.
     *
     * @param  null|int $limit
     * @return DataList
     * @throws Exception
     */
    public static function ScheduledEmails($limit = null)
    {
        $current_time = (new DateTime)->getTimestamp();
        return static::QueuedEmails($limit)
            ->filter('SendingSchedule:GreaterThan', $current_time);
    }

    /**
     * @param  null $limit
     * @return DataList|SS_Limitable|EmailQueue[]
     */
    public static function EmailsInProgress($limit = null)
    {
        $emails = static::get()->filter('Status', 'in-progress');

        if ($limit) {
            $emails = $emails->limit($limit);
        }

        return $emails;
    }

    /**
     * @param  null $limit
     * @return DataList|SS_Limitable|EmailQueue[]
     */
    public static function SentEmails($limit = null)
    {
        $emails = static::get()->filter('Status', 'sent');

        if ($limit) {
            $emails = $emails->limit($limit);
        }

        return $emails;
    }

    /**
     * @param  null $limit
     * @return DataList|SS_Limitable|EmailQueue[]
     */
    public static function FailedEmails($limit = null)
    {
        $emails = static::get()->filter('Status', 'failed');

        if ($limit) {
            $emails = $emails->limit($limit);
        }

        return $emails;
    }

    /**
     * Checks if the given $unique_string is found amongst previous email messages. This can be used to prevent re-sending
     * same emails over and over again.
     *
     * @param  $email_template_class_name
     * @param  $unique_string
     * @return bool
     */
    public static function CheckUniqueString($email_template_class_name, $unique_string)
    {
        $email_queue = self::byUniqueString($email_template_class_name, $unique_string);
        return is_object($email_queue) && $email_queue->exists();
    }

    /**
     * @param  string $email_template_class_name
     * @param  string $unique_string
     * @return EmailQueue
     */
    public static function byUniqueString($email_template_class_name, $unique_string)
    {
        return static::get()->filter([
            'EmailClass' => $email_template_class_name,
            'UniqueString' => $unique_string,
        ])->first();
    }

    /**
     * Handle the grunt work of importing a contacts array
     * into one of the email lists
     *
     * @param array $contacts List of contacts, formatted $address => $name
     * @param SS_List $list List of contacts to add array to
     * 
     * @return self
     */
    protected function importContactsToList(array $contacts, SS_List $list)
    {
        foreach ($contacts as $address => $name) {
            $contact = EmailContact::findOrMake($address, (string)$name);
            $list->add($contact);
        }

        return $this;
    }

    /**
     * Swiftmailer supports multiple senders as a list of key
     * value pairs ($address => $name) so convert data to list
     *
     * @param mixed $data Either string or array with format $address => $name
     * 
     * @return self
     */
    public function importFrom($data): self
    {
        if (!is_string($data) && !is_array($data)) {
            throw new LogicException('Can only import strings or arrays');
        }

        if (is_string($data)) {
            $data = [$data => ""];
        }

        $this->importContactsToList($data, $this->From());

        return $this;
    }

    /**
     * Swiftmailer stores recipients as a list of key value
     * pairs ($address => $name) so convert data to list
     *
     * @param mixed $data Either string or array with format $address => $name
     * 
     * @return self
     */
    public function importTo($data): self
    {
        if (!is_string($data) && !is_array($data)) {
            throw new LogicException('Can only import strings or arrays');
        }

        if (is_string($data)) {
            $data = [$data => ""];
        }

        $this->importContactsToList($data, $this->To());

        return $this;
    }

    /**
     * Swiftmailer stores recipients as a list of key value
     * pairs ($address => $name) so convert data to list
     *
     * @param mixed $data Either string or array with format $address => $name
     *
     * @return self
     */
    public function importCC($data): self
    {
        // If there is no valid data, return (as CC is optional)
        if (empty($data)) {
            return $this;
        }

        if (!is_string($data) && !is_array($data)) {
            throw new LogicException('Can only import strings or arrays');
        }

        if (is_string($data)) {
            $data = [$data => ""];
        }

        $this->importContactsToList($data, $this->CC());

        return $this;
    }

    /**
     * Swiftmailer stores recipients as a list of key value
     * pairs ($address => $name) so convert data to list
     *
     * @param mixed $data Either string or array with format $address => $name
     *
     * @return self
     */
    public function importBCC($data): self
    {
        // If there is no valid data, return (as BCC is optional)
        if (empty($data)) {
            return $this;
        }

        if (!is_string($data) && !is_array($data)) {
            throw new LogicException('Can only import strings or arrays');
        }

        if (is_string($data)) {
            $data = [$data => ""];
        }

        $this->importContactsToList($data, $this->BCC());

        return $this;
    }
}
