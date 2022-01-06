<?php

namespace Taitava\SilverstripeEmailQueue;

/**
 * If you pass custom objects to @link EmailQueue as recipients then they need to
 * implement this interface. 
 */
interface EmailAddressProvider
{
    /**
     * Generate a list of email addresses and names that is compatible with
     * swiftmailer. Each item in the array must be formatted as follows:
     *
     *      emailaddress@domain => Recipient Name (can be empty string)
     * 
     * @return array
     */
    public function getEmailAddresses(): array;
}