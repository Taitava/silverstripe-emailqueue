<?php

namespace Taitava\SilverstripeEmailQueue\Tests;

use SilverStripe\Dev\TestOnly;
use Taitava\SilverstripeEmailQueue\EmailTemplate;

class TestTemplate extends EmailTemplate implements TestOnly
{
    public function init()
    {
        return;
    }

    public function renderBody(): EmailTemplate
    {
        $this->setBody($this->forTemplate());
        return $this;
    }

    public function forTemplate(): string
    {
        return "A Test Email";
    }
}
