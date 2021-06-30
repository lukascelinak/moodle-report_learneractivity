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
 * The Monthly Grade Report search form definition.
 *
 * @package     report_learneractivity
 * @category    admin
 * @copyright   2021 Lukas Celinak <lukascelinak@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_learneractivity\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Report search form class.
 *
 * @package     report_learneractivity
 * @copyright   2021 Lukas Celinak <lukascelinak@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search extends \moodleform {

    /**
     * Form definition
     *
     * @return void
     */
    public function definition() {
        global $DB, $CFG;
        $mform = $this->_form;
        $this->gradebookroles = $CFG->gradebookroles;
        //show required config fields for report
        $mform->addElement('header', 'heading', get_string('pluginname', 'report_learneractivity'));

        $courses = $DB->get_records_menu('course', array('visible' => 1), '', 'id, fullname');
        array_unshift($courses,get_string('selectcourse', 'report_learneractivity'));
        $mform->addElement('autocomplete', 'course', get_string('course'), $courses);
        $mform->addRule('course', get_string('course'), 'required');
        if ($courseid = $mform->optional_param('course', null, PARAM_RAW)) {
            $institutionarray = [0 => get_string('allparticipants')];
            $instituions = $this->get_institutions($courseid);
            foreach ($instituions as $instituion) {
                $institutionarray["$instituion->institution"] = $instituion->institution;
            }
            $mform->addElement('autocomplete', 'institution', get_string('institution'), $institutionarray);

            $coursegroups = groups_get_all_groups($courseid);
            $coursegrouparray = [0 => get_string('allparticipants')];
            foreach ($coursegroups as $coursegroup) {
                $coursegrouparray[$coursegroup->id] = $coursegroup->name;
            }
            $mform->addElement('autocomplete', 'group', get_string('group'), $coursegrouparray);
        }

        $this->add_action_buttons(false, get_string('search'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['course'])) {
            $errors['course'] = get_string("coursesincolumnshelp", "report_learneractivity");
        }
        return $errors;
    }

    public function get_institutions($courseid) {
        global $DB;
        $this->context = \context_course::instance($courseid);
        list($relatedctxsql, $relatedctxparams) = $DB->get_in_or_equal($this->context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'relatedctx');
        $sqlstart = "SELECT ";
        $sqlwhat = "u.id, u.institution ";
        $sqlfrom = "FROM {user} u ";
        $sqlwhere = "";
        $sqlgroup = "GROUP BY u.institution ";

        $sqlinner = " JOIN (
                           SELECT DISTINCT ra.userid
                             FROM {role_assignments} ra
                            WHERE ra.roleid IN ($this->gradebookroles)
                              AND ra.contextid $relatedctxsql
                       ) rainner ON rainner.userid = u.id ";
        $sqlorder = "ORDER BY u.institution ";
        $sql = $sqlstart . $sqlwhat . $sqlfrom . $sqlinner . $sqlwhere . $sqlgroup . $sqlorder;
        return $DB->get_records_sql($sql, $relatedctxparams);
    }

}
