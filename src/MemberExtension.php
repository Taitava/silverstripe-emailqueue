<?php

namespace Taitava\SilverstripeEmailQueue;

use SilverStripe\Security\Member;
use SilverStripe\ORM\DataExtension;

/**
 * Members cannot implement @link EmailAddressProvider
 * so manually add the method required
 */
class MemberExtension extends DataExtension
{
    public function getEmailAddresses(): array
    {
        /**
 * @var Member 
*/
        $owner = $this->getOwner();
        return [trim($owner->Email) => $owner->Name];
    }
}