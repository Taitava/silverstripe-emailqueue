<?php

namespace Taitava\SilverstripeEmailQueue;



use Exception;
use SilverStripe\Control\Director;
use SilverStripe\CronTask\Interfaces\CronTask;
use SilverStripe\Dev\BuildTask;




class EmailQueueProcessor extends BuildTask
{
	/**
	 * @conf int How many messages to send at a time (maximum).
	 */
	private static $max_email_messages = 50;
	
	public function getDescription()
	{
		return 'Sends queued emails. Must be called via command line, calling from browser is disabled.';
	}
	
	/**
	 * Implement this method in the task subclass to
	 * execute via the TaskRunner
	 *
	 * @throws Exception
	 */
	public function run($request)
	{
		if (!Director::is_cli()) throw new Exception(__METHOD__ . ': This task can only be ran via command line!');
		
		$nl = Director::is_cli() ? "\n" : '<br>';
		$email_messages = EmailQueue::SendableEmails(static::config()->get('max_email_messages'));
		if (!$email_messages->exists())
		{
			echo 'Nothing to do, the list of sendable messages is empty.';
			$later_scheduled_messages = EmailQueue::ScheduledEmails();
			if ($later_scheduled_messages->exists()) echo '(However, ' . $later_scheduled_messages->count() . ' messages have been scheduled to be sent at a later time).';
			echo $nl;
			return;
		}
		/** @var EmailQueue[] $email_messages_array */
		$email_messages_array = $email_messages->toArray(); //For some reason it's not possible to iterate a DataList instance twice, so convert it to an array.
		
		//Mark the messages as being processes to prevent subsequent processes from processing them again
		echo "Marking messages as 'in-progress'...$nl";
		foreach ($email_messages_array as $email_message)
		{
			echo 'Marking #' . $email_message->ID . '... ';
			$email_message->UpdateStatus('in-progress');
			echo "OK$nl";
		}
		
		//Send the messages
		echo "Sending messages...$nl";
		foreach ($email_messages_array as $email_message)
		{
			echo 'Sending #' . $email_message->ID . '... ';
			$this->extend('onBeforeSendingMessage', $email_message);
			$succeeded = $email_message->Send() ? 'Sent' : 'FAILED!';
			$this->extend('onAfterSendingMessage', $email_message);
			echo $succeeded . $nl;
		}
		
		echo "Done.$nl";
	}
	
	/**
	 * Returns true if the optional CronTask module is installed and EmailQueueProcessorCronTask class can be used.
	 * @return bool
	 */
	public static function CronTaskInstalled()
	{
		return interface_exists(CronTask::class);
	}
	
}