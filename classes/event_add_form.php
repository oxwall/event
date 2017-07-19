<?php
/**
 * Add new event form
 *
 * @author Sardar Madumarov <madumarov@gmail.com, Sergey Pryadkin <GiperProger@gmail.com>
 * @package ow_plugins.event.forms
 * @since 1.8.5
 */
class EVENT_CLASS_EventAddForm extends Form
{

    const EVENT_NAME = 'event.event_add_form.get_element';

    public function __construct( $name, $mobile = false )
    {
        parent::__construct($name);

        $militaryTime = Ow::getConfig()->getValue('base', 'military_time');

        $language = OW::getLanguage();

        $currentYear = date('Y', time());

        $title = new TextField('title');
        $title->setRequired();
        $title->setLabel($language->text('event', 'add_form_title_label'));

        $event = new OW_Event(self::EVENT_NAME, array( 'name' => 'title' ), $title);
        OW::getEventManager()->trigger($event);
        $title = $event->getData();

        $this->addElement($title);

        $startDate = new DateField('start_date');
        $startDate->setMinYear($currentYear);
        $startDate->setId('eventcus_startDate');
        $startDate->setMaxYear($currentYear + 5);
        $startDate->setRequired();

        $event = new OW_Event(self::EVENT_NAME, array( 'name' => 'start_date' ), $startDate);
        OW::getEventManager()->trigger($event);
        $startDate = $event->getData();


        $this->addElement($startDate);

        $startTime = new EVENT_CLASS_EventTimeField('start_time');
        $startTime->setMilitaryTime($militaryTime);

        if ( !empty($_POST['endDateFlag']) )
        {
            $startTime->setRequired();
        }

        $event = new OW_Event(self::EVENT_NAME, array( 'name' => 'start_time' ), $startTime);
        OW::getEventManager()->trigger($event);
        $startTime = $event->getData();

        $this->addElement($startTime);

        $endDate = new DateField('end_date');
        $endDate->setMinYear($currentYear);
        $endDate->setMaxYear($currentYear + 5);

        $event = new OW_Event(self::EVENT_NAME, array( 'name' => 'end_date' ), $endDate);
        OW::getEventManager()->trigger($event);
        $endDate = $event->getData();


        $this->addElement($endDate);

        $endTime = new EVENT_CLASS_EventTimeField('end_time');
        $endTime->setMilitaryTime($militaryTime);

        $event = new OW_Event(self::EVENT_NAME, array( 'name' => 'end_time' ), $endTime);
        OW::getEventManager()->trigger($event);
        $endTime = $event->getData();

        $this->addElement($endTime);

        $location = new TextField('location');
        $location->setRequired();
        $location->setLabel($language->text('event', 'add_form_location_label'));

        $event = new OW_Event(self::EVENT_NAME, array( 'name' => 'location' ), $location);
        OW::getEventManager()->trigger($event);
        $location = $event->getData();


        $this->addElement($location);

        $whoCanView = new RadioField('who_can_view');
        $whoCanView->setRequired();
        $whoCanView->addOptions(
            array(
                '1' => $language->text('event', 'add_form_who_can_view_option_anybody'),
                '2' => $language->text('event', 'add_form_who_can_view_option_invit_only')
            )
        );
        $whoCanView->setLabel($language->text('event', 'add_form_who_can_view_label'));

        $event = new OW_Event(self::EVENT_NAME, array( 'name' => 'who_can_view' ), $whoCanView);
        OW::getEventManager()->trigger($event);
        $whoCanView = $event->getData();

        $this->addElement($whoCanView);

        $whoCanInvite = new RadioField('who_can_invite');
        $whoCanInvite->setRequired();
        $whoCanInvite->addOptions(
            array(
                EVENT_BOL_EventService::CAN_INVITE_PARTICIPANT => $language->text('event', 'add_form_who_can_invite_option_participants'),
                EVENT_BOL_EventService::CAN_INVITE_CREATOR => $language->text('event', 'add_form_who_can_invite_option_creator')
            )
        );
        $whoCanInvite->setLabel($language->text('event', 'add_form_who_can_invite_label'));
        $whoCanInvite->setColumnCount(2);

        $event = new OW_Event(self::EVENT_NAME, array( 'name' => 'who_can_invite' ), $whoCanInvite);
        OW::getEventManager()->trigger($event);
        $whoCanInvite = $event->getData();

        $this->addElement($whoCanInvite);

        $submit = new Submit('submit');
        $submit->setValue($language->text('event', 'add_form_submit_label'));
        $this->addElement($submit);

        $mobile ? $desc = new Textarea('desc') : $desc = new WysiwygTextarea('desc');

        $desc->setLabel($language->text('event', 'add_form_desc_label'));
        $desc->setRequired();

        $event = new OW_Event(self::EVENT_NAME, array( 'name' => 'desc' ), $desc);
        OW::getEventManager()->trigger($event);
        $desc = $event->getData();

        $this->addElement($desc);

        $imageField = new FileField('image');
        $imageField->setLabel($language->text('event', 'add_form_image_label'));
        $this->addElement($imageField);

        $event = new OW_Event(self::EVENT_NAME, array( 'name' => 'image' ), $imageField);
        OW::getEventManager()->trigger($event);
        $imageField = $event->getData();

        $this->setEnctype(Form::ENCTYPE_MULTYPART_FORMDATA);
    }
}