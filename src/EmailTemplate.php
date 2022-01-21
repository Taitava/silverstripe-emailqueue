<?php

namespace Taitava\SilverstripeEmailQueue;

use DateTime;
use Exception;
use LogicException;
use RuntimeException;
use InvalidArgumentException;
use SilverStripe\Security\Member;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\SiteConfig\SiteConfig;

abstract class EmailTemplate extends Email
{
    /**
     * If true, the message will be sent to the admin email. No need to define a 'to' address.
     *
     * @var bool
     */
    protected $is_internal_message = false;
    
    protected $rendering_variables = [];
    
    /**
     * @var Member
     */
    private $queue_recipient_member = null;
    
    final public function __construct()
    {
        parent::__construct();
        
        //Automatically set from and to addresses
        $this->setFrom(Email::config()->admin_email);
        if ($this->is_internal_message) {
            $this->setTo(static::config()->admin_email_to);
        }
        
        //Let sub classes do their own setups:
        $this->init();
    }
    
    /**
     * Meant for subclasses of EmailTemplate to be able to define some settings. __construct() is too complicated by
     * it's signature and therefore it cannot be overloaded in sub classes. This method is called as part of the __construct()
     * call.
     */
    abstract protected function init();
    
    /**
     * Subclasses of EmailTemplate can use this to delay email sending by returning a Datetime instance. Returning null
     * sends immediately or if sending is queued, sends in just a few minutes.
     *
     * @return DateTime|null
     */
    protected function getSendingSchedule()
    {
        return null;
    }
    
    /**
     * @param  null $messageID
     * @param  bool $queue     Whether to put the message to a queue or send immediately. The latter is slower.
     * @return bool|EmailQueue If queueing is enabled, returns an EmailQueue instance, otherwise returns just true or false depending on if the sending succeeded or not. Note that if the current EmailTemplate instance defines a sending schedule (see getSendingSchedule()), queuing is always forced and setting this parameter to false will have no effect.
     * 
     * @throws LogicException
     * @throws RuntimeException
     */
    public function send($messageID = null, $queue = true)
    {
        if (empty($this->getBody())) {
            $this->renderBody();
        }

        if (count($this->getTo()) == 0) {
            if (is_object($this->queue_recipient_member)) {
                // Get recipient email address from a Member
                $this->setTo($this->queue_recipient_member);
            } else {
                // We do not have a recipient address nor a Member
                throw new Exception(__METHOD__ . ': No recipient defined in To field.');
            }
        }

        // If we are sending email on dev/test environment, ensure that mail is only
        // sent to admin or allowed email addresses.
        $to = $this->getTo();
        $to = $this->filterTestSiteRecipients($to);

        if ($this->isDevOrTest() && count($to) > 0) {
            $cc = $this->filterTestSiteRecipients($this->getCC());
            $bcc = $this->filterTestSiteRecipients($this->getBCC());

            $this->setTo($to);

            if (count($cc) > 0) {
                $this->setCC($cc);
            }

            if (count($bcc) > 0) {
                $this->setBCC($bcc);
            }
        }

        if ($queue || $this->getSendingSchedule()) {
            // Queue for sending later via EmailQueueProcessor cron task
            if (!is_object($this->getQueueRecipientMember())) {
                throw new RuntimeException(__METHOD__ . ': Email queueing cannot be used if queue_recipient_member is not set.');
            }
            $factory = QueueFactory::create($this);
            $schedule = $this->getSendingSchedule();

            if (!empty($schedule)) {
                $factory->setSendingSchedule($schedule);
            }

            $factory->addToQueue();
            return $factory->getCurrQueueItem();
        } else {
            // Send immediately
            return parent::send($messageID);
        }
    }

    /**
     * Returns the address that should be used in test site when overriding some none whitelisted email addresses.
     *
     * This is currently just a wrapper around static::config()->admin_email_to, but this method offers a place where to
     * alter the test site related address if needed.
     *
     * @return string
     */
    public static function getTestSiteOverridingAddress(): string
    {
        return (string) static::config()->admin_email_to;
    }

    /**
     * Screen the recipient(s) email addresses and return only
     * those supported?
     *
     * @param mixed $recipients
     *
     * @throws LogicException
     *
     * @return array
     */
    private function filterTestSiteRecipients(
        $recipients,
        bool $return_override = false
    ): array {
        if (!is_array($recipients)) {
            $recipients = [$recipients => ""];
        }

        $config = SiteConfig::current_site_config();
        $override = static::getTestSiteOverridingAddress();
        $approved = [];

        // Get a list of whitelist addresses
        $whitelist = array_filter(
            preg_split(
                '/(\r\n|\r|\n)/',
                strtolower($config->TestEmailAddressWhitelist)
            )
        );

        $i = 0;
        foreach ($recipients as $address => $name) {
            $address = trim(strtolower($address));

            if (!empty($address) && in_array($address, $whitelist)) {
                $approved[$address] = $name;
            }

            $i++;
        }

        if (count($approved) > 0) {
            return $approved;
        }

        if (empty($override) && $return_override) {
            throw new LogicException("No default admin address available");
        }

        if (empty($override)) {
            return [];
        }

        return [$override => ""];
    }

    /**
     * @param  $val
     * @return Email
     * @throws Exception
     */
    public function setTo($address, $name = null)
    {
        $address = $this->resolveEmailAddresses($address);
        return parent::setTo($address, $name);
    }
    
    public function addTo($address, $name = null)
    {
        $address = $this->resolveEmailAddresses($address);
        return parent::addTo($address, $name);
    }
    
    public function forTemplate(): string
    {
        return (string) $this->renderWith(static::class, $this->rendering_variables);
    }
    
    /**
     * Looks for a ClassName.ss template and renders its content to the body of this email message.
     *
     * This is automatically called during send(), but only if the current body is empty.
     *
     * @return EmailTemplate
     */
    public function renderBody(): EmailTemplate
    {
        $this->setBody($this->forTemplate());

        return $this;
    }
    
    /**
     * Ensures that the given addresses are strings
     * containing an email address that has no whitespace.
     * 
     * Accepts either an array of strings or an object
     * implementing the EmailAddressProvider interface
     * as a parameter.
     *
     * @param  string|string[]|Member|EmailAddressProvider $email_address
     * @return string
     * @throws Exception
     */
    private function resolveEmailAddresses($address): array
    {
        if (is_string($address)) {
            return [trim($address)];
        }

        if (is_array($address)) {
            return array_map('trim', $address);
        }

        $message = __METHOD__ . ": $address must implement EmailAddressProvider interface.";

        // Members cannot be extended to implement EmailAddressProvider
        // so an extension adds this method manually
        if ($address instanceof Member 
            || $address instanceof EmailAddressProvider
        ) {
            return $address->getEmailAddresses();
        }

        throw new InvalidArgumentException($message);
    }

    public function getRenderingVariables(): array
    {
        return $this->rendering_variables;
    }
    
    /**
     * @param array $rendering_variables
     */
    public function setRenderingVariables(array $variables): self
    {
        $this->rendering_variables = $variables;
        return $this;
    }
    
    public function setRenderingVariable(string $variable, mixed $value): self
    {
        $this->rendering_variables[$variable] = $value;
        return $this;
    }

    public function getQueueRecipientMember(): Member
    {
        return $this->queue_recipient_member;
    }

    public function setQueueRecipientMember(Member $member): self
    {
        $this->queue_recipient_member = $member;
        return $this;
    }
    
    private function isDevOrTest()
    {
        return Director::isDev() || Director::isTest();
    }
}
