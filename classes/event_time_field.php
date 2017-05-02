<?php
/**
 * Form element: CheckboxField.
 *
 * @author Sardar Madumarov <madumarov@gmail.com, Sergey Pryadkin <GiperProger@gmail.com>
 * @package ow_core
 * @since 1.8.5
 */
class EVENT_CLASS_EventTimeField extends FormElement
{
    private $militaryTime;

    private $allDay = false;

    /**
     * Constructor.
     *
     * @param string $name
     */
    public function __construct( $name )
    {
        parent::__construct($name);
        $this->militaryTime = false;
    }

    public function setMilitaryTime( $militaryTime )
    {
        $this->militaryTime = (bool) $militaryTime;
    }

    public function setValue( $value )
    {
        if ( $value === null )
        {
            $this->value = null;
        }

        $this->allDay = false;

        if ( $value === 'all_day' )
        {
            $this->allDay = true;
            $this->value = null;
            return;
        }

        if ( is_array($value) && isset($value['hour']) && isset($value['minute']) )
        {
            $this->value = array_map('intval', $value);
        }

        if ( is_string($value) && strstr($value, ':') )
        {
            $parts = explode(':', $value);
            $this->value['hour'] = (int) $parts[0];
            $this->value['minute'] = (int) $parts[1];
        }
    }

    public function getValue()
    {
        if ( $this->allDay === true )
        {
            return 'all_day';
        }

        return $this->value;
    }

    /**
     *
     * @return string
     */
    public function getElementJs()
    {
        $jsString = "var formElement = new OwFormElement('" . $this->getId() . "', '" . $this->getName() . "');";

        return $jsString.$this->generateValidatorAndFilterJsCode("formElement");
    }

    private function getTimeString( $hour, $minute )
    {
        if ( $this->militaryTime )
        {
            $hour = $hour < 10 ? '0' . $hour : $hour;
            return $hour . ':' . $minute;
        }
        else
        {
            if ( $hour == 12 )
            {
                $dp = 'pm';
            }
            else if ( $hour > 12 )
            {
                $hour = $hour - 12;
                $dp = 'pm';
            }
            else
            {
                $dp = 'am';
            }

            $hour = $hour < 10 ? '0' . $hour : $hour;
            return $hour . ':' . $minute . $dp;
        }
    }

    /**
     * @see FormElement::renderInput()
     *
     * @param array $params
     * @return string
     */
    public function renderInput( $params = null )
    {
        parent::renderInput($params);

        for ( $hour = 0; $hour <= 23; $hour++ )
        {
            $valuesArray[$hour . ':0'] = array('label' => $this->getTimeString($hour, '00'), 'hour' => $hour, 'minute' => 0);
            $valuesArray[$hour . ':30'] = array('label' => $this->getTimeString($hour, '30'), 'hour' => $hour, 'minute' => 30);
        }

        $optionsString = UTIL_HtmlTag::generateTag('option', array('value' => ""), true, OW::getLanguage()->text('event', 'time_field_invitation_label'));

        $allDayAttrs = array( 'value' => "all_day"  );

        if ( $this->allDay )
        {
            $allDayAttrs['selected'] = 'selected';
        }

        $optionsString = UTIL_HtmlTag::generateTag('option', $allDayAttrs, true, OW::getLanguage()->text('event', 'all_day'));

        foreach ( $valuesArray as $value => $labelArr )
        {
            $attrs = array('value' => $value);

            if ( !empty($this->value) && $this->value['hour'] === $labelArr['hour'] && $this->value['minute'] === $labelArr['minute'] )
            {
                $attrs['selected'] = 'selected';
            }

            $optionsString .= UTIL_HtmlTag::generateTag('option', $attrs, true, $labelArr['label']);
        }

        return UTIL_HtmlTag::generateTag('select', $this->attributes, true, $optionsString);
    }
}