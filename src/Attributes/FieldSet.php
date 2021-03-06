<?php

namespace Sintattica\Atk\Attributes;

use Sintattica\Atk\Utils\StringParser;

/**
 * A fieldset can be used to combine multiple attributes to a single
 * attribute in edit/view mode.
 *
 * @author Peter C. Verhage <peter@ibuildings.nl>
 */
class FieldSet extends Attribute
{
    private $m_template;
    private $m_parser;

    /**
     * Constructor.
     *
     * @param string $name fieldset name
     * @param int $flags flags
     * @param string $template template string
     */
    public function __construct($name, $flags = 0, $template)
    {
        $flags = $flags | self::AF_NO_SORT | self::AF_HIDE_SEARCH;
        parent::__construct($name, $flags);
        
        $this->setTemplate($template);
        $this->setLoadType(self::NOLOAD);
        $this->setStorageType(self::NOSTORE);
    }
    
    public function isEmpty($record)
    {
        // always return false, this way you can mark a field-set as obligatory
        // as a visual cue without ATK complaining that no value has been set
        return false;
    }

    /**
     * Check if one of the fields contains an error.
     *
     * @param array $errors The error list is one that is stored in the
     *                      "atkerror" section of a record, for example
     *                      generated by validate() methods.
     *
     * @return bool
     */
    public function getError($errors)
    {
        $fields = array_unique($this->getParser()->getFields());

        foreach ($fields as $field) {
            @list($attrName) = explode('.', $field);
            $attr = $this->getOwnerInstance()->getAttribute($attrName);
            if ($attr->getError($errors)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the fieldset template.
     *
     * @return string template string
     */
    public function getTemplate()
    {
        return $this->m_template;
    }

    /**
     * Sets the fieldset template. To include an attribute use [attribute name].
     *
     * @param string $template template string
     */
    public function setTemplate($template)
    {
        $this->m_template = $template;
        $this->m_parser = null;
    }

    /**
     * Returns the string parser instance for the fieldset template.
     *
     * @return StringParser
     */
    protected function getParser()
    {
        if ($this->m_parser == null) {
            $this->m_parser = new StringParser($this->getTemplate());
        }

        return $this->m_parser;
    }

    /**
     * Make sure we disable the normal rendering for attributes that
     * are part of this fieldset.
     */
    public function postInit()
    {
        $fields = $this->getParser()->getFields();
        foreach ($fields as $field) {
            list($attrName) = explode('.', $field);
            $attr = $this->getOwnerInstance()->getAttribute($attrName);
            $attr->addDisabledMode(self::DISABLED_VIEW | self::DISABLED_EDIT);
            $attr->setTabs($this->getTabs());
            $attr->setSections($this->getSections());
        }
    }

    /**
     * Renders the fieldset.
     *
     * @param string $type edit or display
     * @param array $record record
     * @param string $mode mode
     * @param string $fieldprefix fieldprefix
     *
     * @return string rendered HTML
     */
    protected function renderFieldSet($type, $record, $mode, $fieldprefix = '')
    {

        $replacements = [];

        $fields = array_unique($this->getParser()->getFields());

        foreach ($fields as $attrName) {
            $attr = $this->getOwnerInstance()->getAttribute($attrName);
            $field = '';

            // render the field
            if ($type == 'edit') {
                if (($mode == 'edit' && $attr->hasFlag(self::AF_HIDE_EDIT)) || ($mode == 'add' && $attr->hasFlag(self::AF_HIDE_ADD))) {
                    $field = '';
                } else {
                    $field = $attr->getEdit($mode, $record, $fieldprefix);
                }
            } else {
                if ($type == 'display') {
                    if (($mode == 'view' && $attr->hasFlag(self::AF_HIDE_VIEW))) {
                        $field = '';
                    } else {
                        $field = $attr->getView($mode, $record);
                    }
                }
            }

            if ($field) {
                $fieldId = $attr->getHtmlId($fieldprefix);

                // render the label
                if (!$attr->hasFlag(self::AF_NO_LABEL)) {
                    $label = '<label for="'.$fieldId.'" class="control-label"> '.$attr->getLabel($record, $mode).'</label>: ';
                } else {
                    $label = '';
                }

                // wrap in a div with appropriate id in order to properly handle a refreshAttribute (v. EditFormModifier)
                // for reference, see Edithandler::createTplField
                $containerId = str_replace('.', '_', $attr->getOwnerInstance()->atkNodeUri().'_'.$fieldId);

                $requiredClass = '';
                if($attr->hasFlag(Attribute::AF_OBLIGATORY)){
                    $requiredClass = ' required';
                }

                $html = '<div class="fieldset-form-group'.$requiredClass.'">';
                $html .= $label.'<div id="'.$containerId.'" class="fieldset-form-group-field">'.$field.'</div>';
                $html .= '</div>';

                $replacements[$attrName] = $html;
            } else {
                $replacements[$attrName] = '';
            }
        }

        $style = '';
        foreach($this->getCssStyles('edit') as $k => $v) {
            $style .= "$k:$v;";
        }

        $result = '<div class="atkfieldset"';
        if($style != ''){
            $result .= ' style="'.$style.'"';
        }
        $result .= '>';
        $result .= $this->getParser()->parse($replacements);
        $result .= '</div>';

        return $result;
    }

    /**
     * Edit fieldset.
     *
     * @param array $record
     * @param string $fieldprefix
     * @param string $mode
     *
     * @return string
     */
    public function edit($record, $fieldprefix, $mode)
    {
        return $this->renderFieldSet('edit', $record, $mode, $fieldprefix);
    }

    /**
     * Display fieldset.
     *
     * @param array $record
     * @param string $mode
     *
     * @return string
     */
    public function display($record, $mode)
    {
        return $this->renderFieldSet('display', $record, $mode);
    }

    public function hide($record, $fieldprefix, $mode)
    {
        $fields = array_unique($this->getParser()->getFields());
        $result = '';
        foreach ($fields as $attrName) {
            $attr = $this->getOwnerInstance()->getAttribute($attrName);
            $result .= $attr->hide($record, $fieldprefix, $mode);
        }
        return $result;
    }
}
