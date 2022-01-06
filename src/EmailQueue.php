<?php

namespace Taitava\SilverstripeEmailQueue;

use DateTime;
use LogicException;
use SilverStripe\Control\Email\Email;
use SilverStripe\ORM\Queries\SQLUpdate;
use SilverStripe\Security\Member;
use SilverStripe\ORM\DataObject;

/**
 * Class EmailQueue
 *
 * @property string $From
 * @property string $To
 * @property string $Subject
 * @property string $Body
 * @property string $Status Either 'queued', 'in-progress', 'sent' or 'failed'.
 * @property string $EmailClass A sub class name of EmailTemplate
 * @property string $UniqueString This can be used to check if a certain kind of email message is already sent.
 * @property string $SendingSchedule This is a date time value that can be used to delay sending.
 */
class EmailQueue extends DataObject
{
    private static $table_name = "EmailQueue";

    private static $singular_name = 'Sähköpostiviesti';

    private static $plural_name = 'Sähköpostiviestit';

    private static $db = [
        'FromJSON' => 'Text',
        'ToJSON' => 'Text',
        'From' => 'Text', // legacy
        'To' => 'Text', // legacy
        'Subject' => 'Text',
        'Body' => 'Text',
        'Status' => "Enum('queued,in-progress,sent,failed','queued')",
        'EmailClass' => 'Varchar(255)',
        'UniqueString' => 'Varchar(255)',
        'SendingSchedule' => 'Datetime',
    ];

    private static $email_field_map = [
        //EmailTemplate field    => EmailQueue field
        'getFrom()' => 'importFrom',
        'getTo()' => 'importTo',
        'getSubject()' => 'Subject',
        'getBody()' => 'Body',
    ];

    public function populateDefaults()
    {
        parent::populateDefaults();

        $this->SendingSchedule = (new DateTime)->getTimestamp(); // Current time

        return $this;
    }

    /**
     * Swiftmailer supports multiple senders as a list of key
     * value pairs (address as key and name as value) so convert
     * data to json
     *
     * @param mixed $data
     * 
     * @return self
     */
    public function importFrom($data): self
    {
        if (!is_string($data) && !is_array($data)) {
            throw new LogicException('Can only import strings or arrays');
        }

        $this->FromJSON = json_encode($data);

        return $this;
    }

    /**
     * Swiftmailer stores recipients as a list of key value
     * pairs (address as key and name as value) so convert
     * data to json
     *
     * @param mixed $data
     * 
     * @return self
     */
    public function importTo($data): self
    {
        if (!is_string($data) && !is_array($data)) {
            throw new LogicException('Can only import strings or arrays');
        }

        $this->ToJSON = json_encode($data);

        return $this;
    }

    public function getToArray(): array
    {
        return json_decode($this->ToJSON, true);
    }

    public function Send()
    {
        $email = Email::create()
            ->setFrom($this->From)
            ->setTo($this->getToArray())
            ->setSubject($this->Subject)
            ->setBody($this->Body);

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
     * @param  EmailTemplate $email_template
     * @param  Member        $recipient_member
     * @param  null|DateTime $sending_schedule
     * @return EmailQueue
     * @throws Exception
     */
    public static function AddToQueue(EmailTemplate $email_template, Member $recipient_member, $sending_schedule = null)
    {
        $me = static::singleton();

        // If any exceptions arise, try to make sure that we
        // are able to call extensions in the 'finally' part
        try {
            $me->extend(
                'onBeforeAddToQueue',
                $email_template,
                $recipient_member,
                $sending_schedule
            );

            // Create a new EmailQueue object which will store the email data
            $email_queue = new static;
            $email_queue->importDataFromEmailTemplate($email_template);

            // Send at a later time
            if ($sending_schedule) {
                $email_queue->SendingSchedule = $sending_schedule->getTimestamp();
            }

            $email_queue->write();
        } finally {
            $me->extend(
                'onAfterAddToQueue',
                $email_template,
                $recipient_member,
                $sending_schedule,
                $email_queue
            );
        }

        return $email_queue;
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

    private function importDataFromEmailTemplate(EmailTemplate $template)
    {
        $this->EmailClass = get_class($template);
        $fields = (array) static::config()->get('email_field_map');

        foreach ($fields as $template_field => $queue_field) {
            // Get value
            if (preg_match('/\(\)$/', $template_field)) {
                // The field is a method
                $method = preg_replace(
                    '/\(\)$/',
                    '',
                    $template_field
                );
                $field_value = $template->$method();
            } else {
                // The field is a simple property
                $field_value = $template->$template_field;
            }

            // Set value. If the current field resolves
            // to a method, then pass the value as an arg
            if ($this->hasMethod($queue_field)) {
                $this->$queue_field($field_value);
            } else {
                $this->$queue_field = $field_value;
            }
        }
    }
}
