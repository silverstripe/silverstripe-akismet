<?php

namespace SilverStripe\Akismet\Tests;

use Exception;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Akismet\Service\AkismetService;

class AkismetTestService implements TestOnly, AkismetService
{
    public function __construct($apiKey, $url)
    {
        if ($apiKey !== 'dummykey') {
            throw new Exception("Invalid key");
        }
    }
    
    public function isSpam($content, $author = null, $email = null, $url = null, $permalink = null, $type = null)
    {
        // This dummy service only checks the content
        return $content === 'spam';
    }
}
