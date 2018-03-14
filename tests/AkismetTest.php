<?php

namespace SilverStripe\Akismet\Tests;

use SilverStripe\Akismet\AkismetSpamProtector;
use SilverStripe\Akismet\Config\AkismetConfig;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\Config;
use SilverStripe\Akismet\Service\AkismetService;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\ORM\DB;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\Connect\DatabaseException;
use SilverStripe\ORM\DataObject;

class AkismetTest extends FunctionalTest
{
    protected static $extra_dataobjects = [AkismetTestSubmission::class];

    protected $usesDatabase = true;

    protected static $required_extensions = [
        SiteConfig::class => [
            AkismetConfig::class,
        ],
    ];

    protected static $extra_controllers = [
        AkismetTestController::class,
    ];

    protected function setUp()
    {
        parent::setUp();
        Injector::inst()->unregisterObjects(AkismetService::class);

        // Mock service
        Config::modify()->set(Injector::class, AkismetService::class, AkismetTestService::class);
        Config::modify()->set(AkismetSpamProtector::class, 'api_key', 'dummykey');

        // Reset options to reasonable default
        Config::modify()->remove(AkismetSpamProtector::class, 'save_spam');
        Config::modify()->remove(AkismetSpamProtector::class, 'require_confirmation');
        Config::modify()->remove(AkismetSpamProtector::class, 'bypass_members');
        Config::modify()->set(AkismetSpamProtector::class, 'bypass_permission', 'ADMIN');
    }

    public function testSpamDetectionForm()
    {
        // Test "nice" setting
        $result = $this->post('AkismetTestController/Form', array(
            'Name' => 'person',
            'Email' => 'person@domain.com',
            'Content' => 'what a nice comment',
            'action_doSubmit' => 'Submit',
        ));

        $this->assertContains('Thanks for your submission, person', $result->getBody());
        $saved = AkismetTestSubmission::get()->last();
        $this->assertNotEmpty($saved);
        $this->assertEquals('person', $saved->Name);
        $this->assertEquals('person@domain.com', $saved->Email);
        $this->assertEquals('what a nice comment', $saved->Content);
        $this->assertEquals(false, (bool)$saved->IsSpam);
        $saved->delete();

        // Test failed setting
        $result = $this->post('AkismetTestController/Form', array(
            'Name' => 'spam',
            'Email' => 'spam@spam.com',
            'Content' => 'spam',
            'action_doSubmit' => 'Submit',
        ));

        $errorMessage = _t(
            'SilverStripe\\Akismet\\AkismetField.SPAM',
            "Your submission has been rejected because it was treated as spam."
        );
        $this->assertContains($errorMessage, $result->getBody());
        $saved = AkismetTestSubmission::get()->last();
        $this->assertEmpty($saved);
    }

    public function testSaveSpam()
    {
        Config::modify()->set(AkismetSpamProtector::class, 'save_spam', true);

        // Test "nice" setting
        $result = $this->post('AkismetTestController/Form', array(
            'Name' => 'person',
            'Email' => 'person@domain.com',
            'Content' => 'what a nice comment',
            'action_doSubmit' => 'Submit',
        ));

        $this->assertContains('Thanks for your submission, person', $result->getBody());
        $saved = AkismetTestSubmission::get()->last();
        $this->assertNotEmpty($saved);
        $this->assertEquals('person', $saved->Name);
        $this->assertEquals('person@domain.com', $saved->Email);
        $this->assertEquals('what a nice comment', $saved->Content);
        $this->assertEquals(false, (bool)$saved->IsSpam);
        $saved->delete();

        $this->markTestIncomplete('@todo fix form validation message in AkismetField');

        // Test failed setting
        $result = $this->post('AkismetTestController/Form', array(
            'Name' => 'spam',
            'Email' => 'spam@spam.com',
            'Content' => 'spam',
            'action_doSubmit' => 'Submit',
        ));

        $errorMessage = _t(
            'SilverStripe\\Akismet\\AkismetField.SPAM',
            "Your submission has been rejected because it was treated as spam."
        );
        $this->assertContains($errorMessage, $result->getBody());
        $saved = AkismetTestSubmission::get()->last();
        $this->assertNotEmpty($saved);
        $this->assertEquals('spam', $saved->Name);
        $this->assertEquals('spam@spam.com', $saved->Email);
        $this->assertEquals('spam', $saved->Content);
        $this->assertEquals(true, (bool)$saved->IsSpam);
    }

    /**
     * Test that the request processor can safely activate when able (and only then)
     */
    public function testProcessor()
    {
        $siteconfig = SiteConfig::current_site_config();
        $siteconfig->write();

        // Test assignment via request filter
        $processor = new AkismetTestTestMiddleware();
        $this->assertTrue($processor->publicIsDBReady());

        try {
            // Remove AkismetKey field
            $siteconfigTable = DataObject::getSchema()->tableName(SiteConfig::class);
            DB::query('ALTER TABLE "' . $siteconfigTable . '" DROP COLUMN "AkismetKey"');
        } catch (DatabaseException $e) {
            $this->markTestSkipped('Could not DROP database column');
        }
        $this->assertFalse($processor->publicIsDBReady());
    }
}
