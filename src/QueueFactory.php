<?php

namespace Taitava\SilverstripeEmailQueue;

use DateTime;
use LogicException;
use SilverStripe\Security\Member;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

class QueueFactory
{
    use Injectable, Configurable;

    /**
     * Class that is instantiated when an email is queued
     *
     * @var EmailQueue
     */
    private static $queue_class = EmailQueue::class;

    /**
     * Mapping of data to be imported from the template
     * class into the queue.
     *
     * @var array
     */
    private static $email_field_map = [
        //EmailTemplate field    => EmailQueue field
        'getFrom()' => 'importFrom',
        'getTo()' => 'importTo',
        'getCC()' => 'importCC',
        'getBCC()' => 'importBCC',
        'getSubject()' => 'Subject',
        'getBody()' => 'Body'
    ];

    /**
     * The email template that needs to be queued
     *
     * @var EmailTemplate
     */
    protected $template;

    /**
     * Optional - When is this email intended to be sent?
     *
     * @var DateTime
     */
    protected $sending_schedule;

    /**
     * Currently processed email queue item.
     * NOTE: This is set via @link addToQueue
     *
     * @var EmailQueue
     */
    protected $curr_queue_item;

    public function __construct(EmailTemplate $template)
    {
        $this->setTemplate($template);
    }

    /**
     * @return self
     * @throws LogicException
     */
    public function addToQueue() {
        $queue_class = Config::inst()->get(__CLASS__, 'queue_class');
        $sending_schedule = $this->getSendingSchedule();

        if (!is_a($queue_class, EmailQueue::class, true)) {
            throw new LogicException('queue_class must be an instance of EmailQueue');
        }

        // Create a new EmailQueue object which will store the email data
        $email_queue = $queue_class::create();
        $this->curr_queue_item = $email_queue;
        $this->importData();

        // Send at a later time
        if ($sending_schedule instanceof DateTime) {
            $this->curr_queue_item->SendingSchedule = $sending_schedule->getTimestamp();
        }

        $this->curr_queue_item->write();

        return $this;
    }

    protected function importData()
    {
        $template = $this->getTemplate();
        $queue_item = $this->getCurrQueueItem();
        $fields = (array)Config::inst()->get(__CLASS__, 'email_field_map');

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
            if ($queue_item->hasMethod($queue_field)) {
                $queue_item->$queue_field($field_value);
            } else {
                $queue_item->$queue_field = $field_value;
            }
        }
    }

    /**
     * Get the email template that needs to be queued
     *
     * @return  EmailTemplate
     */ 
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Set the email template that needs to be queued
     *
     * @param  EmailTemplate  $template
     *
     * @return  self
     */ 
    public function setTemplate(EmailTemplate $template)
    {
        $this->template = $template;
        return $this;
    }

    /**
     * Get optional - When is this email intended to be sent?
     *
     * @return DateTime
     */ 
    public function getSendingSchedule()
    {
        return $this->sending_schedule;
    }

    /**
     * Set optional - When is this email intended to be sent?
     *
     * @param DateTime $sending_schedule
     *
     * @return self
     */ 
    public function setSendingSchedule(DateTime $sending_schedule)
    {
        $this->sending_schedule = $sending_schedule;
        return $this;
    }

    /**
     * Get Current Email Queue Item
     *
     * @return  EmailQueue
     */ 
    public function getCurrQueueItem()
    {
        return $this->curr_queue_item;
    }
}