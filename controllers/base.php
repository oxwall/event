<?php

/**
 * This software is intended for use with Oxwall Free Community Software http://www.oxwall.org/ and is
 * licensed under The BSD license.

 * ---
 * Copyright (c) 2011, Oxwall Foundation
 * All rights reserved.

 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the
 * following conditions are met:
 *
 *  - Redistributions of source code must retain the above copyright notice, this list of conditions and
 *  the following disclaimer.
 *
 *  - Redistributions in binary form must reproduce the above copyright notice, this list of conditions and
 *  the following disclaimer in the documentation and/or other materials provided with the distribution.
 *
 *  - Neither the name of the Oxwall Foundation nor the names of its contributors may be used to endorse or promote products
 *  derived from this software without specific prior written permission.

 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED
 * AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * @author Sardar Madumarov <madumarov@gmail.com>, Podyachev Evgeny <joker.OW2@gmail.com>, Sergey Pryadkin <GiperProger@gmail.com>
 * @package ow_plugins.event.controllers
 * @since 1.8.5
 */
class EVENT_CTRL_Base extends OW_ActionController
{
    /**
     * @var EVENT_BOL_EventService
     */
    private $eventService;

    public function __construct()
    {
        parent::__construct();
        $this->eventService = EVENT_BOL_EventService::getInstance();
    }

    /**
     * Add new event controller
     */
    public function add()
    {
        $language = OW::getLanguage();
        $this->setPageTitle($language->text('event', 'add_page_title'));
        $this->setPageHeading($language->text('event', 'add_page_heading'));
        $this->setPageHeadingIconClass('ow_ic_add');

        OW::getDocument()->setDescription(OW::getLanguage()->text('event', 'add_event_meta_description'));

        OW::getNavigation()->activateMenuItem(OW_Navigation::MAIN, 'event', 'main_menu_item');

        // check permissions for this page
        if ( !OW::getUser()->isAuthenticated() || !OW::getUser()->isAuthorized('event', 'add_event') )
        {
            $status = BOL_AuthorizationService::getInstance()->getActionStatus('event', 'add_event');
            throw new AuthorizationException($status['msg']);
        }
        
        $form = new EVENT_CLASS_EventAddForm('event_add');

        if ( date('n', time()) == 12 && date('j', time()) == 31 )
        {
            $defaultDate = (date('Y', time()) + 1) . '/1/1';
        }
        else if ( ( date('j', time()) + 1 ) > date('t') )
        {
            $defaultDate = date('Y', time()) . '/' . ( date('n', time()) + 1 ) . '/1';
        }
        else
        {
            $defaultDate = date('Y', time()) . '/' . date('n', time()) . '/' . ( date('j', time()) + 1 );
        }

        $form->getElement('start_date')->setValue($defaultDate);
        $form->getElement('end_date')->setValue($defaultDate);
        $form->getElement('start_time')->setValue('all_day');
        $form->getElement('end_time')->setValue('all_day');

        $checkboxId = 'input_' . EVENT_BOL_EventService::PLUGIN_KEY .'_end_date_chk';
        $tdId = 'input_' . EVENT_BOL_EventService::PLUGIN_KEY .'_end_date_td';
        $this->assign('tdId', $tdId);
        $this->assign('chId', $checkboxId);

        OW::getDocument()->addScript(OW::getPluginManager()->getPlugin("event")->getStaticJsUrl() . 'event.js');
        OW::getDocument()->addOnloadScript("new eventAddForm(". json_encode(array('checkbox_id' => $checkboxId, 'end_date_id' => $form->getElement('end_date')->getId(), 'tdId' => $tdId )) .")");

        if ( OW::getRequest()->isPost() )
        {
            if ( !empty($_POST['endDateFlag']) )
            {
                $this->assign('endDateFlag', true);
            }

            if ( $form->isValid($_POST) )
            {
                $data = $form->getValues();
                
                $serviceEvent = new OW_Event(EVENT_BOL_EventService::EVENT_BEFORE_EVENT_CREATE, array(), $data);
                OW::getEventManager()->trigger($serviceEvent);
                $data = $serviceEvent->getData();
                
                $dateArray = explode('/', $data['start_date']);

                $startStamp = mktime(0, 0, 0, $dateArray[1], $dateArray[2], $dateArray[0]);

                if ( $data['start_time'] != 'all_day' )
                {
                    $startStamp = mktime($data['start_time']['hour'], $data['start_time']['minute'], 0, $dateArray[1], $dateArray[2], $dateArray[0]);
                }

                if ( !empty($_POST['endDateFlag']) && !empty($data['end_date']) )
                {
                    $dateArray = explode('/', $data['end_date']);
                    $endStamp = mktime(0, 0, 0, $dateArray[1], $dateArray[2], $dateArray[0]);

                    $endStamp = strtotime("+1 day", $endStamp);

                    if ( $data['end_time'] != 'all_day' )
                    {
                        $hour = 0;
                        $min = 0;

                        if( $data['end_time'] != 'all_day' )
                        {
                            $hour = $data['end_time']['hour'];
                            $min = $data['end_time']['minute'];
                        }

                        $dateArray = explode('/', $data['end_date']);
                        $endStamp = mktime($hour, $min, 0, $dateArray[1], $dateArray[2], $dateArray[0]);
                    }
                }
                
                $imageValid = true;
                $datesAreValid = true;
                $imagePosted = false;

                if ( !empty($_FILES['image']['name']) )
                {
                    if ( (int) $_FILES['image']['error'] !== 0 || !is_uploaded_file($_FILES['image']['tmp_name']) || !UTIL_File::validateImage($_FILES['image']['name']) )
                    {
                        $imageValid = false;
                        OW::getFeedback()->error($language->text('base', 'not_valid_image'));
                    }
                    else
                    {
                        $imagePosted = true;
                    }
                }

                if ( empty($endStamp) )
                {
                    $endStamp = strtotime("+1 day", $startStamp);
                    $endStamp = mktime(0, 0, 0, date('n',$endStamp), date('j',$endStamp), date('Y',$endStamp));
                }

                if ( !empty($endStamp) && $endStamp < $startStamp )
                {
                    $datesAreValid = false;
                    OW::getFeedback()->error($language->text('event', 'add_form_invalid_end_date_error_message'));
                }

                if ( !trim(strip_tags($data['title'])) )
                {
                    $datesAreValid = false;
                    OW::getFeedback()->error($language->text('base', 'form_validator_string_error_message'));
                }

                if ( $imageValid && $datesAreValid )
                {
                    $event = new EVENT_BOL_Event();
                    $event->setStartTimeStamp($startStamp);
                    $event->setEndTimeStamp($endStamp);
                    $event->setCreateTimeStamp(time());
                    $event->setTitle(trim(strip_tags($data['title'])));
                    $event->setLocation(UTIL_HtmlTag::autoLink(strip_tags($data['location'])));
                    $event->setWhoCanView((int) $data['who_can_view']);
                    $event->setWhoCanInvite((int) $data['who_can_invite']);
                    $event->setDescription($data['desc']);
                    $event->setUserId(OW::getUser()->getId());
                    $event->setEndDateFlag( !empty($_POST['endDateFlag']) );
                    $event->setStartTimeDisable( $data['start_time'] == 'all_day' );
                    $event->setEndTimeDisable( $data['end_time'] == 'all_day' );

                    if ( $imagePosted )
                    {
                        $event->setImage(uniqid());
                    }
                    
                    $serviceEvent = new OW_Event(EVENT_BOL_EventService::EVENT_ON_CREATE_EVENT, array(
                        'eventDto' => $event,
                        "imageValid" => $imageValid,
                        "imageTmpPath" => $_FILES['image']['tmp_name']
                    ));
                    OW::getEventManager()->trigger($serviceEvent);

                    $this->eventService->saveEvent($event);
                    
                    if ( $imagePosted )
                    {
                        $this->eventService->saveEventImage($_FILES['image']['tmp_name'], $event->getImage());
                    }

                    $eventUser = new EVENT_BOL_EventUser();
                    $eventUser->setEventId($event->getId());
                    $eventUser->setUserId(OW::getUser()->getId());
                    $eventUser->setTimeStamp(time());
                    $eventUser->setStatus(EVENT_BOL_EventService::USER_STATUS_YES);
                    $this->eventService->saveEventUser($eventUser);
                    
                    OW::getFeedback()->info($language->text('event', 'add_form_success_message'));

//                    if ( $event->getWhoCanView() == EVENT_BOL_EventService::CAN_VIEW_ANYBODY )
//                    {
//                        $eventObj = new OW_Event('feed.action', array(
//                                'pluginKey' => 'event',
//                                'entityType' => 'event',
//                                'entityId' => $event->getId(),
//                                'userId' => $event->getUserId()
//                            ));
//                        OW::getEventManager()->trigger($eventObj);
//                    }


                    
                    $serviceEvent = new OW_Event(EVENT_BOL_EventService::EVENT_AFTER_CREATE_EVENT, array(
                        'eventId' => $event->id,
                        'eventDto' => $event
                    ), array(
                        
                    ));
                    OW::getEventManager()->trigger($serviceEvent);

                    $event = EVENT_BOL_EventService::getInstance()->findEvent($event->getId());

                    if ( $event->status == EVENT_BOL_EventService::MODERATION_STATUS_ACTIVE )
                    {
                        BOL_AuthorizationService::getInstance()->trackAction('event', 'add_event');
                    }
                    
                    $this->redirect(OW::getRouter()->urlForRoute('event.view', array('eventId' => $event->getId())));
                }
            }
        }

        if( empty($_POST['endDateFlag']) )
        {
            //$form->getElement('start_time')->addAttribute('disabled', 'disabled');
            //$form->getElement('start_time')->addAttribute('style', 'display:none;');

            $form->getElement('end_date')->addAttribute('disabled', 'disabled');
            $form->getElement('end_date')->addAttribute('style', 'display:none;');

            $form->getElement('end_time')->addAttribute('disabled', 'disabled');
            $form->getElement('end_time')->addAttribute('style', 'display:none;');
        }

        $this->addForm($form);
    }

    /**
     * Get event by params(eventId)
     * 
     * @param array $params
     * @return EVENT_BOL_Event 
     */
    private function getEventForParams( $params )
    {
        if ( empty($params['eventId']) )
        {
            throw new Redirect404Exception();
        }

        $event = $this->eventService->findEvent($params['eventId']);

        if ( $event === null )
        {
            throw new Redirect404Exception();
        }

        return $event;
    }

    /**
     * Update event controller
     * 
     * @param array $params 
     */
    public function edit( $params )
    {
        if( !OW::getUser()->isAuthenticated() )
        {
            throw new Redirect404Exception();
        }

        $userId = OW::getUser()->getId();

        $event = $this->getEventForParams($params);

        $isModerator = OW::getUser()->isAuthorized('event');

        if( $userId != $event->userId && !OW::getUser()->isAdmin() && !$isModerator )
        {
            throw new Redirect404Exception();
        }

        $language = OW::getLanguage();
        $form = new EVENT_CLASS_EventAddForm('event_edit');

        $form->getElement('title')->setValue($event->getTitle());
        $form->getElement('desc')->setValue($event->getDescription());
        $form->getElement('location')->setValue($event->getLocation());
        $form->getElement('who_can_view')->setValue($event->getWhoCanView());
        $form->getElement('who_can_invite')->setValue($event->getWhoCanInvite());
        $form->getElement('who_can_invite')->setValue($event->getWhoCanInvite());


        $startTimeArray = array('hour' => date('G', $event->getStartTimeStamp()), 'minute' => date('i', $event->getStartTimeStamp()));
        $form->getElement('start_time')->setValue($startTimeArray);

        $startDate = date('Y', $event->getStartTimeStamp()) . '/' . date('n', $event->getStartTimeStamp()) . '/' . date('j', $event->getStartTimeStamp());
        $form->getElement('start_date')->setValue($startDate);

        if ( $event->getEndTimeStamp() !== null )
        {
            $endTimeArray = array('hour' => date('G', $event->getEndTimeStamp()), 'minute' => date('i', $event->getEndTimeStamp()));
            $form->getElement('end_time')->setValue($endTimeArray);


            $endTimeStamp = $event->getEndTimeStamp();
            if ( $event->getEndTimeDisable() )
            {
                $endTimeStamp = strtotime("-1 day", $endTimeStamp);
            }

            $endDate = date('Y', $endTimeStamp) . '/' . date('n', $endTimeStamp) . '/' . date('j', $endTimeStamp);
            $form->getElement('end_date')->setValue($endDate);
        }

        if ( $event->getStartTimeDisable() )
        {
            $form->getElement('start_time')->setValue('all_day');
        }

        if ( $event->getEndTimeDisable() )
        {
            $form->getElement('end_time')->setValue('all_day');
        }

        $form->getSubmitElement('submit')->setValue(OW::getLanguage()->text('event', 'edit_form_submit_label'));

        $checkboxId = 'input_' . EVENT_BOL_EventService::PLUGIN_KEY .'_end_date_chk';
        $tdId = 'input_' . EVENT_BOL_EventService::PLUGIN_KEY .'_end_date_td';
        $this->assign('tdId', $tdId);
        $this->assign('chId', $checkboxId);
        
        OW::getDocument()->addScript(OW::getPluginManager()->getPlugin("event")->getStaticJsUrl() . 'event.js');
        OW::getDocument()->addOnloadScript("new eventAddForm(". json_encode(array('checkbox_id' => $checkboxId, 'end_date_id' => $form->getElement('end_date')->getId(), 'tdId' => $tdId )) .")");

        if ( $event->getImage() )
        {
            $this->assign('imgsrc', $this->eventService->generateImageUrl($event->getImage(), true));
        }

        $endDateFlag = $event->getEndDateFlag();

        if ( OW::getRequest()->isPost() )
        {
            $endDateFlag = !empty($_POST['endDateFlag']);

            //$this->assign('endDateFlag', !empty($_POST['endDateFlag']));

            if ( $form->isValid($_POST) )
            {
                $data = $form->getValues();
                
                $serviceEvent = new OW_Event(EVENT_BOL_EventService::EVENT_BEFORE_EVENT_EDIT, array('eventId' => $event->id), $data);
                OW::getEventManager()->trigger($serviceEvent);
                $data = $serviceEvent->getData();
                
                $dateArray = explode('/', $data['start_date']);

                $startStamp = mktime(0, 0, 0, $dateArray[1], $dateArray[2], $dateArray[0]);

                if ( $data['start_time'] != 'all_day' )
                {
                    $startStamp = mktime($data['start_time']['hour'], $data['start_time']['minute'], 0, $dateArray[1], $dateArray[2], $dateArray[0]);
                }

                if ( !empty($_POST['endDateFlag']) && !empty($data['end_date']) )
                {
                        $dateArray = explode('/', $data['end_date']);
                        $endStamp = mktime(0, 0, 0, $dateArray[1], $dateArray[2], $dateArray[0]);
                        $endStamp = strtotime("+1 day", $endStamp);

                        if ( $data['end_time'] != 'all_day' )
                        {
                            $hour = 0;
                            $min = 0;

                            if( $data['end_time'] != 'all_day' )
                            {
                                $hour = $data['end_time']['hour'];
                                $min = $data['end_time']['minute'];
                            }

                            $dateArray = explode('/', $data['end_date']);
                            $endStamp = mktime($hour, $min, 0, $dateArray[1], $dateArray[2], $dateArray[0]);
                        }
                }

                $event->setStartTimeStamp($startStamp);
                
                if ( empty($endStamp) )
                {
                    $endStamp = strtotime("+1 day", $startStamp);
                    $endStamp = mktime(0, 0, 0, date('n',$endStamp), date('j',$endStamp), date('Y',$endStamp));
                }

                if ( !trim(strip_tags($data['title'])) )
                {
                    OW::getFeedback()->error($language->text('base', 'form_validator_string_error_message'));
                    $this->redirect();
                }
                
                if ( $startStamp > $endStamp )
                {
                    OW::getFeedback()->error($language->text('event', 'add_form_invalid_end_date_error_message'));
                    $this->redirect();
                }
                else
                {
                    $event->setEndTimeStamp($endStamp);

                    if ( !empty($_FILES['image']['name']) )
                    {
                        if ( (int) $_FILES['image']['error'] !== 0 || !is_uploaded_file($_FILES['image']['tmp_name']) || !UTIL_File::validateImage($_FILES['image']['name']) )
                        {
                            OW::getFeedback()->error($language->text('base', 'not_valid_image'));
                            $this->redirect();
                        }
                        else
                        {
                            $event->setImage(uniqid());
                            $this->eventService->saveEventImage($_FILES['image']['tmp_name'], $event->getImage());

                        }
                    }
                                        
                    $event->setTitle(trim(strip_tags($data['title'])));
                    $event->setLocation(UTIL_HtmlTag::autoLink(strip_tags($data['location'])));
                    $event->setWhoCanView((int) $data['who_can_view']);
                    $event->setWhoCanInvite((int) $data['who_can_invite']);
                    $event->setDescription($data['desc']);
                    $event->setEndDateFlag(!empty($_POST['endDateFlag']));
                    $event->setStartTimeDisable( $data['start_time'] == 'all_day' );
                    $event->setEndTimeDisable( $data['end_time'] == 'all_day' );

                    $this->eventService->saveEvent($event);
                    
                    $e = new OW_Event(EVENT_BOL_EventService::EVENT_AFTER_EVENT_EDIT, array('eventId' => $event->id));
                    OW::getEventManager()->trigger($e);
                    
                    OW::getFeedback()->info($language->text('event', 'edit_form_success_message'));
                    $this->redirect(OW::getRouter()->urlForRoute('event.view', array('eventId' => $event->getId())));
                }
            }
        }

        if( !$endDateFlag )
        {
           // $form->getElement('start_time')->addAttribute('disabled', 'disabled');
           // $form->getElement('start_time')->addAttribute('style', 'display:none;');

            $form->getElement('end_date')->addAttribute('disabled', 'disabled');
            $form->getElement('end_date')->addAttribute('style', 'display:none;');

            $form->getElement('end_time')->addAttribute('disabled', 'disabled');
            $form->getElement('end_time')->addAttribute('style', 'display:none;');
        }

        $this->assign('endDateFlag', $endDateFlag);

        $this->setPageHeading($language->text('event', 'edit_page_heading'));
        $this->setPageTitle($language->text('event', 'edit_page_title'));
        OW::getNavigation()->activateMenuItem(OW_Navigation::MAIN, 'event', 'main_menu_item');
        $this->addForm($form);
    }

    /**
     * Delete event controller
     * 
     * @param array $params 
     */
    public function delete( $params )
    {
        $event = $this->getEventForParams($params);

        if ( !OW::getUser()->isAuthenticated() || ( OW::getUser()->getId() != $event->getUserId() && !OW::getUser()->isAuthorized('event') ) )
        {
            throw new Redirect403Exception();
        }

        $this->eventService->deleteEvent($event->getId());
        OW::getFeedback()->info(OW::getLanguage()->text('event', 'delete_success_message'));
        $this->redirect(OW::getRouter()->urlForRoute('event.main_menu_route'));
    }

    
    /**
     * View event controller
     * 
     * @param array $params
     */
    public function view( $params )
    {
        $event = $this->getEventForParams($params);

        $cmpId = UTIL_HtmlTag::generateAutoId('cmp');

        $this->assign('contId', $cmpId);

        if ( !OW::getUser()->isAuthorized('event', 'view_event') && $event->getUserId() != OW::getUser()->getId() )
        {
            $status = BOL_AuthorizationService::getInstance()->getActionStatus('event', 'view_event');
            throw new AuthorizationException($status['msg']);
        }
        
        if ( $event->status != 1 && !OW::getUser()->isAuthorized('event') && $event->getUserId() != OW::getUser()->getId()  )
        {
            throw new Redirect403Exception();
        }

        // guest gan't view private events
        if ( (int) $event->getWhoCanView() === EVENT_BOL_EventService::CAN_VIEW_INVITATION_ONLY && !OW::getUser()->isAuthenticated() )
        {
            $this->redirect(OW::getRouter()->urlForRoute('event.private_event', array('eventId' => $event->getId())));
        }

        $eventInvite = $this->eventService->findEventInvite($event->getId(), OW::getUser()->getId());
        $eventUser = $this->eventService->findEventUser($event->getId(), OW::getUser()->getId());

        // check if user can view event
        if ( (int) $event->getWhoCanView() === EVENT_BOL_EventService::CAN_VIEW_INVITATION_ONLY && $eventUser === null && $eventInvite === null && !OW::getUser()->isAuthorized('event') )
        {
            $this->redirect(OW::getRouter()->urlForRoute('event.private_event', array('eventId' => $event->getId())));
        }

        $buttons = array();
        $toolbar = array();
        
        if ( OW::getUser()->isAuthorized('event') || OW::getUser()->getId() == $event->getUserId() )
        {
            $buttons = array(
                'edit' => array('url' => OW::getRouter()->urlForRoute('event.edit', array('eventId' => $event->getId())), 'label' => OW::getLanguage()->text('event', 'edit_button_label')),
                'delete' =>
                array(
                    'url' => OW::getRouter()->urlForRoute('event.delete', array('eventId' => $event->getId())),
                    'label' => OW::getLanguage()->text('event', 'delete_button_label'),
                    'confirmMessage' => OW::getLanguage()->text('event', 'delete_confirm_message')
                )
            );
        }
        
        $this->assign('editArray', $buttons);
        
        OW::getNavigation()->activateMenuItem(OW_Navigation::MAIN, 'event', 'main_menu_item');
        
        $moderationStatus = '';
        
        if ( $event->status == 2  )
        {
            $moderationStatus = " <span class='ow_remark ow_small'>(".OW::getLanguage()->text('event', 'moderation_status_pending_approval').")</span>";
        }
        
        $this->setPageHeading($event->getTitle(). $moderationStatus);
//        $this->setPageTitle(OW::getLanguage()->text('event', 'event_view_page_heading', array('event_title' => $event->getTitle())));
        $this->setPageHeadingIconClass('ow_ic_calendar');
//        OW::getDocument()->setDescription(UTIL_String::truncate(strip_tags($event->getDescription()), 200, '...'));

        $infoArray = array(
            'id' => $event->getId(),
            'image' => ( $event->getImage() ? $this->eventService->generateImageUrl($event->getImage(), false) : null ),
            'date' => UTIL_DateTime::formatSimpleDate($event->getStartTimeStamp(), $event->getStartTimeDisable()),
            'endDate' => $event->getEndTimeStamp() === null || !$event->getEndDateFlag() ? null : UTIL_DateTime::formatSimpleDate($event->getEndTimeDisable() ? strtotime("-1 day", $event->getEndTimeStamp()) : $event->getEndTimeStamp(),$event->getEndTimeDisable()),
            'location' => $event->getLocation(),
            'desc' => UTIL_HtmlTag::autoLink($event->getDescription()),
            'title' => strip_tags(htmlspecialchars_decode($event->getTitle())),
            'creatorName' => BOL_UserService::getInstance()->getDisplayName($event->getUserId()),
            'creatorLink' => BOL_UserService::getInstance()->getUserUrl($event->getUserId()),
            'moderationStatus' => $event->status
        );

        $this->assign('info', $infoArray);

        // event attend form
        if ( OW::getUser()->isAuthenticated() && $event->getEndTimeStamp() > time() )
        {
            if ( $eventUser !== null )
            {
                $this->assign('currentStatus', OW::getLanguage()->text('event', 'user_status_label_' . $eventUser->getStatus()));
            }
            $this->addForm(new EVENT_CLASS_AttendForm($event->getId(), $cmpId));

            $onloadJs = "
                var \$context = $('#" . $cmpId . "');
                $('#event_attend_yes_btn').click(
                    function(){
                        $('input[name=attend_status]', \$context).val(" . EVENT_BOL_EventService::USER_STATUS_YES . ");
                    }
                );
                $('#event_attend_maybe_btn').click(
                    function(){
                        $('input[name=attend_status]', \$context).val(" . EVENT_BOL_EventService::USER_STATUS_MAYBE . ");
                    }
                );
                $('#event_attend_no_btn').click(
                    function(){
                        $('input[name=attend_status]', \$context).val(" . EVENT_BOL_EventService::USER_STATUS_NO . ");
                    }
                );

                $('.current_status a', \$context).click(
                    function(){
                        $('.attend_buttons .buttons', \$context).fadeIn(500);
                    }
                );
            ";

            OW::getDocument()->addOnloadScript($onloadJs);
        }
        else
        {
            $this->assign('no_attend_form', true);
        }
        
        if ($event->status == EVENT_BOL_EventService::MODERATION_STATUS_ACTIVE && ( $event->getEndTimeStamp() > time() && ((int) $event->getUserId() === OW::getUser()->getId() || ( (int) $event->getWhoCanInvite() === EVENT_BOL_EventService::CAN_INVITE_PARTICIPANT && $eventUser !== null) ) ) )
        {
            $params = array(
                $event->id
            );

            $this->assign('inviteLink', true);
            OW::getDocument()->addOnloadScript("
                var eventFloatBox;
                $('#inviteLink', $('#" . $cmpId . "')).click(
                    function(){
                        eventFloatBox = OW.ajaxFloatBox('EVENT_CMP_InviteUserListSelect', " . json_encode($params) . ", {width:600, iconClass: 'ow_ic_user', title: " . json_encode(OW::getLanguage()->text('event', 'friends_invite_button_label')) . "});
                    }
                );
                OW.bind('base.avatar_user_list_select',
                    function(list){
                        eventFloatBox.close();
                        $.ajax({
                            type: 'POST',
                            url: " . json_encode(OW::getRouter()->urlFor('EVENT_CTRL_Base', 'inviteResponder')) . ",
                            data: 'eventId=" . json_encode($event->getId()) . "&userIdList='+JSON.stringify(list),
                            dataType: 'json',
                            success : function(data){
                                if( data.messageType == 'error' ){
                                    OW.error(data.message);
                                }
                                else{
                                    OW.info(data.message);
                                }
                            },
                            error : function( XMLHttpRequest, textStatus, errorThrown ){
                                OW.error(textStatus);
                            }
                        });
                    }
                );
            ");
        }

        if ( $event->status == EVENT_BOL_EventService::MODERATION_STATUS_ACTIVE )
        {
            $cmntParams = new BASE_CommentsParams('event', 'event');
            $cmntParams->setEntityId($event->getId());
            $cmntParams->setOwnerId($event->getUserId());
            $this->addComponent('comments', new BASE_CMP_Comments($cmntParams));
        }
        
        $this->addComponent('userListCmp', new EVENT_CMP_EventUsers($event->getId()));

        $ev = new BASE_CLASS_EventCollector(EVENT_BOL_EventService::EVENT_COLLECT_TOOLBAR, array(
            "event" => $event
        ));

        OW::getEventManager()->trigger($ev);
        
        $this->assign("toolbar", $ev->getData());

        $params = array(
            "sectionKey" => "event",
            "entityKey" => "eventView",
            "title" => "event+meta_title_event_view",
            "description" => "event+meta_desc_event_view",
            "keywords" => "event+meta_keywords_event_view",
            "vars" => array(
                "event_title" => $event->getTitle(),
                "event_description" => UTIL_HtmlTag::stripTags($event->getDescription())
            )
        );

        OW::getEventManager()->trigger(new OW_Event("base.provide_page_meta_info", $params));
    }

    /**
     * Events list controller
     * 
     * @param array $params 
     */
    public function eventsList( $params )
    {
        if ( empty($params['list']) )
        {
            throw new Redirect404Exception();
        }


        if ( !OW::getUser()->isAuthorized('event', 'view_event') )
        {
            $status = BOL_AuthorizationService::getInstance()->getActionStatus('event', 'view_event');
            throw new AuthorizationException($status['msg']);
        }

        $configs = $this->eventService->getConfigs();
        $page = ( empty($_GET['page']) || (int) $_GET['page'] < 0 ) ? 1 : (int) $_GET['page'];

        $language = OW::getLanguage();

        $toolbarList = array();

        switch ( trim($params['list']) )
        {
            case 'created':
                if ( !OW::getUser()->isAuthenticated() )
                {
                    throw new Redirect403Exception();
                }

                $this->setPageHeading($language->text('event', 'event_created_by_me_page_heading'));
//                $this->setPageTitle($language->text('event', 'event_created_by_me_page_title'));
                $this->setPageHeadingIconClass('ow_ic_calendar');
                $events = $this->eventService->findUserEvents(OW::getUser()->getId(), $page, null, true);
                $eventsCount = $this->eventService->findLatestEventsCount();
                break;

            case 'joined':
                if ( !OW::getUser()->isAuthenticated() )
                {
                    throw new Redirect403Exception();
                }

                $contentMenu = EVENT_BOL_EventService::getInstance()->getContentMenu();
                $this->addComponent('contentMenu', $contentMenu);
                $this->setPageHeading($language->text('event', 'event_joined_by_me_page_heading'));
//                $this->setPageTitle($language->text('event', 'event_joined_by_me_page_title'));
                $this->setPageHeadingIconClass('ow_ic_calendar');

                $events = $this->eventService->findUserParticipatedEvents(OW::getUser()->getId(), $page, null, true);
                $eventsCount = $this->eventService->findUserParticipatedEventsCount(OW::getUser()->getId(), true);
                break;

            case 'latest':
                $contentMenu = EVENT_BOL_EventService::getInstance()->getContentMenu();
                $contentMenu->getElement('latest')->setActive(true);
                $this->addComponent('contentMenu', $contentMenu);
                $this->setPageHeading($language->text('event', 'latest_events_page_heading'));
//                $this->setPageTitle($language->text('event', 'latest_events_page_title'));
                $this->setPageHeadingIconClass('ow_ic_calendar');
                OW::getDocument()->setDescription($language->text('event', 'latest_events_page_desc'));
                $events = $this->eventService->findPublicEvents($page);
                $eventsCount = $this->eventService->findPublicEventsCount();
                break;

            case 'user-participated-events':

                if ( empty($_GET['userId']) )
                {
                    throw new Redirect404Exception();
                }

                $user = BOL_UserService::getInstance()->findUserById($_GET['userId']);

                if ( $user === null )
                {
                    throw new Redirect404Exception();
                }

                $eventParams = array(
                    'action' => 'event_view_attend_events',
                    'ownerId' => $user->getId(),
                    'viewerId' => OW::getUser()->getId()
                );

                OW::getEventManager()->getInstance()->call('privacy_check_permission', $eventParams);

                $displayName = BOL_UserService::getInstance()->getDisplayName($user->getId());

                $this->setPageHeading($language->text('event', 'user_participated_events_page_heading', array('display_name' => $displayName)));
//                $this->setPageTitle($language->text('event', 'user_participated_events_page_title', array('display_name' => $displayName)));
                OW::getDocument()->setDescription($language->text('event', 'user_participated_events_page_desc', array('display_name' => $displayName)));
                $this->setPageHeadingIconClass('ow_ic_calendar');
                $events = $this->eventService->findUserParticipatedPublicEvents($user->getId(), $page);
                $eventsCount = $this->eventService->findUserParticipatedPublicEventsCount($user->getId());
                break;

            case 'past':
                $contentMenu = EVENT_BOL_EventService::getInstance()->getContentMenu();
                $this->addComponent('contentMenu', $contentMenu);
                $this->setPageHeading($language->text('event', 'past_events_page_heading'));
//                $this->setPageTitle($language->text('event', 'past_events_page_title'));
                $this->setPageHeadingIconClass('ow_ic_calendar');
//                OW::getDocument()->setDescription($language->text('event', 'past_events_page_desc'));
                $events = $this->eventService->findPublicEvents($page, null, true);
                $eventsCount = $this->eventService->findPublicEventsCount(true);
                break;

            case 'invited':
                if ( !OW::getUser()->isAuthenticated() )
                {
                    throw new Redirect403Exception();
                }

                $this->eventService->hideInvitationByUserId(OW::getUser()->getId());

                $contentMenu = EVENT_BOL_EventService::getInstance()->getContentMenu();
                $this->addComponent('contentMenu', $contentMenu);
                $this->setPageHeading($language->text('event', 'invited_events_page_heading'));
//                $this->setPageTitle($language->text('event', 'invited_events_page_title'));
                $this->setPageHeadingIconClass('ow_ic_calendar');
                $events = $this->eventService->findUserInvitedEvents(OW::getUser()->getId(), $page);
                $eventsCount = $this->eventService->findUserInvitedEventsCount(OW::getUser()->getId());

                foreach( $events as $event )
                {
                    $toolbarList[$event->getId()] = array();

                    $paramsList = array( 'eventId' => $event->getId(), 'page' => $page, 'list' => trim($params['list']) );

                    $acceptUrl = OW::getRequest()->buildUrlQueryString(OW::getRouter()->urlForRoute('event.invite_accept', $paramsList), array('page' => $page));
                    $ignoreUrl = OW::getRequest()->buildUrlQueryString(OW::getRouter()->urlForRoute('event.invite_decline', $paramsList), array('page' => $page));

                    $toolbarList[$event->getId()][] = array('label' => $language->text('event', 'accept_request'),'href' => $acceptUrl);
                    $toolbarList[$event->getId()][] = array('label' => $language->text('event', 'ignore_request'),'href' => $ignoreUrl);
                    
                }

                break;

            default:
                throw new Redirect404Exception();
        }

        $this->addComponent('paging', new BASE_CMP_Paging($page, ceil($eventsCount / $configs[EVENT_BOL_EventService::CONF_EVENTS_COUNT_ON_PAGE]), 5));

        $addUrl = OW::getRouter()->urlForRoute('event.add');

        $script = '$("input.add_event_button").click(function() {
                window.location='.json_encode($addUrl).';
            });';

        if ( !OW::getUser()->isAuthorized('event', 'add_event') )
        {
            $status = BOL_AuthorizationService::getInstance()->getActionStatus('event', 'add_event');

            if ( $status['status'] == BOL_AuthorizationService::STATUS_PROMOTED )
            {
                $script = '$("input.add_event_button").click(function() {
                        OW.authorizationLimitedFloatbox('.json_encode($status['msg']).');
                    });';
            }
            else if ( $status['status'] == BOL_AuthorizationService::STATUS_DISABLED )
            {
                $this->assign('noButton', true);
            }
        }

        OW::getDocument()->addOnloadScript($script);

        if ( empty($events) )
        {
            $this->assign('no_events', true);
        }
        
        $this->assign('listType', trim($params['list']));
        $this->assign('page', $page);
        $this->assign('events', $this->eventService->getListingDataWithToolbar($events, $toolbarList));
        $this->assign('toolbarList', $toolbarList);
        $this->assign('add_new_url', OW::getRouter()->urlForRoute('event.add'));
        OW::getNavigation()->activateMenuItem(OW_Navigation::MAIN, 'event', 'main_menu_item');

        // meta info
        $params = array(
            "sectionKey" => "event",
            "entityKey" => "eventsList",
            "title" => "event+meta_title_events_list",
            "description" => "event+meta_desc_events_list",
            "keywords" => "event+meta_keywords_events_list",
            "vars" => array( "event_list" => $language->text("event", str_replace("-", "_", trim($params["list"]))."_events_page_title") )
        );

        OW::getEventManager()->trigger(new OW_Event("base.provide_page_meta_info", $params));

    }

    public function inviteListAccept( $params )
    {
        if ( !OW::getUser()->isAuthenticated() )
        {
            throw new Redirect404Exception();
        }

        $userId = OW::getUser()->getId();
        $feedback = array('messageType' => 'error');
        $exit = false;
        $attendedStatus = 1;

        if ( !empty($attendedStatus) && !empty($params['eventId']) && $this->eventService->canUserView($params['eventId'], $userId) )
        {
            $event = $this->eventService->findEvent($params['eventId']);

            if ( $event->getEndTimeStamp() < time() )
            {
                throw new Redirect404Exception();
            }

            $eventUser = $this->eventService->findEventUser($params['eventId'], $userId);

            if ( $eventUser !== null && (int) $eventUser->getStatus() === (int) $attendedStatus )
            {
                $feedback['message'] = OW::getLanguage()->text('event', 'user_status_not_changed_error');
                //exit(json_encode($feedback));
            }

            if ( $event->getUserId() == OW::getUser()->getId() && (int) $attendedStatus == EVENT_BOL_EventService::USER_STATUS_NO )
            {
                $feedback['message'] = OW::getLanguage()->text('event', 'user_status_author_cant_leave_error');
                //exit(json_encode($feedback));
            }

            if ( !$exit )
            {
                if ( $eventUser === null )
                {
                    $eventUser = new EVENT_BOL_EventUser();
                    $eventUser->setUserId($userId);
                    $eventUser->setEventId((int) $params['eventId']);
                }

                $eventUser->setStatus((int) $attendedStatus);
                $eventUser->setTimeStamp(time());
                $this->eventService->saveEventUser($eventUser);
                $this->eventService->deleteUserEventInvites((int)$params['eventId'], OW::getUser()->getId());

                $feedback['message'] = OW::getLanguage()->text('event', 'user_status_updated');
                $feedback['messageType'] = 'info';

                if ( $eventUser->getStatus() == EVENT_BOL_EventService::USER_STATUS_YES && $event->getWhoCanView() == EVENT_BOL_EventService::CAN_VIEW_ANYBODY )
                {
                    $userName = BOL_UserService::getInstance()->getDisplayName($event->getUserId());
                    $userUrl = BOL_UserService::getInstance()->getUserUrl($event->getUserId());
                    $userEmbed = '<a href="' . $userUrl . '">' . $userName . '</a>';

                    OW::getEventManager()->trigger(new OW_Event('feed.activity', array(
                            'activityType' => 'event-join',
                            'activityId' => $eventUser->getId(),
                            'entityId' => $event->getId(),
                            'entityType' => 'event',
                            'userId' => $eventUser->getUserId(),
                            'pluginKey' => 'event'
                            ), array(
                            'eventId' => $event->getId(),
                            'userId' => $eventUser->getUserId(),
                            'eventUserId' => $eventUser->getId(),
                            'string' =>  OW::getLanguage()->text('event', 'feed_actiovity_attend_string' ,  array( 'user' => $userEmbed )),
                            'feature' => array()
                        )));
                }
            }
        }
        else
        {
            $feedback['message'] = OW::getLanguage()->text('event', 'user_status_update_error');
        }

        if ( !empty($feedback['message']) )
        {
            switch( $feedback['messageType'] )
            {
                case 'info':
                    OW::getFeedback()->info($feedback['message']);
                    break;
                case 'warning':
                    OW::getFeedback()->warning($feedback['message']);
                    break;
                case 'error':
                    OW::getFeedback()->error($feedback['message']);
                    break;
            }
        }

        $paramsList = array();

        if ( !empty($params['page']) )
        {
            $paramsList['page'] = $params['page'];
        }

        if ( !empty($params['list']) )
        {
            $paramsList['list'] = $params['list'];
        }

        $this->redirect(OW::getRouter()->urlForRoute('event.view_event_list', $paramsList));
    }

    public function inviteListDecline( $params )
    {
        if ( !empty($params['eventId']) )
        {
            $this->eventService->deleteUserEventInvites((int)$params['eventId'], OW::getUser()->getId());
            OW::getLanguage()->text('event', 'user_status_updated');
        }
        else
        {
            OW::getLanguage()->text('event', 'user_status_update_error');
        }

        if ( !empty($params['page']) )
        {
            $paramsList['page'] = $params['page'];
        }

        if ( !empty($params['list']) )
        {
            $paramsList['list'] = $params['list'];
        }

        $this->redirect(OW::getRouter()->urlForRoute('event.view_event_list', $paramsList));
    }

    /**
     * User's events list controller
     * 
     * @param array $params 
     */
    public function eventUserLists( $params )
    {
        if ( empty($params['eventId']) || empty($params['list']) )
        {
            throw new Redirect404Exception();
        }

        $event = $this->eventService->findEvent((int) $params['eventId']);

        if ( $event === null )
        {
            throw new Redirect404Exception();
        }

        $listArray = array_flip($this->eventService->getUserListsArray());

        if ( !array_key_exists($params['list'], $listArray) )
        {
            throw new Redirect404Exception();
        }

        if ( !OW::getUser()->isAuthorized('event', 'view_event') && $event->getUserId() != OW::getUser()->getId() && !OW::getUser()->isAuthorized('event') )
        {
            $this->assign('authErrorText', OW::getLanguage()->text('event', 'event_view_permission_error_message'));
            return;
        }

        // guest gan't view private events
        if ( (int) $event->getWhoCanView() === EVENT_BOL_EventService::CAN_VIEW_INVITATION_ONLY && !OW::getUser()->isAuthenticated() )
        {
            $this->redirect(OW::getRouter()->urlForRoute('event.private_event', array('eventId' => $event->getId())));
        }

        $eventInvite = $this->eventService->findEventInvite($event->getId(), OW::getUser()->getId());
        $eventUser = $this->eventService->findEventUser($event->getId(), OW::getUser()->getId());

        // check if user can view event
        if ( (int) $event->getWhoCanView() === EVENT_BOL_EventService::CAN_VIEW_INVITATION_ONLY && $eventUser === null && $eventInvite === null && !OW::getUser()->isAuthorized('event') )
        {
            $this->redirect(OW::getRouter()->urlForRoute('event.private_event', array('eventId' => $event->getId())));
        }

        $language = OW::getLanguage();
        $configs = $this->eventService->getConfigs();
        $page = ( empty($_GET['page']) || (int) $_GET['page'] < 0 ) ? 1 : (int) $_GET['page'];
        $status = $listArray[$params['list']];
        $eventUsers = $this->eventService->findEventUsers($event->getId(), $status, $page);
        $eventUsersCount = $this->eventService->findEventUsersCount($event->getId(), $status);

        $userIdList = array();

        /* @var $eventUser EVENT_BOL_EventUser */
        foreach ( $eventUsers as $eventUser )
        {
            $userIdList[] = $eventUser->getUserId();
        }

        $userDtoList = BOL_UserService::getInstance()->findUserListByIdList($userIdList);

        $this->addComponent('users', new EVENT_CMP_EventUsersList($userDtoList, $eventUsersCount, $configs[EVENT_BOL_EventService::CONF_EVENT_USERS_COUNT_ON_PAGE], true));

        $this->setPageHeading($language->text('event', 'user_list_page_heading_' . $status, array('eventTitle' => $event->getTitle())));
//        $this->setPageTitle($language->text('event', 'user_list_page_heading_' . $status, array('eventTitle' => $event->getTitle())));
//        OW::getDocument()->setDescription($language->text('event', 'user_list_page_desc_' . $status, array('eventTitle' => $event->getTitle())));

        OW::getNavigation()->activateMenuItem(OW_Navigation::MAIN, 'event', 'main_menu_item');
        $this->assign("eventId", $event->id);

        // meta info
        $params = array(
            "sectionKey" => "event",
            "entityKey" => "eventUsers",
            "title" => "event+meta_title_event_users",
            "description" => "event+meta_desc_event_users",
            "keywords" => "event+meta_keywords_event_users",
            "vars" => array("event_title" => $event->getTitle())
        );

        OW::getEventManager()->trigger(new OW_Event("base.provide_page_meta_info", $params));

    }

    public function privateEvent( $params )
    {
        $language = OW::getLanguage();

        $this->setPageTitle($language->text('event', 'private_page_title'));
        $this->setPageHeading($language->text('event', 'private_page_heading'));
        $this->setPageHeadingIconClass('ow_ic_lock');

        $eventId = $params['eventId'];
        $event = $this->eventService->findEvent((int) $eventId);

        $avatarList = BOL_AvatarService::getInstance()->getDataForUserAvatars(array($event->userId));
        $displayName = BOL_UserService::getInstance()->getDisplayName($event->userId);
        $userUrl = BOL_UserService::getInstance()->getUserUrl($event->userId);

        $this->assign('event', $event);
        $this->assign('avatar', $avatarList[$event->userId]);
        $this->assign('displayName', $displayName);
        $this->assign('userUrl', $userUrl);
        $this->assign('creator', $language->text('event', 'creator'));
    }
    
    /**
     * Responder for event attend form
     */
    public function attendFormResponder()
    {
        if ( !OW::getRequest()->isAjax() || !OW::getUser()->isAuthenticated() )
        {
            throw new Redirect404Exception();
        }

        $userId = OW::getUser()->getId();
        $respondArray = array('messageType' => 'error');
        
        if ( !empty($_POST['attend_status']) && in_array((int) $_POST['attend_status'], array(1, 2, 3)) && !empty($_POST['eventId']) && $this->eventService->canUserView($_POST['eventId'], $userId) )
        {
            $event = $this->eventService->findEvent($_POST['eventId']);
            
            if ( $event->getEndTimeStamp() < time() )
            {
                throw new Redirect404Exception();
            }

            $eventUser = $this->eventService->findEventUser($_POST['eventId'], $userId);

            if ( $eventUser !== null && (int) $eventUser->getStatus() === (int) $_POST['attend_status'] )
            {
                $respondArray['message'] = OW::getLanguage()->text('event', 'user_status_not_changed_error');
                exit(json_encode($respondArray));
            }

            if ( $event->getUserId() == OW::getUser()->getId() && (int) $_POST['attend_status'] == EVENT_BOL_EventService::USER_STATUS_NO )
            {
                $respondArray['message'] = OW::getLanguage()->text('event', 'user_status_author_cant_leave_error');
                exit(json_encode($respondArray));
            }

            if ( $eventUser === null )
            {
                $eventUser = new EVENT_BOL_EventUser();
                $eventUser->setUserId($userId);
                $eventUser->setEventId((int) $_POST['eventId']);
            }

            $eventUser->setStatus((int) $_POST['attend_status']);
            $eventUser->setTimeStamp(time());
            $this->eventService->saveEventUser($eventUser);

            $this->eventService->deleteUserEventInvites((int)$_POST['eventId'], OW::getUser()->getId());

            $e = new OW_Event(EVENT_BOL_EventService::EVENT_ON_CHANGE_USER_STATUS, array('eventId' => $event->id, 'userId' => $eventUser->userId));
            OW::getEventManager()->trigger($e);
            
            $respondArray['message'] = OW::getLanguage()->text('event', 'user_status_updated');
            $respondArray['messageType'] = 'info';
            $respondArray['currentLabel'] = OW::getLanguage()->text('event', 'user_status_label_' . $eventUser->getStatus());
            $respondArray['eventId'] = (int) $_POST['eventId'];
            //$eventUsersCmp = new EVENT_CMP_EventUsers((int) $_POST['eventId']);
            //$respondArray['eventUsersCmp'] = $eventUsersCmp->render();
            $respondArray['newInvCount'] = $this->eventService->findUserInvitedEventsCount(OW::getUser()->getId());

            if ( $eventUser->getStatus() == EVENT_BOL_EventService::USER_STATUS_YES && $event->getWhoCanView() == EVENT_BOL_EventService::CAN_VIEW_ANYBODY )
            {
//                $params = array(
//                    'pluginKey' => 'event',
//                    'entityType' => 'event_join',
//                    'entityId' => $eventUser->getUserId(),
//                    'userId' => $eventUser->getUserId()
//                );
//
//                $url = OW::getRouter()->urlForRoute('event.view', array('eventId' => $event->getId()));
//                $thumb = $this->eventService->generateImageUrl($event->getId(), true);
//
//
//
//                $dataValue = array(
//                    'time' => $eventUser->getTimeStamp(),
//                    'string' => OW::getLanguage()->text('event', 'feed_user_join_string'),
//                    'content' => '<div class="clearfix"><div class="ow_newsfeed_item_picture">
//                        <a href="' . $url . '"><img src="' . $thumb . '" /></a>
//                        </div><div class="ow_newsfeed_item_content">
//                        <a class="ow_newsfeed_item_title" href="' . $url . '">' . $event->getTitle() . '</a><div class="ow_remark">' . strip_tags($event->getDescription()) . '</div></div></div>',
//                    'view' => array(
//                        'iconClass' => 'ow_ic_calendar'
//                    )
//                );
//
//                $fEvent = new OW_Event('feed.action', $params, $dataValue);
//                OW::getEventManager()->trigger($fEvent);

                $userName = BOL_UserService::getInstance()->getDisplayName($event->getUserId());
                $userUrl = BOL_UserService::getInstance()->getUserUrl($event->getUserId());
                $userEmbed = '<a href="' . $userUrl . '">' . $userName . '</a>';

                OW::getEventManager()->trigger(new OW_Event('feed.activity', array(
                        'activityType' => 'event-join',
                        'activityId' => $eventUser->getId(),
                        'entityId' => $event->getId(),
                        'entityType' => 'event',
                        'userId' => $eventUser->getUserId(),
                        'pluginKey' => 'event'
                        ), array(
                        'eventId' => $event->getId(),
                        'userId' => $eventUser->getUserId(),
                        'eventUserId' => $eventUser->getId(),
                        'string' =>  OW::getLanguage()->text('event', 'feed_actiovity_attend_string' ,  array( 'user' => $userEmbed )),
                        'feature' => array()
                    )));
            }
        }
        else
        {
            $respondArray['message'] = OW::getLanguage()->text('event', 'user_status_update_error');
        }

        exit(json_encode($respondArray));
    }

    /**
     * Responder for event invite form
     */
    public function inviteResponder()
    {
        $respondArray = array();

        if ( empty($_POST['eventId']) || empty($_POST['userIdList']) || !OW::getUser()->isAuthenticated() )
        {
            $respondArray['messageType'] = 'error';
            $respondArray['message'] = '_ERROR_';
            echo json_encode($respondArray);
            exit;
        }

        $idList = json_decode($_POST['userIdList']);

        if ( empty($_POST['eventId']) || empty($idList) )
        {
            $respondArray['messageType'] = 'error';
            $respondArray['message'] = '_EMPTY_EVENT_ID_';
            echo json_encode($respondArray);
            exit;
        }

        $event = $this->eventService->findEvent($_POST['eventId']);

        if ( $event->getEndTimeStamp() < time() )
        {
            throw new Redirect404Exception();
        }

        if ( $event === null )
        {
            $respondArray['messageType'] = 'error';
            $respondArray['message'] = '_EMPTY_EVENT_';
            echo json_encode($respondArray);
            exit;
        }

        if ( (int) $event->getUserId() === OW::getUser()->getId() || (int) $event->getWhoCanInvite() === EVENT_BOL_EventService::CAN_INVITE_PARTICIPANT )
        {
            $count = 0;

            $userList = BOL_UserService::getInstance()->findUserListByIdList($idList);

            foreach ( $userList as $user )
            {
                $userId = $user->id;
                $eventInvite = $this->eventService->findEventInvite($event->getId(), $userId);

                if ( $eventInvite === null )
                {
                    $eventInvite = $this->eventService->inviteUser($event->getId(), $userId, OW::getUser()->getId());
                    $eventObj = new OW_Event('event.invite_user', array('userId' => $userId, 'inviterId' => OW::getUser()->getId(), 'eventId' => $event->getId(), 'imageId' => $event->getImage(), 'eventTitle' => $event->getTitle(), 'eventDesc' => $event->getDescription(), 'displayInvitation' => $eventInvite->displayInvitation));
                    OW::getEventManager()->trigger($eventObj);
                    $count++;
                }
            }
        }

        $respondArray['messageType'] = 'info';
        $respondArray['message'] = OW::getLanguage()->text('event', 'users_invite_success_message', array('count' => $count));

        exit(json_encode($respondArray));
    }
    
    public function approve( $params )
    {
        $entityId = $params["eventId"];
        $entityType = EVENT_CLASS_ContentProvider::ENTITY_TYPE;
        
        $backUrl = OW::getRouter()->urlForRoute("event.view", array(
            "eventId" => $entityId
        ));
        
        $event = new OW_Event("moderation.approve", array(
            "entityType" => $entityType,
            "entityId" => $entityId
        ));
        
        OW::getEventManager()->trigger($event);
        
        $data = $event->getData();
        if ( empty($data) )
        {
            $this->redirect($backUrl);
        }
        
        if ( $data["message"] )
        {
            OW::getFeedback()->info($data["message"]);
        }
        else
        {
            OW::getFeedback()->error($data["error"]);
        }
        
        $this->redirect($backUrl);
    }


}








