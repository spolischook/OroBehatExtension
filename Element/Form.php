<?php

namespace Oro\Bundle\TestFrameworkBundle\Behat\Element;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Doctrine\Common\Inflector\Inflector;

/**
 * Class Form
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @package Oro\Bundle\TestFrameworkBundle\Behat\Element
 */
class Form extends Element
{
    /**
     * @param TableNode $table
     * @throws ElementNotFoundException
     */
    public function fill(TableNode $table)
    {
        $isEmbeddedForm = isset($this->options['embedded-id']);
        if ($isEmbeddedForm) {
            $this->getDriver()->switchToIFrame($this->options['embedded-id']);
        }
        foreach ($table->getRows() as $row) {
            list($label, $value) = $row;
            $locator = isset($this->options['mapping'][$label]) ? $this->options['mapping'][$label] : $label;
            $value = self::normalizeValue($value);

            $field = $this->findField($locator);
            if (null === $field) {
                throw new ElementNotFoundException(
                    $this->getDriver(),
                    'form field',
                    'id|name|label|value|placeholder',
                    $locator
                );
            }
            if (isset($this->options['mapping'][$label]['element'])) {
                $this->elementFactory->wrapElement(
                    $this->options['mapping'][$label]['element'],
                    $field
                );
            }
            $field->setValue($value);
        }
        if ($isEmbeddedForm) {
            $this->getDriver()->switchToWindow();
        }
    }

    public function assertFields(TableNode $table)
    {
        foreach ($table->getRows() as $row) {
            $locator = isset($this->options['mapping'][$row[0]]) ? $this->options['mapping'][$row[0]] : $row[0];
            $field = $this->findField($locator);
            self::assertNotNull($field, "Field with '$locator' locator not found");

            $expectedValue = self::normalizeValue($row[1]);
            $fieldValue = self::normalizeValue($field->getValue());
            self::assertEquals($expectedValue, $fieldValue, sprintf('Field "%s" value is not as expected', $locator));
        }
    }

    /**
     * Find last embed form in collection of fieldset
     * See collection address in Contact (CRM) form for example
     *
     * @return Form|null
     */
    public function getLastSet()
    {
        $sets = $this->findAll('css', '.oro-multiselect-holder');
        self::assertNotCount(0, $sets, 'Can\'t find any set in form');

        return $this->elementFactory->wrapElement('OroForm', array_pop($sets));
    }

    public function saveAndClose()
    {
        $this->pressActionButton('Save and Close');
    }

    public function save()
    {
        $this->pressActionButton('Save');
    }

    /**
     * Choose from list Save, Save and Close, Save and New etc. on from element
     * If button is visible it'll pressed
     * If not, select from list and pressed
     *
     * @param string $actionLocator
     */
    protected function pressActionButton($actionLocator)
    {
        $button = $this->findButton($actionLocator);

        self::assertNotNull($button, sprintf('Can\'t find "%s" form action button', $actionLocator));

        if ($button->isVisible()) {
            $button->press();

            return;
        }

        $this->elementFactory->createElement('Action Button Chooser')->click();
        $button->press();
    }

    /**
     * @param string $locator
     */
    public function pressEntitySelectEntityButton($locator)
    {
        $field = $this->findField($locator);

        if (null !== $field) {
            $field = $this->findLabel($locator);
        }

        $this->findElementInParents($field, '.entity-select-btn')->click();
    }

    /**
     * {@inheritdoc}
     * @todo Move behat elements to Driver layer. BAP-11887.
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function findField($locator)
    {
        $selector = is_array($locator)
            ? $locator
            : ['type' => 'named', 'locator' => ['field', $locator]];
        $field = $this->find($selector['type'], $selector['locator']);

        if ($field) {
            if ($field->hasAttribute('type') && 'file' === $field->getAttribute('type')) {
                return $this->elementFactory->wrapElement('FileField', $field);
            }

            if ($field->hasAttribute('type') && 'datetime' === $field->getAttribute('type')) {
                return $this->elementFactory->wrapElement('DateTimePicker', $field->getParent()->getParent());
            }

            if ($field->hasAttribute('type') && 'checkbox' === $field->getAttribute('type')) {
                return $this->elementFactory->wrapElement('Checkbox', $field);
            }

            if ($field->hasClass('select2-offscreen')) {
                return $this->elementFactory->wrapElement('Select2Entity', $field);
            }

            return $field;
        }

        if ($field = $this->findFieldByLabel($locator)) {
            return $field;
        }

        if ($fieldSetLabel = $this->findFieldSetLabel($locator)) {
            return $this->elementFactory->wrapElement('FieldSet', $fieldSetLabel->getParent());
        }

        return null;
    }

    /**
     * @param string $locator Label text
     * @return NodeElement|null
     */
    protected function findFieldByLabel($locator)
    {
        if ($label = $this->findLabel($locator)) {
            $sndParent = $label->getParent()->getParent();

            if ($sndParent->hasClass('control-group-collection')) {
                $elementName = Inflector::singularize(trim($label->getText())).'Collection';
                $elementName = $this->elementFactory->hasElement($elementName) ? $elementName : 'CollectionField';

                return $this->elementFactory->wrapElement($elementName, $sndParent);
            } elseif ($sndParent->hasClass('control-group-oro_file')) {
                $input = $sndParent->find('css', 'input[type="file"]');

                return $this->elementFactory->wrapElement('FileField', $input);
            } elseif ($select = $sndParent->find('css', 'select')) {
                return $select;
            } elseif ($sndParent->hasClass('control-group-checkbox')) {
                return $sndParent->find('css', 'input[type=checkbox]');
            } elseif ($sndParent->hasClass('control-group-choice')) {
                return $this->elementFactory->wrapElement('GroupChoiceField', $sndParent->find('css', '.controls'));
            } elseif ($field = $this->getPage()->find('css', '#'.$label->getAttribute('for'))) {
                return $field;
            } else {
                self::fail(sprintf('Find label "%s", but can\'t determine field type', $locator));
            }
        }

        return null;
    }

    /**
     * @param array|string $value
     * @return array|string
     */
    public static function normalizeValue($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::normalizeValue($item);
            }

            return $value;
        }

        $value = trim($value);

        if (0 === strpos($value, '[')) {
            return array_map('trim', explode(',', trim($value, '[]')));
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', trim($value))) {
            return new \DateTime($value);
        }

        $value = self::checkAdditionalFunctions($value);

        if (in_array($value, ['true', 'false', 'yes', 'no', 'on', 'off'])) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $value;
    }

    /**
     * Parse for string commands and execute they
     * Example: "<DateTime:August 24 11:00 AM>" would be parsed to DateTime object with provided data
     *          "Daily every 5 days, end by <Date:next month>" <> value will be replaced as well
     *
     * @param $value
     * @return \DateTime|mixed
     */
    protected static function checkAdditionalFunctions($value)
    {
        $matches = [];
        preg_match('/<(?P<function>[\w]+):(?P<value>.+)>/', $value, $matches);

        if (!empty($matches['function']) && !empty($matches['value'])) {
            if ('DateTime' === $matches['function']) {
                $value = new \DateTime($matches['value']);
            }
            if ('Date' === $matches['function']) {
                $parsed =  new \DateTime($matches['value']);
                $value = str_replace($matches[0], $parsed->format('M j, Y'), $value);
            }
        }

        return $value;
    }

    protected function findFieldSetLabel($locator)
    {
        $labelSelector = sprintf("h5.user-fieldset:contains('%s')", $locator);

        return $this->find('css', $labelSelector);
    }

    /**
     * @param NodeElement $element
     * @param string $type etc. input|label|select
     * @param int $deep Count of parent elements that will be inspected for contains searched element type
     * @return NodeElement|null First found element with given type
     */
    protected function findElementInParents(NodeElement $element, $type, $deep = 3)
    {
        $field = null;
        $parentElement = $element->getParent();
        $i = 0;

        do {
            $parentElement = $parentElement->getParent();
            $field = $this->find('css', $type);
            $i++;
        } while ($field === null && $i < $deep);

        return $field;
    }

    /**
     * Retrieves validation error message text for provided field name
     *
     * @param string $fieldName
     * @return string
     */
    public function getFieldValidationErrors($fieldName)
    {
        $field = $this->findFieldByLabel($fieldName);
        $fieldId = $field->getAttribute('id');

        $errorSpan = $this->find('css', "span.validation-failed[for='$fieldId']");

        self::assertNotNull($errorSpan, "Field $fieldName has no validation errors");

        return $errorSpan->getText();
    }
}
