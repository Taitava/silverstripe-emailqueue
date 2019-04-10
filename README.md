silverstripe-emailqueue
=======================

This is a module that allows you to send emails in a background process, check previously sent emails, and monitor and cancel queued and scheduled emails. Also contains an an email templating structure that makes your email related code easier to manage via custom classes and .ss template files. Note that this module stores the email messages in a database.


## Maintainer Contact

 Jarkko Linnanvirta
 posti (at) taitavasti (dot) fi (in English or in Finnish)
 www.taitavasti.fi (only in Finnish)

## Requirements

- SilverStripe 3.1.0 or greater
- Optional: [silverstripe/crontask](https://github.com/silverstripe/silverstripe-crontask) is highly recommended, but not mandatory. If this is installed, the email queue is processed automatically (= emails are sent). Without this, you will need to run the `EmailQueueProcessor` task from the command line your self, or define a cron job that does it.
- Optional: [unclecheese/silverstripe-dashboard](https://github.com/unclecheese/silverstripe-dashboard) is recommended so that you can have an overview on what has been sent and what is currently queued for sending in the admin panel.

## Installation

`composer require taitava/silverstripe-emailqueue`

## Documentation

**An important thing to notice** is that this module does not change the way the default SilverStripe email messages are sent (for example the "forgot password" email message). Also email messages sent by the UserForms module is not affected by this module. Perhaps in the future, who knows. The idea is that you can use this module for your own, custom email messages.

So if you are sending email messages using this normal method:
```php
$email = new Email;
$email->setTo('the.recipient@domain.tld');
$email->setFrom('the.sender@domain.tld');
$email->setSubject('My subject');
$email->setBody($this->renderWith('MyEmailMessageTemplate.ss'));
$email->send();
```
Every time you use this method to send email messages, it will use the regular SilverStripe's process of sending emails. So nothing changes when you install this module.

When you want to send emails using this module and queueing, you will need to alter your code like this. Let's first define a new class for our email message:

```php
class MyEmailMessage extends EmailTemplate
{
        protected $is_internal_message = false; // Set this to true if you want to automatically set the 'To' address to a preconfigured admin receiver address.
        protected function init()
        {
                $this->setSubject('My subject');
        }
}
```

And a template file `MyEmailMessage.ss`:
```silverstripe
<h1>$Subject</h1>
Hi $YourName, this is just me sending a message to you.
```

Then let's instantiate the message:
```php

$email = new MyEmailMessage;
$recipient_member = Member::currentUser();
$email->setRenderingVariables([
	'YourName' => $recipient_member->getName(),
]);
$email->setTo($recipient_member); // Note that now we can use a Member object as the recipient. See below for more info. Normal email addresses are accepted too!
$email->send();
```

You can configure a default sender in YAML config:
```yaml
Email:
  admin_email: 'the.sender@domain.tld' # This is SilverStripe's default 'From' address, and QueuedEmails uses the same setting too
QueuedEmail:
  admin_to_address: 'the.recipient@domain.tld' # This is used as a 'To' address if an EmailTemplate subclass has defined $is_internal_message as true.
```

And as you can see, we have passed a `Member` object to the `setTo()` method instead of a regular email address. With this module, you can always use whatever of the following as an email address:
- A regular string like 'me@somewhere.tld'.
- An object of the 'Member' class (email address will be taken from the 'Email' field, no other fields can be configured).
- An object of any class that implements the `EmailAddressProvider` interface. In this case, the class should contain a method called `getEmailAddresses()` which returns an array of email addresses (so you can send to multiple recipients if you wish).

As you may have already guessed, the `EmailTemplate` class extends the SilverStripe's `Email` class, so the same methods are available in `EmailTemplate` class too. When the queued messages are sent, the `Email` class is also used to handle the actual sending part, so we are not reinventing a wheel here.

### Opt out from queueing?

Calling `$email->send()` will not actually send the message. It will queue it. If you wish to make an exception and send it immediately, you can call `$email->send($message_id=null, $queue=false)`, and it will skip the queueing completely and send immediately. Note that in this case the email message is not even stored to the database at all, so you cannot monitor sent messages.

### So when will my queued emails be sent?

Who knows if they will ever be sent.

Just joking. If you are already using the [CronTask module](https://github.com/silverstripe/silverstripe-crontask), then there's actually nothing you need to do to send you messages. If not, it takes a little preparation. Install the module and then define a cron job in your server environment like so in the commandline:

`hostname:~$ nano /etc/cron.d/silverstripe`

`* * * * * www-data php /var/www/my-silverstripe-project/framework/cli-script.php dev/cron`

Then you are good to go. If you need more information about setting up cron tasks, please refer to the link above.

And to actually answer the question, you can configure the interval how often the queue is processed (= emails sent):

```yaml
EmailQueueProcessorCronTask:
  frequency: 10 # In minutes. This is the default value
EmailQueueProcessor:
  max_email_messages: 50 # How many messages to send at once.
```

### Where to see the queued and sent emails?

The only option for now is to use the [Dashboard module](https://github.com/unclecheese/silverstripe-dashboard). Just install it and then you can create a new dashboard panel of the class `DashboardEmailQueue` in your web browser. This is not the best solution but at least it provides quick glance on whether the email sendings are working correctly or if the queue is piling up. (If it's piling up, please, **please** don't try to create an automated email message that will alert you that sending email does not work! :D ).

### Scheduling emails to be sent later (and cancelling the sending)

You can schedule particular email messages to be sent at a later time if you wish. Do this in your `EmailTemplate` subclass:

```php
class MyEmailMessage extends EmailTemplate
{

        /*
         * @return DateTime|null
         */
        protected function getSendingSchedule()
        {
        	$datetime = new DateTime;
        	$datetime->modify('+3 hours'); // Send after three hours
            return $datetime;
            // Or send immediately:
            // return null;
        }
}
```

Note that the sending time is not minutely accurate. This is because the email queue is processed every ten minutes (by default) and there can be a lot of messages to process, so you can't know whether your message will be sent at 12:00, 12:01, or 12:02 etc... The time that you define declares **an earliest** time when the message can be sent. In theory, your message might get sent a year later than when the scheduled time was.

You are even able to *cancel sending scheduled emails*. If you are for example sending a notifying email message about a newly published blog post, but you end up to unpublish it for whatever reason, you can call `EmailQueue::ScheduledEmails()` in your code to get a `DataList` of emails waiting for their sending time to come, and then delete all those records so that they will never be sent.

### Have I already sent a similar message?

When sending automated messages there can be cases where you might end up creating the same message to the same recipient over and over again. This is not an issue in simple cases such as *"a user clicks on a button in a form -> send the form to an email address"*. But it might be an issue for example if email messages are created by a cron task that runs every day: *"Get a list of members who haven't visited this application in 12 months and send them an email that we miss them."*

If you just take a list of members whose last logged in date is a year ago (or longer), you will end up sending them a new message every day. One solution could be to only include members who have logged in exactly 365 days ago (within a 24h interval), but that can cause other problems if for example your server is down due to maintenance for one day and that cron task is not ran during that day. Or you are not sure whether you already ran it today and would like to rerun it just in case. This is when you would like to know if you have sent a similar message already or not.

And this is how you can do it very easily:

```php
// Some data that will be used in the email message body
$some_data_object_id = 123;
$some_other_variable = 'ABC';

// Create a unique "key" that can be used to identify our message
$recipient_member = Member::currentUser();
$unique_string = $recipient_member->ID.'-'.$some_data_object_id.'-'.$some_other_variable;

// Have we already sent a message with the same key?
if (EmailQueue::CheckUniqueString(MyEmailMessage::class, $unique_string))
{
	// Already sent!!!
}
else
{
	// Not sent yet, so let's send it!
    $email = new MyEmailMessage;
    $email->setTo($recipient_member);
    $email->setRenderingVariables([
    	'some_data_object_id' => $some_data_object_id,
        'some_other_variable' => $some_other_variable,
    ]);
    /** @var EmailQueue $email_queue_record */
    $email_queue_record = $email->send();

    // Remember to write the key to the database!
    $email_queue_record->UniqueString = $unique_string;
    $email_queue_record->write();
}
```

### A note about development and testing environments

This module has a protection mechanism to not to send email messages accidentally to unintended addresses in development/testing phases (for example to client email addresses, which might be defined in your application's database).

You will need to visit your website's/application's SiteConfig section ("Settings" in the admin panel) and go to the "EmailQueue" tab. There you can see a field where you can define a list of allowed recipients. This list is only used in development/testing environments.

This is not the best possible solution because one might want to define this list in YAML config instead of in SiteConfig, but that's the way it is for now and this can be improved later.

## TODO

- Upgrade the code to support SilverStripe 4.
- Create a user interface for managing/deleting all email messages stored in the database. Currently only viewing them is possible (if you have the dashboard module installed).
- Perhaps add support for the [SilverStripe Queued Jobs module](https://github.com/symbiote/silverstripe-queuedjobs)? This could be an alternative for the CronTask module which is currently supported.
- YAML support for dev/test email whitelisting (see the section above). Also make it possible to turn this feature off.

