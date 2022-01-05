<?php

namespace Taitava\SilverstripeEmailQueue;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TextareaField;
use SilverStripe\ORM\DataExtension;

class EmailQueueSiteConfigExtension extends DataExtension
{
    private static $db = [
    'TestEmailAddressWhitelist' => 'Text',
    ];
    
    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldToTab('Root', new Tab(EmailQueue::class, _t('EmailQueueSiteConfigExtension.EmailQueueTab', 'Email Queue')));
        $fields->addFieldsToTab(
            'Root.EmailQueue', [
            $email_whitelist_field = new TextareaField('TestEmailAddressWhitelist', _t('EmailQueueSiteConfigExtension.TestEmailAddressWhitelist', 'Email addresses that are allowed on testing environments'), $this->owner->TestEmailAddressWhitelist),
            ]
        );
        $email_whitelist_field->setDescription(
            _t(
                'EmailQueueSiteConfigExtension.TestEmailAddressWhitelistDescription', 'On testing environments, email messages are not allowed to be sent to any other addresses than the ones mentioned above. Enter one email address per line. If an email is tried to be sent to an address not in this list, the address will be changed to {overriding_address}.', '', [
                'overriding_address' => EmailTemplate::TestSiteOverridingAddress(),
                ]
            )
        );
    }
    
}