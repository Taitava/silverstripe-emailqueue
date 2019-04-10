<?php

if (class_exists('DashboardPanel'))
{
	
	/**
	 * Class DashboardEmailQueue
	 *
	 * @property int $NewestEmailsListCount
	 */
	class DashboardEmailQueue extends DashboardPanel
	{
		private static $db = [
			'NewestEmailsListCount' => 'Int',
		];
		
		public function getLabel()
		{
			return 'Email queue';
		}
		
		public function getConfiguration()
		{
			$fields = parent::getConfiguration();
			
			$fields->push($field = new NumericField('NewestEmailsListCount', 'Viestimäärä'));
			$field->setDescription("If 0, newest messages won't be shown.");
			
			return $fields;
		}
		
		public function QueuedEmails()
		{
			return EmailQueue::QueuedEmails();
		}
		
		public function SentEmails()
		{
			return EmailQueue::SentEmails();
		}
		
		public function FailedEmails()
		{
			return EmailQueue::FailedEmails();
		}
		
		public function EmailsInProgress()
		{
			return EmailQueue::EmailsInProgress();
		}
		
		public function AllEmails()
		{
			return EmailQueue::get();
		}
		
		public function NewestEmails()
		{
			if (!$this->NewestEmailsListCount) return new ArrayList; // Return an empty list because showing the newest emails is turned off.
			return $this->AllEmails()
				    ->limit($this->NewestEmailsListCount)
				    ->sort('LastEdited DESC');
		}
		
		private function EmailQueueProcessorConfig()
		{
			return EmailQueueProcessor::config();
		}
		
		private function EmailQueueProcessorCronTaskConfig()
		{
			if (!EmailQueueProcessor::CronTaskInstalled()) return null;
			return EmailQueueProcessorCronTask::config();
		}
		
		public function EmailQueueFrequency()
		{
			if (!EmailQueueProcessor::CronTaskInstalled()) return '?'; // The frequency in unknown if the CronTask module is not installed, because then we do not know how the coder has implemented calling the EmailQueueProcessor task.
			return $this->EmailQueueProcessorCronTaskConfig()->frequency;
		}
		
		public function EmailQueueMaxEmailMessages()
		{
			return $this->EmailQueueProcessorConfig()->max_email_messages;
		}
	}
}
