<?php
/**
 * Event attend form
 *
 * @author Sardar Madumarov <madumarov@gmail.com, Sergey Pryadkin <GiperProger@gmail.com>
 * @package ow_plugins.event.forms
 * @since 1.8.5
 */
class EVENT_CLASS_AttendForm extends Form
{
    const PLUGIN_KEY = EVENT_BOL_EventService::PLUGIN_KEY;

    public function __construct( $eventId, $contId )
    {
        parent::__construct('event_attend', self::PLUGIN_KEY);
        $this->setAction(OW::getRouter()->urlFor('EVENT_CTRL_Base', 'attendFormResponder'));
        $this->setAjax();
        $hidden = new HiddenField('attend_status', self::PLUGIN_KEY);
        $this->addElement($hidden);
        $eventIdField = new HiddenField('eventId', self::PLUGIN_KEY);
        $eventIdField->setValue($eventId);
        $this->addElement($eventIdField);
        $this->setAjaxResetOnSuccess(false);
        $this->bindJsFunction(Form::BIND_SUCCESS, "function(data){
            var \$context = $('#" . $contId . "');



            if(data.messageType == 'error'){
                OW.error(data.message);
            }
            else{
                $('.current_status span.status', \$context).empty().html(data.currentLabel);
                $('.current_status span.link', \$context).css({display:'inline'});
                $('.attend_buttons .buttons', \$context).fadeOut(500);

                if ( data.eventId != 'undefuned' )
                {
                    OW.loadComponent('EVENT_CMP_EventUsers', {eventId: data.eventId},
                    {
                      onReady: function( html ){
                         $('.userList', \$context).empty().html(html);

                      }
                    });
                }

                $('.userList', \$context).empty().html(data.eventUsersCmp);
                OW.trigger('event_notifications_update', {count:data.newInvCount});
                OW.info(data.message);
            }
        }");
    }
}
