<?php

namespace SilverStripe\Akismet\Tests;

use SilverStripe\Akismet\AkismetSpamProtector;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;

class AkismetSpamProtectorTest extends SapphireTest
{
    public function testGetApiKeyPriority()
    {
        $spamProtector = AkismetSpamProtector::singleton();

        // Clear all possible values from the environment
        Config::modify()->set(AkismetSpamProtector::class, 'api_key', '');
        Environment::setEnv('SS_AKISMET_API_KEY', '');
        $this->assertSame('', $spamProtector->getApiKey(), 'Blank string returned by default');

        // Set some values in all possible places
        $spamProtector->setApiKey('instance_api_key');
        Config::modify()->set(AkismetSpamProtector::class, 'api_key', 'config_api_key');
        Environment::setEnv('SS_AKISMET_API_KEY', 'env_api_key');

        $this->assertSame('instance_api_key', $spamProtector->getApiKey(), 'Instance value is given priority');

        $spamProtector->setApiKey('');
        $this->assertSame('config_api_key', $spamProtector->getApiKey(), 'Config value is second priority');

        Config::modify()->set(AkismetSpamProtector::class, 'api_key', '');
        $this->assertSame('env_api_key', $spamProtector->getApiKey(), 'Environment value is last priority');
    }
}
