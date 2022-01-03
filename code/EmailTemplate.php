<?php

namespace Taitava\SilverstripeEmailQueue;

use Exception;
use RuntimeException;
use InvalidArgumentException;
use SilverStripe\Control\Email\Email;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Security\Member;
use SilverStripe\Control\Director;

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
		if ($this->is_internal_message)
		{
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
	 * @param null $messageID
	 * @param bool $queue Whether to put the message to a queue or send immediately. The latter is slower.
	 * @return bool|EmailQueue If queueing is enabled, returns an EmailQueue instance, otherwise returns just true or false depending on if the sending succeeded or not. Note that if the current EmailTemplate instance defines a sending schedule (see getSendingSchedule()), queuing is always forced and setting this parameter to false will have no effect.
	 * @throws Exception
	 */
	public function send($messageID = null, $queue = true)
	{
		if (empty($this->body)) $this->renderBody();
		if (!$this->To())
		{
			if (is_object($this->queue_recipient_member))
			{
				// Get recipient email address from a Member
				$this->setTo($this->queue_recipient_member);
			}
			else
			{
				// We do not have a recipient address nor a Member
				throw new Exception(__METHOD__ . ': No recipient defined in To field.');
			}
		}
		
		if ($this->isDevOrTest())
		{
			//If we are sending email on dev/test environment, ensure that mail is only sent to admin email addresses.
			$overriding_address = static::TestSiteOverridingAddress();
			if (!$this->test_site_is_email_whitelisted($this->To())) $this->setTo($overriding_address);
			if ($this->Cc() && !$this->test_site_is_email_whitelisted($this->Cc())) $this->setCc($overriding_address);
			if ($this->Bcc() && !$this->test_site_is_email_whitelisted($this->Bcc())) $this->setBcc($overriding_address);
		}
		
		if ($queue || $this->getSendingSchedule())
		{
			//Queue for sending later via EmailQueueProcessor cron task
			if (!is_object($this->queue_recipient_member)) throw new RuntimeException(__METHOD__ . ': Email queueing cannot be used if queue_recipient_member is not set.');
			return EmailQueue::AddToQueue($this, $this->queue_recipient_member, $this->getSendingSchedule());
		}
		else
		{
			//Send immediately
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
	public static function TestSiteOverridingAddress()
	{
		return static::config()->admin_email_to;
	}
	
	private function test_site_is_email_whitelisted($email_address)
	{
		$whitelist = preg_split('/(\r\n|\r|\n)/', strtolower(SiteConfig::current_site_config()->TestEmailAddressWhitelist));
		return in_array(strtolower($email_address), $whitelist);
	}
	
	/**
	 * @param $val
	 * @return Email
	 * @throws Exception
	 */
	public function setTo($address, $name = null)
	{
		$address = $this->resolve_email_address($address);

		return parent::setTo($address, $name);
	}
	
	public function addTo($address, $name = null)
	{
		$address = $this->resolve_email_address($address);

		if (empty($this->to)) {
			$this->to = $address;
		} else {
			$this->to .= ",$address";
		}

		return $this;
	}
	
	public function removeTo($address)
	{
		$this->to = str_ireplace($address, '', $this->to); //Do the actual email address removal
		$this->to = str_replace(',,', ',', $this->to); //Ensure that the removal does not leave two adjacent commas
		$this->to = preg_replace('/,$/', '', $this->to); //Ensure that removal does not leave a trailing comma. (This check may be not needed, but do it just in case).

		return $this;
	}
	
	public function forTemplate()
	{
		return (string) $this->renderWith(static::class, $this->rendering_variables);
	}
	
	/**
	 * Looks for a ClassName.ss template and renders its content to the body of this email message.
	 *
	 * This is automatically called during send(), but only if the current body is empty.
	 */
	public function renderBody()
	{
		$this->setBody($this->forTemplate());
	}
	
	/**
	 * Ensures that the given value is a string containing either one email address or multiple comma separated email addresses. Accepts either a simple string, an array of strings or an object implementing the EmailAddressProvider interface as a parameter.
	 *
	 * @param string|string[]|Member|EmailAddressProvider $email_address
	 * @return string
	 * @throws Exception
	 */
	private function resolve_email_address($email_address)
	{
		if (is_string($email_address)) return $email_address;
		if (is_array($email_address)) return $this->implode_email_addresses($email_address);
		if ($email_address instanceof Member) return $email_address->Email; //The Member class cannot be modified to implement the EmailAddressProvider interface, so exceptionally handle it here.
		if (!$email_address instanceof EmailAddressProvider)
		{
			throw new InvalidArgumentException(__METHOD__ . ': Parameter $email_address must either be a string or an instance of a class that implements the EmailAddressProvider interface.');
		}
		return $this->implode_email_addresses($email_address->getEmailAddresses());
	}
	
	private function implode_email_addresses($email_addresses)
	{
		return implode(',', $email_addresses);
	}
	
	/**
	 * @return array
	 */
	public function getRenderingVariables()
	{
		return $this->rendering_variables;
	}
	
	/**
	 * @param array $rendering_variables
	 */
	public function setRenderingVariables($rendering_variables)
	{
		$this->rendering_variables = $rendering_variables;
	}
	
	public function setRenderingVariable($variable, $value)
	{
		$this->rendering_variables[$variable] = $value;
	}
	
	/**
	 * @return Member
	 */
	public function getQueueRecipientMember()
	{
		return $this->queue_recipient_member;
	}
	
	/**
	 * @param Member $queue_recipient_member
	 */
	public function setQueueRecipientMember(Member $queue_recipient_member)
	{
		$this->queue_recipient_member = $queue_recipient_member;
	}
	
	private function isDevOrTest()
	{
		return Director::isDev() || Director::isTest();
	}
}
