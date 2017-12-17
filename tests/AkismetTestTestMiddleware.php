<?php

namespace SilverStripe\Akismet\Tests;

use SilverStripe\Akismet\Config\AkismetMiddleware;
use SilverStripe\Dev\TestOnly;

class AkismetTestTestMiddleware extends AkismetMiddleware implements TestOnly
{
    public function publicIsDBReady()
    {
        return $this->isDBReady();
    }
}
