<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * The Monthly Grade Report plugin administration.
 *
 * @package     report_learneractivity
 * @category    admin
 * @copyright   2021 Lukas Celinak <lukascelinak@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$ADMIN->add('reports', new admin_externalpage('report_learneractivity', get_string('pluginname',
                        'report_learneractivity'),
                "$CFG->wwwroot/report/learneractivity/index.php",
                'report/learneractivity:view'));

$roles = $DB->get_records('role');
$rolesarray = [];
foreach ($roles as $role) {
    $rolesarray[$role->id] = $role->shortname;
}

$settings->add(new admin_setting_configselect('report_learneractivity/studentrole', get_string('studentrole', 'report_learneractivity'),
                get_string('studentrole_help', 'report_learneractivity'), NULL,
                $rolesarray));


$extrafields = $DB->get_records('user_info_field');
//$extrafields =   $userfieldsapi = \core_user\fields::for_identity(\context_system::instance(), false)->with_userpic();
$extrafieldsarray = [];
foreach ($extrafields as $field) {
    $extrafieldsarray[$field->shortname] = $field->name;
}

$settings->add(new admin_setting_configselect('report_learneractivity/teamcustomfield', get_string('teamcustomfield', 'report_learneractivity'),
                get_string('teamcustomfield_help', 'report_learneractivity'), NULL,
                $extrafieldsarray));

