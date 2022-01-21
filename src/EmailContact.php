<?php

namespace Taitava\SilverstripeEmailQueue;

use SilverStripe\ORM\DataObject;

/**
 * A standardised cached contact that can be attached
 * to an email
 */
class EmailContact extends DataObject
{
    private static $table_name = "EmailQueueContact";

    private static $db = [
        'Name' => 'Varchar',
        'Address' => 'Varchar'
    ];

    private static $casting = [
        'RFC5322' => 'Varchar'
    ];

    /**
     * Return this contact formated as RFC 5322
     *
     * @return string
     */
    public function getRFC5322(): string
    {
        if (empty($this->Name)) {
            return $this->Address;
        }

        return "{$this->Name} <{$this->Address}>";
    }

    /**
     * This table could contain a lot of redundand data so
     * instead we should try to assign messages to an
     * existing contact if possible.
     *
     * If no contact is found, maked a new one
     *
     * @param string $address Email Address
     * @param string $name    Optional contact's name
     *
     * @return EmailContact
     */
    public static function findOrMake(string $address, string $name = null): EmailContact
    {
        $contact = self::get()->find('Address', $address);
        $write = false;

        if (empty($contact)) {
            $contact = self::create(
                [
                'Address' => $address,
                'Name' => $name
                ]
            );
            $write = true;
        }

        if (!empty($contact) && !empty($name) && $contact->Name !== $name) {
            $contact->Name = $name;
            $write = true;
        }

        if ($write === true) {
            $contact->write();
        }

        return $contact;
    }
}
