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
 * Web service local plugin template external functions and service definitions.
 *
 * @package    local_reflect
 * @copyright  2014 Bjoern Groneberg
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// We defined the web service functions to install.
$functions = array(
    'local_reflect_get_calendar_entries' => array(
        'classname' => 'local_reflect_external',
        'methodname' => 'get_calendar_reflect_events',
        'classpath' => 'local/reflect/externallib.php',
        'description' => 'Returns the calendar entries of the Reflection course',
        'type' => 'read',
    ),
    'local_reflect_get_feedbacks' => array(
        'classname' => 'local_reflect_external',
        'methodname' => 'get_feedbacks',
        'classpath' => 'local/reflect/externallib.php',
        'description' => 'Returns the feedback entries of the Reflection course',
        'type' => 'read',
    ),
    'local_reflect_submit_feedbacks' => array(
        'classname' => 'local_reflect_external',
        'methodname' => 'submit_feedbacks',
        'classpath' => 'local/reflect/externallib.php',
        'description' => 'Submits the feedback values for the Reflection course',
        'type' => 'write',
    ),
    'local_reflect_enrol_self' => array(
        'classname' => 'local_reflect_external',
        'methodname' => 'enrol_self',
        'classpath' => 'local/reflect/externallib.php',
        'description' => 'Enrols user in reflection course',
        'type' => 'write',
    ),
    'local_reflect_post_feedback' => array(
        'classname' => 'local_reflect_external',
        'methodname' => 'post_feedback',
        'classpath' => 'local/reflect/externallib.php',
        'description' => 'post general feedback',
        'type' => 'write',
    ),
    'local_reflect_get_completed_feedbacks' => array(
        'classname' => 'local_reflect_external',
        'methodname' => 'get_completed_feedbacks',
        'classpath' => 'local/reflect/externallib.php',
        'description' => 'Returns answered feedbacks for the Reflection course',
        'type' => 'read',
    ),
    'local_reflect_get_messages' => array(
        'classname' => 'local_reflect_external',
        'methodname' => 'get_messages',
        'classpath' => 'local/reflect/externallib.php',
        'description' => 'Returns messages sent by the reflect-block_pushnotification',
        'type' => 'read',
    )
);

// We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = array(
    'Reflect Service' => array(
        'functions' => array(
            'local_reflect_get_calendar_entries',
            'local_reflect_get_feedbacks',
            'local_reflect_submit_feedbacks',
            'local_reflect_enrol_self',
            'local_reflect_post_feedback',
            'local_reflect_get_completed_feedbacks',
            'local_reflect_get_messages'
        ),
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'reflect',
    )
);
