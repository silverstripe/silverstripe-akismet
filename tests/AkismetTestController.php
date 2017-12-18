<?php

namespace SilverStripe\Akismet\Tests;

use SilverStripe\Akismet\AkismetSpamProtector;
use SilverStripe\Forms\TextField;
use SilverStripe\Control\Email\Email;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\Form;
use SilverStripe\Control\Controller;
use SilverStripe\Dev\TestOnly;

/**
 * @skipUpgrade
 */
class AkismetTestController extends Controller implements TestOnly
{
    private static $allowed_actions = array(
        'Form'
    );

    public function Form()
    {
        $fields = new FieldList(
            new TextField('Name'),
            new EmailField('Email'),
            new TextareaField('Content')
        );
        $actions = new FieldList(new FormAction('doSubmit', 'Submit'));
        $validator = new RequiredFields('Name', 'Content');
        $form = new Form($this, 'Form', $fields, $actions, $validator);

        $form->enableSpamProtection(array(
            'protector' => AkismetSpamProtector::class,
            'name' => 'IsSpam',
            'mapping' => array(
                'Content' => 'body',
                'Name' => 'authorName',
                'Email' => 'authorMail',
            )
        ));

        // Because we don't want to be testing this
        $form->disableSecurityToken();
        return $form;
    }

    public function doSubmit($data, Form $form)
    {
        $item = new AkismetTestSubmission();
        $form->saveInto($item);
        $item->write();
        $form->sessionMessage('Thanks for your submission, ' . $data['Name'], 'good');
        return $this->redirect($this->Link());
    }

    public function Link($action = null)
    {
        return 'AkismetTestController';
    }
}
