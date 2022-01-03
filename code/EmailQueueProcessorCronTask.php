<?php

namespace Taitava\SilverstripeEmailQueue;

use SS_Object;




use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\CronTask\Interfaces\CronTask;



if (EmailQueueProcessor::CronTaskInstalled())
{
	// Support SilverStripe versions lower than 3.7:
	if (!class_exists('SS_Object')) class_alias('Object', 'SS_Object');

	use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Config\Configurable;
class EmailQueueProcessorCronTask implements CronTask
{
    use Extensible;
    use Injectable;
    use Configurable;
    /**
     * @conf int How often to run - in minutes.
     */
    private static $frequency = 10;
    /**
     * Return a string for a CRON expression
     *
     * @return string
     */
    public function getSchedule()
    {
        $frequency = static::config()->frequency;
        return "*/{$frequency} * * * *";
    }
    /**
     * When this script is supposed to run the CronTaskController will execute
     * process().
     *
     * @return void
     * @throws Exception
     */
    public function process()
    {
        /** @var EmailQueueProcessor $processor */
        $processor = Injector::inst()->create(EmailQueueProcessor::class);
        if (!$processor->isEnabled()) {
            $nl = Director::is_cli() ? "\n" : '<br>';
            echo "EmailQueueProcessor is disabled.{$nl}";
            return;
        }
        $processor->run(Controller::curr()->getRequest());
    }
}
}
