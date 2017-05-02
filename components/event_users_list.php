<?php

/**
 * User console component class.
 *
 * @author Sardar Madumarov <madumarov@gmail.com>, Podyachev Evgeny <joker.OW2@gmail.com>, Sergey Pryadkin <GiperProger@gmail.com>
 * @package ow.event.components
 * @since 1.8.5
 */

class EVENT_CMP_EventUsersList extends BASE_CMP_Users
{

    public function getFields( $userIdList )
    {
        $fields = array();

        $qs = array();

        $qBdate = BOL_QuestionService::getInstance()->findQuestionByName('birthdate', 'sex');

        if ( $qBdate->onView )
            $qs[] = 'birthdate';

        $qSex = BOL_QuestionService::getInstance()->findQuestionByName('sex');

        if ( $qSex->onView )
            $qs[] = 'sex';

        $questionList = BOL_QuestionService::getInstance()->getQuestionData($userIdList, $qs);

        foreach ( $questionList as $uid => $q )
        {

            $fields[$uid] = array();

            $age = '';

            if ( !empty($q['birthdate']) )
            {
                $date = UTIL_DateTime::parseDate($q['birthdate'], UTIL_DateTime::MYSQL_DATETIME_DATE_FORMAT);

                $age = UTIL_DateTime::getAge($date['year'], $date['month'], $date['day']);
            }

            if ( !empty($q['sex']) )
            {
                $fields[$uid][] = array(
                    'label' => '',
                    'value' => BOL_QuestionService::getInstance()->getQuestionValueLang('sex', $q['sex']) . ' ' . $age
                );
            }

            if ( !empty($q['birthdate']) )
            {
                $dinfo = date_parse($q['birthdate']);
            }
        }

        return $fields;
    }
}
