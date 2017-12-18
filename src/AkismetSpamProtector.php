<?php

namespace SilverStripe\Akismet;

use SilverStripe\Akismet\Service\AkismetService;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SpamProtection\SpamProtector;

/**
 * Spam protector for Akismet
 *
 * @author Damian Mooyman
 * @package akismet
 */
class AkismetSpamProtector implements SpamProtector
{
    use Injectable;

    /**
     * Set this to your API key
     *
     * @var string
     * @config
     */
    private static $api_key = '';

    /**
     * The API key that will be used for the service. Can be set on the singleton to take priority over configuration.
     *
     * @var string
     */
    protected $apiKey = '';

    /**
     * Permission required to bypass check
     *
     * @var string
     * @config
     */
    private static $bypass_permission = 'ADMIN';

    /**
     * Set to try to bypass check for all logged in users
     *
     * @var boolean
     * @config
     */
    private static $bypass_members = false;

    /**
     * IMPORTANT: If you are operating in a country (such as Germany) that has content transmission disclosure
     * requirements, set this to true in order to require a user prompt prior to submission of user data
     * to the Akismet servers
     *
     * @var boolean
     * @config
     */
    private static $require_confirmation = false;

    /**
     * Set to true to disable spam errors, instead saving this field to the dataobject with the spam
     * detection as a flag. This will disable validation errors when spam is encountered.
     * The flag will be saved to the same field specified by the 'name' option in enableSpamProtection()
     *
     * @var boolean
     * @config
     */
    private static $save_spam = false;
    
    /**
     * @var array
     */
    private $fieldMapping = array();

    /**
     * Set the API key
     *
     * @param string $key
     * @return $this
     */
    public function setApiKey($key)
    {
        $this->apiKey = $key;
        return $this;
    }
    
    /**
     * Get the API key. Priority is given first to explicitly set values on a singleton, then to configuration values
     * and finally to environment values.
     *
     * @return string
     */
    public function getApiKey()
    {
        // Priority given to explicitly set API keys on the singleton object
        if ($this->apiKey) {
            return $this->apiKey;
        }

        // Check config for a value defined in YAML or _config.php
        $key = Config::inst()->get(AkismetSpamProtector::class, 'api_key');
        if (!empty($key)) {
            return $key;
        }
        
        // Check environment as last resort
        if ($envApiKey = Environment::getEnv('SS_AKISMET_API_KEY')) {
            return $envApiKey;
        }

        return '';
    }
    
    /**
     * Retrieves Akismet API object, or null if not configured
     *
     * @return AkismetService|null
     */
    public function getService()
    {
        // Get API key and URL
        $key = $this->getApiKey();
        if (empty($key)) {
            user_error("AkismetSpamProtector is incorrectly configured. Please specify an API key.", E_USER_WARNING);
            return null;
        }
        $url = Director::protocolAndHost();
        
        // Generate API object
        return Injector::inst()->get(AkismetService::class, false, array($key, $url));
    }
    
    public function getFormField($name = null, $title = null, $value = null, $form = null, $rightTitle = null)
    {
        return AkismetField::create($name, $title, $value, $form, $rightTitle)
            ->setFieldMapping($this->fieldMapping);
    }

    public function setFieldMapping($fieldMapping)
    {
        $this->fieldMapping = $fieldMapping;
    }
}
