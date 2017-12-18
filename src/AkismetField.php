<?php

namespace SilverStripe\Akismet;

use SilverStripe\Akismet\Service\AkismetService;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\Validator;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Permission;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\Forms\FormField;
use SilverStripe\Security\Security;

/**
 * Form field to handle akismet error display and handling
 *
 * @author Damian Mooyman
 * @package akismet
 */
class AkismetField extends FormField
{
    /**
     * @var array
     */
    private $fieldMapping = array();

    /**
     *
     * @var boolean
     */
    protected $isSpam = null;
    
    /**
     * Get the nested confirmation checkbox field
     *
     * @return CheckboxField
     */
    protected function confirmationField()
    {
        // Check if confirmation is required
        $requireConfirmation = Config::inst()->get(AkismetSpamProtector::class, 'require_confirmation');
        if (empty($requireConfirmation)) {
            return null;
        }
        
        // If confirmation is required then return a checkbox
        return CheckboxField::create(
            $this->getName(),
            _t(
                __CLASS__ . '.NOTIFICATION',
                'I understand that, and give consent to, having this content submitted to '
                . 'a third party for automated spam detection'
            )
        )
            ->setError($this->Message(), $this->MessageType())
            ->setForm($this->getForm());
    }
    
    public function Field($properties = array())
    {
        $checkbox = $this->confirmationField();
        if ($checkbox) {
            return $checkbox->Field($properties);
        }
    }
    
    public function FieldHolder($properties = array())
    {
        $checkbox = $this->confirmationField();
        if ($checkbox) {
            return $checkbox->FieldHolder($properties);
        }
    }
    
    /**
     * @return array
     */
    public function getSpamMappedData()
    {
        if (empty($this->fieldMapping)) {
            return null;
        }
        
        $result = array();
        $data = $this->form->getData();

        foreach ($this->fieldMapping as $fieldName => $mappedName) {
            $result[$mappedName] = (isset($data[$fieldName])) ? $data[$fieldName] : null;
        }

        return $result;
    }
    
    /**
     * This function first gets values from mapped fields and then checks these values against
     * akismet and then notifies callback object with the spam checking result.
     * @param Validator $validator
     * @return  boolean     - True when akismet confirms that the submission is ham (not spam) or should be saved
     *                      - False when akismet confirms that the submission is spam or permission was not given to
     *                        check for spam
     */
    public function validate($validator)
    {
        
        // Check that, if necessary, the user has given permission to check for spam
        $requireConfirmation = Config::inst()->get(AkismetSpamProtector::class, 'require_confirmation');
        if ($requireConfirmation && !$this->Value()) {
            $validator->validationError(
                $this->name,
                _t(
                    __CLASS__ . '.NOTIFICATIONREQUIRED',
                    'You must give consent to submit this content to spam detection'
                ),
                "error"
            );
            return false;
        }
        
        // Check result
        $isSpam = $this->getIsSpam();
        if (!$isSpam) {
            return true;
        }

        // Save error message
        $errorMessage = _t(
            __CLASS__ . '.SPAM',
            "Your submission has been rejected because it was treated as spam."
        );

        // If spam should be allowed, let it pass and save it for later
        if (Config::inst()->get(AkismetSpamProtector::class, 'save_spam')) {
            // In order to save spam but still display the spam message, we must mock a form message
            // without failing the validation
            $errors = array(array(
                'fieldName' => $this->name,
                'message' => $errorMessage,
                'messageType' => 'error',
            ));
            $formName = $this->getForm()->FormName();

            $this->getForm()->sessionMessage($errorMessage, ValidationResult::TYPE_GOOD);

            return true;
        } else {
            // Mark as spam
            $validator->validationError($this->name, $errorMessage, "error");
            return false;
        }
    }

    /**
     * Determine if this field is spam or not
     *
     * @return boolean
     */
    public function getIsSpam()
    {
        // Prevent multiple API calls
        if ($this->isSpam !== null) {
            return $this->isSpam;
        }

        // Check bypass permission
        $permission = Config::inst()->get(AkismetSpamProtector::class, 'bypass_permission');
        if ($permission && Permission::check($permission)) {
            return false;
        }

        // if the user has logged and there's no force check on member
        $bypassMember = Config::inst()->get(AkismetSpamProtector::class, 'bypass_members');
        if ($bypassMember && Security::getCurrentUser()) {
            return false;
        }

        // Map input fields to spam fields
        $mappedData = $this->getSpamMappedData();
        $content = isset($mappedData['body']) ? $mappedData['body'] : null;
        $author = isset($mappedData['authorName']) ? $mappedData['authorName'] : null;
        $email = isset($mappedData['authorMail']) ? $mappedData['authorMail'] : null;
        $url = isset($mappedData['authorUrl']) ? $mappedData['authorUrl'] : null;

        // Check result
        /** @var AkismetService $api */
        $api = AkismetSpamProtector::singleton()->getService();
        $this->isSpam = $api && $api->isSpam($content, $author, $email, $url);
        return $this->isSpam;
    }
    
    /**
     * Get the fields to map spam protection too
     *
     * @return array Associative array of Field Names, where the indexes of the array are
     * the field names of the form and the values are the standard spamprotection
     * fields used by the protector
     */
    public function getFieldMapping()
    {
        return $this->fieldMapping;
    }

    /**
     * Set the fields to map spam protection too
     *
     * @param array $fieldMapping array of Field Names, where the indexes of the array are
     * the field names of the form and the values are the standard spamprotection
     * fields used by the protector
     * @return self
     */
    public function setFieldMapping($fieldMapping)
    {
        $this->fieldMapping = $fieldMapping;
        return $this;
    }

    /**
     * Allow spam flag to be saved to the underlying data record
     *
     * @param DataObjectInterface $record
     */
    public function saveInto(DataObjectInterface $record)
    {
        if (Config::inst()->get(AkismetSpamProtector::class, 'save_spam')) {
            $dataValue = $this->getIsSpam() ? 1 : 0;
            $record->setCastedField($this->name, $dataValue);
        }
    }
}
