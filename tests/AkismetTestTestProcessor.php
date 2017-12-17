<?php

namespace SilverStripe\Akismet\Tests;

use SilverStripe\Akismet\Config\AkismetProcessor;
use SilverStripe\Dev\TestOnly;

class AkismetTestTestProcessor extends AkismetProcessor implements TestOnly
{
    public function publicIsDBReady()
    {
        return $this->isDBReady();
    }
}
