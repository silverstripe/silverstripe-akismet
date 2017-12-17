<?php

namespace SilverStripe\Akismet\Config;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Akismet\AkismetSpamProtector;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;
use SilverStripe\Control\RequestFilter;
use SilverStripe\Security\Security;

/**
 * Allows akismet to be configured via siteconfig instead of hard-coded configuration
 */
class AkismetProcessor implements RequestFilter
{
    public function postRequest(HTTPRequest $request, HTTPResponse $response)
    {
    }

    public function preRequest(HTTPRequest $request)
    {
        // Skip if database isn't ready
        if (!$this->isDBReady()) {
            return;
        }

        // Skip if SiteConfig doesn't have this extension
        if (!SiteConfig::has_extension(AkismetConfig::class)) {
            return;
        }

        // Check if key exists
        $akismetKey = SiteConfig::current_site_config()->AkismetKey;
        if ($akismetKey) {
            AkismetSpamProtector::set_api_key($akismetKey);
        }
    }

    /**
     * Make sure the DB is ready before accessing siteconfig db field
     *
     * @return bool
     */
    protected function isDBReady()
    {
        if (!Security::database_is_ready()) {
            return false;
        }

        /** @var DataObjectSchema $schema */
        $schema = DataObject::getSchema();

        // Require table
        if (!$schema->classHasTable(SiteConfig::class)) {
            return false;
        }

        // Ensure siteconfig has all fields necessary
        $dbFields = DB::field_list($schema->tableName(SiteConfig::class));
        if (empty($dbFields)) {
            return false;
        }

        // Ensure that SiteConfig has all fields
        $objFields = $schema->databaseFields(SiteConfig::class);
        $missingFields = array_diff_key($objFields, $dbFields);
        return empty($missingFields);
    }
}
