<?php
// This file is part of Moodle - http://moodle.org/
//
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
 * Plugin settings
 *
 * @package    local_reflect
 * @copyright  2016 Alexander Kiy <alekiy@uni-potsdam.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {

    $settings = new admin_settingpage('local_reflect', new lang_string('pluginname', 'local_reflect'));
    $ADMIN->add('localplugins', $settings);

    /*		ORIGINAL CODE:
    *
    *	    $settings->add(new admin_setting_configtext('local_reflect/courseID',
    *                    get_string('local_reflect_courseID_key', 'local_reflect'),
    *                    get_string('local_reflect_courseID', 'local_reflect'), 'UPR1', PARAM_RAW));
    *
    */

    //	NEW VERSION WITH A TEXT AREA INSTEAD : courses' ids must be listed one per line

    $settings->add(new admin_setting_configtextarea('local_reflect/courseID', get_string('local_reflect_courseID_key', 'local_reflect'),
    	get_string('local_reflect_courseID', 'local_reflect'), 'UPR1', PARAM_RAW, $cols = '60', $rows= '8'));


}
