<?php

namespace Taitava\SilverstripeEmailQueue;

interface EmailAddressProvider
{
	/**
	 * @return string[]
	 */
	public function getEmailAddresses();
}