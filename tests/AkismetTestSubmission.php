<?php

namespace SilverStripe\Akismet\Tests;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;

class AkismetTestSubmission extends DataObject implements TestOnly
{
    private static $db = array(
        'Name' => 'Varchar',
        'Email' => 'Varchar',
        'Content' => 'Text',
        'IsSpam' => 'Boolean',
    );

    private static $default_sort = 'ID';
}
