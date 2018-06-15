# Akismet Silverstripe Module

[![Build Status](https://travis-ci.org/silverstripe/silverstripe-akismet.svg?branch=master)](https://travis-ci.org/silverstripe/silverstripe-akismet)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/silverstripe/silverstripe-akismet/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/silverstripe/silverstripe-akismet/?branch=master)
[![codecov](https://codecov.io/gh/silverstripe/silverstripe-akismet/branch/master/graph/badge.svg)](https://codecov.io/gh/silverstripe/silverstripe-akismet)
[![SilverStripe supported module](https://img.shields.io/badge/silverstripe-supported-0071C4.svg)](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/)

Simple spam filter for Silverstripe using Akismet

Also, please [report any issues](https://github.com/tractorcow/silverstripe-akismet/issues)
you may encounter, as it helps us all out!

Please see [the changelog](changelog.md) for module history.

## Credits and Authors

 * Damian Mooyman - <https://github.com/tractorcow/silverstripe-akismet>
 * Attribution to Tijs Verkoyen for his Akismet API wrapper - <https://github.com/tijsverkoyen/Akismet>

## Requirements

 * SilverStripe 4.0+
 * Silverstripe SpamProtection module - <https://github.com/silverstripe/silverstripe-spamprotection>
 * Tijs Verkoyen's Akismet API wrapper - <https://github.com/tijsverkoyen/Akismet>
 * PHP 5.6 or higher

 **Note:** For a SilverStripe 3.x compatible version, please use [the 1.x release line](https://github.com/silverstripe/silverstripe-mimevalidator/tree/1.0).

## Installation Instructions

This module can be easily installed on any already-developed website

 * You can install using Composer, as below:

```bash
composer require silverstripe/akismet ^4.0
```

`AkismetSpamProtector` is automatically assigned as the default spam protector class.

 * Get an API key from [akismet.com](http://akismet.com/) and set in the site against one of the following ways.

config.yml:

```yml
---
Name: myspamprotection
---
SilverStripe\Akismet\AkismetSpamProtector:
  api_key: 5555dddd55d5d
```

\_config.php:

```php
use SilverStripe\Akismet\AkismetSpamProtector;

AkismetSpamProtector::singleton()->setApiKey('5555dddd55d5d');
```

.env:

```
SS_AKISMET_API_KEY="5555dddd55d5d"
```

If instead you want to configure your akismet key via the siteconfig (as a password field) you can
add the included extension to SiteConfig

mysite/_config/settings.yml:

```yaml
SilverStripe\SiteConfig\SiteConfig:
  extensions:
    - SilverStripe\Akismet\Config\AkismetConfig
```

### Priority of defined API keys

Please note that the API key values defined in the various ways above will be prioritised as:

1. Values assigned to the singleton via `AkismetSpamProtector::singleton()->setApiKey()`
2. Values defined in configuration, whether YAML or in \_config.php files with `Config::modify()->set(...)`
3. Values defined in the environment via .env


## Testing

By default, spam protection is disabled for users with ADMIN priviliges. There is also an option to disable
spam protection for all logged in users. In order to disable this for testing purposes, you can temporarily
modify these options in your development environment as below:

```php
use SilverStripe\Akismet\AkismetSpamProtector;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;

if (!Director::isLive()) {
	Config::modify()->remove(AkismetSpamProtector::class, 'bypass_permission');
	Config::modify()->remove(AkismetSpamProtector::class, 'bypass_members');
}
```

In order to check that your form is blocking spam correctly, you can always set 'viagra-test-123' as 
the author and Akismet will always mark this as spam.

## Comments

If you're using Comments module you can quickly set akismet to filter these out by adding the `CommentSpamProtection`
extension to the `CommentingController`

config.yml

```yml
SilverStripe\Comments\Controllers\CommentingController:
  extensions:
    - CommentSpamProtection
```

If necessary, you can also mark spam comments to be saved to the database. This will still display the spam rejection
notice, but spam comments will now be available for moderation in the backend. In order to enable this feature add
the following to your configuration.

config.yml


```yaml
# Allows spam posts to be saved for review if necessary
SilverStripe\Akismet\AkismetSpamProtector:
  save_spam: true
```

## Custom Form Usage
To enable spam protection in your custom forms, call the enableSpamProtection method with your field names mapped to the akismet fields:

````
$form  = new Form($this, 'Form', $fields, $actions, $validator);
$form->enableSpamProtection(array(
  'mapping' => array(
    'Name' => 'authorName',
    'Email' => 'authorMail',
    'Comments' => 'body'
    )
  )
);
````


## Important notes for those in the EU

Because of the way Akismet works (message, author, and other information sent to a third party) in some countries
it's legally necessary to notify and gain the user's permission prior to verification.

To create a checkbox style authorisation prompt for this field set the following configuration option:

config.yml

```yml
SilverStripe\Akismet\AkismetSpamProtector:
  require_confirmation: true
```

_config.php

```php
Config::modify()->set(AkismetSpamProtector::class, 'require_confirmation', true);
```

