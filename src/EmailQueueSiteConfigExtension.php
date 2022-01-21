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
        $email_whitelist_field = TextareaField::create(
            'TestEmailAddressWhitelist',
            _t('EmailQueueSiteConfigExtension.TestEmailAddressWhitelist', 'Email addresses that are allowed on testing environments'),
            $this->owner->TestEmailAddressWhitelist
        )->setDescription(
            _t(
                'EmailQueueSiteConfigExtension.TestEmailAddressWhitelistDescription',
                'On testing environments, email messages are not allowed to be sent to any other addresses than the ones mentioned above. Enter one email address per line. If an email is tried to be sent to an address not in this list, the address will be changed to {overriding_address}.',
                '',
                ['overriding_address' => EmailTemplate::getTestSiteOverridingAddress()]
            )
        );

        $fields->addFieldToTab(
            'Root',
            Tab::create(
                'EmailQueue',
                _t('EmailQueueSiteConfigExtension.EmailQueueTab', 'Email Queue')
            )
        );
        $fields->addFieldToTab(
            'Root.EmailQueue',
            $email_whitelist_field
        );
    }
}
