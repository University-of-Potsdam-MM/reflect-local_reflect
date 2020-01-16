<?php

// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External functions backported.
 *
 * @package    local_reflect
 * @copyright  2019 Alexander Kiy <alekiy@uni-potsdam.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->libdir . "/externallib.php");

class local_reflect_external extends external_api {

    /**
     * @global type $DB
     * @global type $CFG
     * @param type $courseid
     * @return boolean
     */
    public static function addFeedbackForumToCourse($courseid, $forumName) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/forum/lib.php");
        include_once($CFG->dirroot . "/course/lib.php");

        if (!$DB->get_record("forum", array('name' => $forumName, 'course' => $courseid))) {

            // create forum entry in moodle database
            $forum = new stdClass();
            $forum->course = $courseid;
            $forum->type = "general";
            $forum->timemodified = time();
            $forum->introformat = 2;
            $forum->timemodified = time();
            $forum->name = $forumName;
            $forum->intro = "Hier wird das Feedback aus der App gesammelt";
            $forum->id = $DB->insert_record("forum", $forum);

            if (!$module = $DB->get_record("modules", array("name" => "forum"))) {
                echo $OUTPUT->notification("Could not find forum module!!");
                return false;
            }

            // create course module entry
            $mod = new stdClass();
            $mod->course = $courseid;
            $mod->module = $module->id;
            $mod->instance = $forum->id;
            $mod->section = 0;

            // assumes course/lib.php is loaded
            if (!$mod->coursemodule = add_course_module($mod)) {
                echo $OUTPUT->notification("Could not add a new course module to the course '" . $courseid . "'");
                return false;
            }

            // assumes course/lib.php is loaded
            if (!$sectionid = course_add_cm_to_section($courseid, $mod->coursemodule, 0)) {
                echo $OUTPUT->notification("Could not add the new course module to that section");
                return false;
            }

            $DB->set_field("course_modules", "section", $sectionid, array("id" => $mod->coursemodule));
        }
    }

    public static function addFeedbackPostToForum($courseid, $forumName, $feedback) {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . "/mod/forum/lib.php");
        include_once($CFG->dirroot . "/course/lib.php");

        $forum = $DB->get_record("forum", array('name' => $forumName, 'course' => $courseid));

        $discussion = new stdClass();
        $discussion->name = "Feedback von " . $USER->username;
        $discussion->message = $feedback;
        $discussion->forum = $forum->id;
        $discussion->messageformat = 1;
        $discussion->messagetrust = 0;
        $discussion->mailnow = false;
        $discussion->course = $courseid;

        $discussionPersisted = forum_add_discussion($discussion);

        rebuild_course_cache($courseid);
    }

    public static function courseHasBeenGrantedPermissionInConfig($courseID) {
        $input = get_config('local_reflect','courseID');
        $result = true;

        // exit if empty
        if (strlen($input) == 0) {
            $result = false;
        }

        // tokenize trimmed input
        $ids_array = explode("\n", str_replace("\r", "", $input));

        // check if the specified array of ids contains the course's id
        if (!in_array($courseID, $ids_array)) {
            $result = false;
        }

        if (!$result) {
            throw new moodle_exception(
                'course_id_not_in_config',
                'courseHasBeenGrantedPermissionInConfig()',
                '',
                null,
                'make sure to add the courseid to administration -> plugins -> local plugins -> reflect web service'
            );
        }

        return $result;
    }

    public static function userCanAccessCourse($courseID) {
        // returns list of courses current $USER is enrolled in and can access
        $courses = enrol_get_my_courses();
        $courses = array_keys($courses);

        if (!in_array($courseID, $courses)) {
            throw new moodle_exception(
                'user_cant_access_course',
                'userCanAccessCourse()',
                '',
                null,
                'user is either not enrolled or has no permission to view the course'
            );
        }

        return true;
    }

    /**
     * Returns boolean if self enrolment succeeded
     * @return boolean
     * @since Moodle 2.5
     */
    public static function enrol_self_parameters() {
        return new external_function_parameters(
            array(
                'courseID' => new external_value(PARAM_TEXT, 'courseID')
            )
        );
    }

    /**
     * enrol_self in course
     *
     * @package array $options various options
     * @return array Array of self enrolment details
     * @since Moodle 2.5
     */
    public static function enrol_self($courseID) {
        global $DB, $USER, $CFG;

        require_once($CFG->libdir . '/enrollib.php');

        $enrolment = false;

        if (!self::courseHasBeenGrantedPermissionInConfig($courseID)) {

        }

        // get instance
        $course = $DB->get_record('course', array('idnumber' => $courseID));
        $param = array('shortname' => 'student');
        $studentRole = $DB->get_record('role', $param);

        // Exception Handling
        if (empty($course)) {
            $errorparams = new stdClass();
            throw new moodle_exception('wsnocourse', 'enrol_self', $errorparams);
        }

        if (empty($studentRole)) {
            $errorparams = new stdClass();
            $errorparams->courseid = $course->id;
            throw new moodle_exception('wsnostudentrole', 'enrol_self', $errorparams);
        }

        $instance = null;
        $enrolinstances = enrol_get_instances($course->id, true);

        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance = $courseenrolinstance;
                break;
            }
        }

        if (empty($instance)) {
            $errorparams = new stdClass();
            $errorparams->courseid = $course->id;
            throw new moodle_exception('wsnoinstance', 'enrol_self', $errorparams);
        }

        // prepare enrolment
        $timestart = time();
        if ($instance->enrolperiod) {
            $timeend = $timestart + $instance->enrolperiod;
        } else {
            $timeend = 0;
        }

        // retrieve the manual enrolment plugin
        $transaction = $DB->start_delegated_transaction();
        $enrol = enrol_get_plugin('manual');

        if (empty($enrol))
            throw new moodle_exception('manualpluginnotinstalled', 'enrol_self');

        if (!$enrol->allow_enrol($instance)) {
            $errorparams = new stdClass();
            $errorparams->roleid = $studentid;
            $errorparams->courseid = $course->id;
            $errorparams->userid = $USER->id;
            throw new moodle_exception('wscannotenrol', 'enrol_self', '', $errorparams);
        }

        $enrol->enrol_user($instance, $USER->id, $studentRole->id);

        $transaction->allow_commit();

        $result['enrolment'] = true;
        $result['userid'] = $USER->id;

        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.5
     */
    public static function enrol_self_returns() {
        return new external_single_structure(
            array(
                'enrolment' => new external_value(PARAM_BOOL, 'result'),
                'userid' => new external_value(PARAM_INT, 'uderid')
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.5
     */
    public static function get_calendar_reflect_events_parameters() {
        return new external_function_parameters(
            array(
                'events' => new external_single_structure(
                    array(
                        'eventids' => new external_multiple_structure(
                            new external_value(PARAM_INT, 'event ids'),
                            'List of event ids',
                            VALUE_DEFAULT, array(), NULL_ALLOWED
                        ),
                        'courseids' => new external_multiple_structure(
                            new external_value(PARAM_INT, 'course ids'),
                            'List of course ids for which events will be returned',
                            VALUE_DEFAULT, array(), NULL_ALLOWED
                        ),
                        'groupids' => new external_multiple_structure(
                            new external_value(PARAM_INT, 'group ids'),
                            'List of group ids for which events should be returned',
                            VALUE_DEFAULT, array(), NULL_ALLOWED
                        )
                    ),
                    'Event details',
                    VALUE_DEFAULT, array()
                ),
                'options' => new external_single_structure(
                    array(
                        'userevents' => new external_value(PARAM_BOOL, "Set to true to return current user's user events", VALUE_DEFAULT, true, NULL_ALLOWED),
                        'siteevents' => new external_value(PARAM_BOOL, "Set to true to return global events", VALUE_DEFAULT, true, NULL_ALLOWED),
                        'timestart' => new external_value(PARAM_INT, "Time from which events should be returned", VALUE_DEFAULT, 0, NULL_ALLOWED),
                        'timeend' => new external_value(PARAM_INT, "Time to which the events should be returned", VALUE_DEFAULT, time(), NULL_ALLOWED),
                        'ignorehidden' => new external_value(PARAM_BOOL, "Ignore hidden events or not", VALUE_DEFAULT, true, NULL_ALLOWED),
                    ),
                    'Options',
                    VALUE_DEFAULT, array()
                ),
                'courseID' => new external_value(PARAM_TEXT, 'courseID')
            )
        );
    }

    /**
     * Get Calendar events
     *
     * @param array $events A list of events
     * @package array $options various options
     * @return array Array of event details
     * @since Moodle 2.5
     */
    public static function get_calendar_reflect_events($events = array(), $options = array(), $courseID) {

        global $SITE, $DB, $USER, $CFG;
        require_once($CFG->dirroot . "/calendar/lib.php");

        // Parameter validation.
        $params = self::validate_parameters(
            self::get_calendar_reflect_events_parameters(),
            array(
                'events' => $events,
                'options' => $options,
                'courseID' => $courseID
            )
        );

        $input = get_config('local_reflect','courseID');
        $warnings = array();
        $events = array();

        // exit if empty
        if (strlen($input) == 0) {
            $warnings[] = array(
                'item' => $courseID,
                'warningcode' => 'nopermissions',
                'message' => 'courseID is missing in local_reflect settings'
            );

            return array('events' => $events, 'warnings' => $warnings);
        }

        // tokenize trimmed input
        $ids_array = explode("\n",str_replace("\r", "", $input));

        // check if the specified array of ids contains the course's id
        if (!in_array($courseID, $ids_array)) {
            $warnings[] = array(
                'item' => $courseID,
                'warningcode' => 'nopermissions',
                'message' => 'courseID is missing in local_reflect settings'
            );

            return array('events' => $events, 'warnings' => $warnings);
        }

        $course = $DB->get_record('course', array('idnumber' => $courseID));

        if (!$course) {
            $warnings[] = array(
                'item' => $courseID,
                'warningcode' => 'coursenotfound',
                'message' => 'there is no course with this courseID'
            );

            return array('events' => $events, 'warnings' => $warnings);
        }

        $params['events']['courseids'] = array(0 => $course->id);
        $params['events']['groupids'] = array();

        $funcparam = array('courses' => array(), 'groups' => array());

        ////file_put_contents("D:\output.txt", "Success", FILE_APPEND);

        // Returns list of courses current $USER is enrolled in and can access
        $courses = enrol_get_my_courses();
        $courses = array_keys($courses);

        foreach ($params['events']['courseids'] as $id) {
            if (in_array($id, $courses)) {
                $funcparam['courses'][] = $id;
            } else {
                // if $USER is not enrolled or can not access the course
                $warnings[] = array(
                    'item' => $id,
                    'warningcode' => 'nopermissions',
                    'message' => 'you do not have permissions to access this course'
                );
            }
        }

        $funcparam['groups'] = array();
        $funcparam['users'] = false;

        $eventlist = calendar_get_events(
            $params['options']['timestart'],
            $params['options']['timeend'],
            $funcparam['users'],
            $funcparam['groups'],
            $funcparam['courses'],
            true,
            $params['options']['ignorehidden']
        );

        //file_put_contents("/Users/elis/Desktop/code/UPReflection/output_appointments.txt", "Complete Appointments: \n", FILE_APPEND);
        //file_put_contents("/Users/elis/Desktop/code/UPReflection/output_appointments.txt", print_r($eventlist, true)."\n", FILE_APPEND);

        foreach ($eventlist as $eventid => $eventobj) {
            $event = (array) $eventobj;

            if (calendar_view_event_allowed($eventobj)) {
                $events[$eventid] = $event;
            } else {
                $warnings[] = array(
                    'item' => $eventid,
                    'warningcode' => 'nopermissions',
                    'message' => 'you do not have permissions to view this event'
                );
            }
        }

        // We need to get events asked for eventids.
        $eventsbyid = calendar_get_events_by_id($params['events']['eventids']);
        foreach ($eventsbyid as $eventid => $eventobj) {
            $event = (array) $eventobj;

            // check if it already exists in to-be-returned-array
            if (isset($events[$eventid])) {
                continue;
            }

            if (calendar_view_event_allowed($eventobj)) {
                $events[$eventid] = $event;
            } else {
                $warnings[] = array(
                    'item' => $eventid,
                    'warningcode' => 'nopermissions',
                    'message' => 'you do not have permissions to view this event'
                );
            }
        }

        return array('events' => $events, 'warnings' => $warnings);
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.5
     */
    public static function get_calendar_reflect_events_returns() {
        return new external_single_structure(
            array(
                'events' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'event id'),
                            'name' => new external_value(PARAM_TEXT, 'event name'),
                            'description' => new external_value(PARAM_RAW, 'Description', VALUE_OPTIONAL, null, NULL_ALLOWED),
                            'format' => new external_format_value('description'),
                            'courseid' => new external_value(PARAM_INT, 'course id'),
                            'groupid' => new external_value(PARAM_INT, 'group id'),
                            'userid' => new external_value(PARAM_INT, 'user id'),
                            'repeatid' => new external_value(PARAM_INT, 'repeat id'),
                            'modulename' => new external_value(PARAM_TEXT, 'module name', VALUE_OPTIONAL, null, NULL_ALLOWED),
                            'instance' => new external_value(PARAM_INT, 'instance id'),
                            'eventtype' => new external_value(PARAM_TEXT, 'Event type'),
                            'timestart' => new external_value(PARAM_INT, 'timestart'),
                            'timeduration' => new external_value(PARAM_INT, 'time duration'),
                            'visible' => new external_value(PARAM_INT, 'visible'),
                            'uuid' => new external_value(PARAM_TEXT, 'unique id of ical events', VALUE_OPTIONAL, null, NULL_NOT_ALLOWED),
                            'sequence' => new external_value(PARAM_INT, 'sequence'),
                            'timemodified' => new external_value(PARAM_INT, 'time modified'),
                            'subscriptionid' => new external_value(PARAM_INT, 'Subscription id', VALUE_OPTIONAL, null, NULL_ALLOWED),
                        ),
                        'event'
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    public static function post_feedback_parameters() {
        return new external_function_parameters(
            array(
                'feedback' => new external_value(PARAM_TEXT, 'feedback'),
                'courseID' => new external_value(PARAM_TEXT, 'courseID')
            )
        );
    }

    public static function post_feedback($feedback, $courseID) {

        global $DB, $CFG;
        include_once($CFG->dirroot . "/course/lib.php");

        $warnings = array();
        $input = get_config('local_reflect','courseID');

        // exit if empty
        if (strlen($input) == 0) {
            $warnings[] = array(
                'item' => $courseID,
                'warningcode' => 'nopermissions',
                'message' => 'courseID is missing in local_reflect settings'
            );

            return array('result' => false, 'warnings' => $warnings);
        }

        // tokenize trimmed input
        $ids_array = explode("\n",str_replace("\r", "", $input));

        // check if the specified array of ids contains the course's id
        if (!in_array($courseID, $ids_array)) {
            $warnings[] = array(
                'item' => $courseID,
                'warningcode' => 'nopermissions',
                'message' => 'courseID is missing in local_reflect settings'
            );

            return array('result' => false, 'warnings' => $warnings);
        }

        if (!self::userCanAccessCourse($courseID)) {
            $warnings[] = array(
                'item' => $courseID,
                'warningcode' => 'nopermissions',
                'message' => 'you do not have permissions to access this course'
            );

            return array('result' => false, 'warnings' => $warnings);
        }

        $course = $DB->get_record('course', array('idnumber' => $courseID));

        if (!$course) {
            $warnings[] = array(
                'item' => $courseID,
                'warningcode' => 'coursenotfound',
                'message' => 'there is no course with this courseID'
            );

            return array('result' => true, 'warnings' => $warnings);
        }

        $courseid = $course->id;

        $forumName = "Feedback Forum";
        self::addFeedbackForumToCourse($courseid, $forumName);
        self::addFeedbackPostToForum($courseid, $forumName, $feedback);

        return array('result' => true, 'warnings' => $warnings);
    }

    public static function post_feedback_returns() {
        return new external_single_structure(
            array(
                'result' => new external_value(PARAM_BOOL, 'Result flag'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.5
     */
    public static function get_feedbacks_parameters() {
        return new external_function_parameters(
            array(
                'options' => new external_single_structure(
                    array(
                        'timestart' => new external_value(PARAM_INT, "Time from which feedbacks should be returned", VALUE_DEFAULT, 0, NULL_ALLOWED),
                        'timeend' => new external_value(PARAM_INT, "Time to which feedbacks should be returned", VALUE_DEFAULT, time(), NULL_ALLOWED)
                    ),
                    'Options',
                    VALUE_DEFAULT, array()
                ),
                'courseID' => new external_value(PARAM_TEXT, 'courseID')
            )
        );
    }

    /**
     * Get Feedback events
     * @package array $options various options
     * @return array Array of feedback details
     * @since Moodle 2.5
     */
    public static function get_feedbacks($options = array(), $courseID) {

        global $SITE, $DB, $USER, $CFG;
        require_once($CFG->dirroot . "/mod/feedback/lib.php");

        $feedbacks = array();
        $warnings = array();

        $input = get_config('local_reflect','courseID');

        // exit if empty
        if (strlen($input) == 0) {
            $warnings[] = array(
                'item' => $courseID,
                'warningcode' => 'nopermissions',
                'message' => 'courseID is missing in local_reflect settings'
            );

            return array('feedbacks' => $feedbacks, 'warnings' => $warnings);
        }

        // tokenize trimmed input
        $ids_array = explode("\n",str_replace("\r", "", $input));

        // check if the specified array of ids contains the course's id
        if (!in_array($courseID, $ids_array)) {
            $warnings[] = array(
                'item' => $courseID,
                'warningcode' => 'nopermissions',
                'message' => 'courseID is missing in local_reflect settings'
            );

            return array('feedbacks' => $feedbacks, 'warnings' => $warnings);
        }

        $course = $DB->get_record('course', array('idnumber' => $courseID));

        if (!$course) {
            $warnings[] = array(
                'item' => $courseID,
                'warningcode' => 'notexistingcourse',
                'message' => 'no course with this courseid found'
            );

            return array('feedbacks' => $feedbacks, 'warnings' => $warnings);
        }

        if (!self::userCanAccessCourse($courseID)) {
            $warnings[] = array(
                'item' => $courseID,
                'warningcode' => 'nopermissions',
                'message' => 'you do not have permissions to access this course'
            );

            return array('feedbacks' => $feedbacks, 'warnings' => $warnings);
        }

        $feedback_list = get_all_instances_in_course("feedback", $course, NULL, false);

        foreach ($feedback_list as $id => $feedback_object) {

            if (feedback_is_already_submitted($feedback_object->id))
                continue;

            // ini_set('display_errors', 'On');
            // error_reporting(E_ALL);
            $time = time();
            // var_dump($feedback_object->timeopen, $time);
            if (($feedback_object->timeopen != 0) && (($feedback_object->timeopen >= $time)))
                continue;

            if (($feedback_object->timeclose != 0) && ($time >= $feedback_object->timeclose))
                continue;

            // Changed the DB results to be ordered by the position defined in the table
            $feedbackitems = $DB->get_records_select('feedback_item', 'feedback ='.$feedback_object->id, null, 'position');

            $questions = array();

            foreach ($feedbackitems as $item_id => $item_object) {

                //file_put_contents("/xampp/htdocs/UPReflection/output_feedbacks.txt", "Whole feedback item: \n", FILE_APPEND);
                //file_put_contents("/xampp/htdocs/UPReflection/output_feedbacks.txt", print_r($item_object, true)."\n", FILE_APPEND);

                if ($item_object->typ != 'textfield' AND $item_object->typ != 'textarea' AND $item_object->typ != 'multichoice')
                    continue;

                // capture the elements 'dependitem' and 'dependvalue' to be able to define a conditional-question mechanism
                $question = array(
                    'id' => $item_object->id,
                    'questionText' => $item_object->name,
                    'type' => $item_object->typ,
                    'dependitem' => $item_object->dependitem,
                    'dependvalue' => $item_object->dependvalue
                );

                if ($item_object->typ == 'multichoice')
                    $question['choices'] = $item_object->presentation;

                $questions[$item_id] = (array) $question;
            }

            // capture the element 'page after submit' to know if there is a custom feedback message to be displayed at the end
            $feedback = array(
                'name' => $feedback_object->name,
                'feedbackMessage' => $feedback_object ->page_after_submit,
                'id' => $feedback_object->id,
                'questions' => $questions,
            );

            $feedbacks[$id] = (array) $feedback;
        }

        //file_put_contents("/xampp/htdocs/UPReflection/output_feedbacks.txt", "Obtained Feedbacks: \n", FILE_APPEND);
        //file_put_contents("/xampp/htdocs/UPReflection/output_feedbacks.txt", print_r($feedbacks, true)."\n", FILE_APPEND);

        return array('feedbacks' => $feedbacks, 'warnings' => $warnings);
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.5
     */
    public static function get_feedbacks_returns() {
        return new external_single_structure(
            array(
                'feedbacks' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_TEXT, 'feedback name'),
                            'feedbackMessage' => new external_value(PARAM_RAW,'feedback message'),          // 'feedbackMessage' needed for custom message after
                            'id' => new external_value(PARAM_INT, 'event id'),                              // questionary is submited
                            'questions' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'id' => new external_value(PARAM_INT, 'Question Id'),
                                        'questionText' => new external_value(PARAM_TEXT, 'Question Text'),
                                        'type' => new external_value(PARAM_TEXT, 'Question Type'),
                                        'dependitem' => new external_value(PARAM_TEXT, 'Depend Item'),              //'dependitem' and 'dependvalue' attributes needed for
                                        'dependvalue' => new external_value(PARAM_TEXT, 'Depend Value'),            // supporting conditional questions
                                        'choices' => new external_value(PARAM_TEXT, 'Choices', VALUE_OPTIONAL)
                                    ),
                                    'Question',
                                    VALUE_DEFAULT, array()
                                ),
                                VALUE_DEFAULT, array()
                            )
                        ),
                        'Feedback'
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.5
     */
    public static function submit_feedbacks_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'event id'),
                'answers' => array(
                    new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'Question Id'),
                                'answer' => new external_value(PARAM_TEXT, 'Answer Text'),
                            ),
                            'Answers',
                            VALUE_DEFAULT, array()
                        )
                    )
                ),
                'courseID' => new external_value(PARAM_TEXT, 'courseID')
            )
        );
    }

    /**
     * Submit Feedback
     * @package array $options various options
     * @return array Array of feedback details
     * @since Moodle 2.5
     */
    public static function submit_feedbacks($id = -1, $answers = array(), $courseID) {

        global $SITE, $DB, $USER, $CFG;
        require_once($CFG->dirroot . "/mod/feedback/lib.php");

        $result = array();
        $result['warnings'] = array();

        $input = get_config('local_reflect','courseID');

        // exit if empty
        if (strlen($input) == 0) {
            $result['warnings'][] = array(
                'item' => $courseID,
                'warningcode' => 'nopermissions',
                'message' => 'courseID is missing in local_reflect settings'
            );

            return $result;
        }

        // tokenize trimmed input
        $ids_array = explode("\n",str_replace("\r", "", $input));

        // check if the specified array of ids contains the course's id
        if (!in_array($courseID, $ids_array)) {
            $result['warnings'][] = array(
                'item' => $courseID,
                'warningcode' => 'nopermissions',
                'message' => 'courseID is missing in local_reflect settings'
            );

            return $result;
        }

        $course = $DB->get_record('course', array('idnumber' => $courseID));

        if (!$course) {
            $result['warnings'][] = array(
                'item' => $courseID,
                'warningcode' => 'notexistingcourse',
                'message' => 'no course with this courseid found'
            );

            return $result;
        }

        if (!self::userCanAccessCourse($courseID)) {
            $result['warnings'][] = array(
                'item' => $courseID,
                'warningcode' => 'nopermissions',
                'message' => 'you do not have permissions to access this course'
            );

            return $result;
        }

        // testing fix for double entries
        // feedback_is_already_submitted checks if there are already saved answers for the user
        // if true: function returns without saving the values for a second time
        if (feedback_is_already_submitted($id)) {
            $result['resultText'] = "Your answers have already been submitted";
            return $result;
        }

        $completed = new stdClass();
        $completed->feedback = $id;
        $completed->userid = $USER->id;
        $completed->guestid = false;
        $completed->timemodified = time();
        $completed->anonymous_response = 1;

        $completedid = $DB->insert_record('feedback_completed', $completed);

        $completed = $DB->get_record('feedback_completed', array('id' => $completedid));

        //get the items of the feedback
        if (!$allitems = $DB->get_records('feedback_item', array('feedback'=>$completed->feedback))) {
            $result['warnings'][] = array(
                'item' => $completed->feedback,
                'warningcode' => 'noexistingfeedback',
                'message' => 'there is no feedback with that feedback id'
            );

            return $result;
        }

        foreach ($answers as $item) {
            $value = new stdClass();
            $value->item = $item['id'];
            $value->completed = $completed->id;
            $value->tmp_completed = $completed->feedback; // need to save the original ID to identify set of answers for get_completed_feedbacks
            $value->course_id = $course->id;
            $value->value = $item['answer'];

            $DB->insert_record('feedback_value', $value);
        }

        $result['resultText'] = "Success";
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.5
     */
    public static function submit_feedbacks_returns() {
        return new external_single_structure(
            array(
                'resultText' => new external_value(PARAM_TEXT, 'Result Text'),
                'warnings' => new external_warnings()
            )
        );
    }

    /** NEW:
     * returns already answered feedbacks:
     * ATTENTION: this only works for feedbacks that were
     * submitted with the submit_feedbacks function above
     */

    public static function get_completed_feedbacks_parameters() {
        return new external_function_parameters(
            array(
                'courseID' => new external_value(PARAM_TEXT, 'courseID')
            )
        );
    }

    public static function get_completed_feedbacks($courseID) {

        global $SITE, $DB, $USER, $CFG;
        require_once($CFG->dirroot . "/mod/feedback/lib.php");

        $feedbacks = array();
        $warnings = array();

        $input = get_config('local_reflect','courseID');

        // exit if empty
        if (strlen($input) == 0) {
            $warnings[] = array(
                'item' => $courseID,
                'warningcode' => 'nopermissions',
                'message' => 'courseID is missing in local_reflect settings'
            );

            return array('feedbacks' => $feedbacks, 'warnings' => $warnings);
        }

        // tokenize trimmed input
        $ids_array = explode("\n", str_replace("\r", "", $input));

        // check if the specified array of ids contains the course's id
        if (!in_array($courseID, $ids_array)) {
            $warnings[] = array(
                'item' => $courseID,
                'warningcode' => 'nopermissions',
                'message' => 'courseID is missing in local_reflect settings'
            );

            return array('feedbacks' => $feedbacks, 'warnings' => $warnings);
        }

        $course = $DB->get_record('course', array('idnumber' => $courseID));

        if (!$course) {
            $warnings[] = array(
                'item' => $courseID,
                'warningcode' => 'notexistingcourse',
                'message' => 'no course with this courseid found'
            );

            return array('feedbacks' => $feedbacks, 'warnings' => $warnings);
        }

        if (!self::userCanAccessCourse($courseID)) {
            $warnings[] = array(
                'item' => $courseID,
                'warningcode' => 'nopermissions',
                'message' => 'you do not have permissions to access this course'
            );

            return array('feedbacks' => $feedbacks, 'warnings' => $warnings);
        }

        $feedback_list = get_all_instances_in_course("feedback", $course, NULL, false);

        foreach ($feedback_list as $id => $feedback_object) {

            // skip feedbacks that are NOT already completed
            if (!feedback_is_already_submitted($feedback_object->id))
                continue;

            // get feedbacks
            // Changed the DB results to be ordered by the position defined in the table
            $feedbackitems = $DB->get_records_select('feedback_item', 'feedback ='.$feedback_object->id, null, 'position');

            // skip, if there are no questions for that specific feedback_object
            if (!count($feedbackitems) > 0)
                continue;

            // get answers for that feedback_object
            $feedbackvalues = $DB->get_records('feedback_value', array('tmp_completed' => $feedback_object->id));

            // skip, if there are no answers from the current user for that specific feedback_object
            if (!count($feedbackvalues) > 0)
                continue;

            $questions = array();

            foreach ($feedbackitems as $item_id => $item_object) {

                // file_put_contents("/Applications/MAMP/htdocs/moodle34/output_feedbacks.txt", "Whole Feedback Item: \n", FILE_APPEND);
                // file_put_contents("/Applications/MAMP/htdocs/moodle34/output_feedbacks.txt", print_r($item_object, true)."\n", FILE_APPEND);

                $question = array(
                    'id' => $item_object->id,
                    'questionText' => $item_object->name,
                    'type' => $item_object->typ,
                    'dependitem' => $item_object->dependitem,
                    'dependvalue' => $item_object->dependvalue
                );

                if ($item_object->typ == 'multichoice')
                    $question['choices'] = $item_object->presentation;

                $questions[$item_id] = (array) $question;
            }

            $answers = array();

            foreach ($feedbackvalues as $item_id => $item_object) {

                // file_put_contents("/Applications/MAMP/htdocs/moodle34/output_feedbacks.txt", "Whole Value Item: \n", FILE_APPEND);
                // file_put_contents("/Applications/MAMP/htdocs/moodle34/output_feedbacks.txt", print_r($item_object, true)."\n", FILE_APPEND);

                $answer = array(
                    'item' => $item_object->item,
                    'completed' => $item_object->completed,
                    'courseID' => $item_object->course_id,
                    'value' => $item_object->value
                );

                $answers[$item_id] = (array) $answer;
            }

            $feedback = array(
                'name' => $feedback_object->name,
                'feedbackMessage' => $feedback_object->page_after_submit,
                'id' => $feedback_object->id,
                'questions' => $questions,
                'answers' => $answers
            );

            $feedbacks[$id] = (array) $feedback;

        }

        return array('feedbacks' => $feedbacks, 'warnings' => $warnings);
    }

    public static function get_completed_feedbacks_returns() {
        return new external_single_structure(
            array(
                'feedbacks' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_TEXT, 'feedback name'),
                            'feedbackMessage' => new external_value(PARAM_RAW, 'feedback message'),
                            'id' => new external_value(PARAM_INT, 'event id'),
                            'questions' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'id' => new external_value(PARAM_INT, 'Question Id'),
                                        'questionText' => new external_value(PARAM_TEXT, 'Question Text'),
                                        'type' => new external_value(PARAM_TEXT, 'Question Type'),
                                        'dependitem' => new external_value(PARAM_TEXT, 'Depend Item'),          // probably not needed
                                        'dependvalue' => new external_value(PARAM_TEXT, 'Depend Value'),
                                        'choices' => new external_value(PARAM_TEXT, 'Choices', VALUE_OPTIONAL)
                                    ),
                                    'Question',
                                    VALUE_DEFAULT, array()
                                ),
                                VALUE_DEFAULT, array()
                            ),
                            'answers' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'item' => new external_value(PARAM_INT, 'Question Id'),
                                        'completed' => new external_value(PARAM_TEXT, 'Completed Id'),
                                        'courseID' => new external_value(PARAM_TEXT, 'Course Id'),              // probably not needed
                                        'value' => new external_value(PARAM_TEXT, 'Feedback Value')
                                    ),
                                    'Answer',
                                    VALUE_DEFAULT, array()
                                ),
                                VALUE_DEFAULT, array()
                            )
                        ),
                        'Feedback'
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.5
     */
    public static function get_messages_parameters() {
        return new external_function_parameters(
            array(
                'courseID' => new external_value(PARAM_TEXT, 'courseID')
            )
        );
    }

    /**
     * Get Messages
     * @return array Array of message details
     * @since Moodle 2.5
     */
    public static function get_messages($courseID) {
        global $DB;
        $dbman = $DB->get_manager();

        $messages = array();
        $warnings = array();

        $input = get_config('local_reflect','courseID');

        // exit if empty
        if (strlen($input) == 0) {
            $warnings[] = array(
                'item' => $courseID,
                'warningcode' => 'nopermissions',
                'message' => 'courseID is missing in local_reflect settings'
            );

            return array('messages' => $messages, 'warnings' => $warnings);
        }

        // tokenize trimmed input
        $ids_array = explode("\n",str_replace("\r", "", $input));

        // check if the specified array of ids contains the course's id
        if (!in_array($courseID, $ids_array)) {
            $warnings[] = array(
                'item' => $courseID,
                'warningcode' => 'nopermissions',
                'message' => 'courseID is missing in local_reflect settings'
            );

            return array('messages' => $messages, 'warnings' => $warnings);
        }

        if (!self::userCanAccessCourse($courseID)) {
            $warnings[] = array(
                'item' => $courseID,
                'warningcode' => 'nopermissions',
                'message' => 'you do not have permissions to access this course'
            );

            return array('messages' => $messages, 'warnings' => $warnings);
        }

        if ($dbman->table_exists('block_pushnotification')) {

            $message_list = $DB->get_records('block_pushnotification', array('idnumber' => $courseID));

            if (!$message_list) {
                $warnings[] = array(
                    'item' => $courseID,
                    'warningcode' => 'nomessages',
                    'message' => 'no messages found'
                );
            }

            foreach ($message_list as $id => $message_object) {
                $message = array(
                    'id' => $message_object->id,
                    'timestamp' => $message_object->timestamp,
                    'title' => $message_object->title,
                    'message' => $message_object->message,
                );
                $messages[$id] = (array) $message;
            }

            return array('messages' => $messages, 'warnings' => $warnings);
        } else {
            throw new moodle_exception(
                'database_missing',
                'error',
                '',
                null,
                'The table block_pushnotification does not exist. Make sure you have reflect-block_pushnotification installed'
            );
        }
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.5
     */
    public static function get_messages_returns() {
        return new external_single_structure(
            array(
                'messages' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'message id'),
                            'timestamp' => new external_value(PARAM_INT, 'unix timestamp of message'),
                            'title' => new external_value(PARAM_TEXT, 'optional title'),
                            'message' => new external_value(PARAM_TEXT, 'content of push message'),
                        ),
                        'Message'
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }

}
