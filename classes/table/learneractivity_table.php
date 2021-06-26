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
 * The Monthly Grade Report table class.
 *
 * @package     report_learneractivity
 * @copyright   2021 Lukas Celinak <lukascelinak@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
declare(strict_types=1);

namespace report_learneractivity\table;

use moodle_url;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/user/lib.php');

/**
 * Class for the displaying the table.
 *
 * @package     report_learneractivity
 * @copyright   2021 Lukas Celinak <lukascelinak@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learneractivity_table extends \table_sql {

    public $couseid;

    /**
     * Sets up the table.
     *
     * @param int $courseid
     * @param int|false $currentgroup False if groups not used, int if groups used, 0 all groups, USERSWITHOUTGROUP for no group
     * @param int $accesssince The time the user last accessed the site
     * @param int $roleid The role we are including, 0 means all enrolled users
     * @param int $enrolid The applied filter for the user enrolment ID.
     * @param int $status The applied filter for the user's enrolment status.
     * @param string|array $search The search string(s)
     * @param bool $bulkoperations Is the user allowed to perform bulk operations?
     * @param bool $selectall Has the user selected all users on the page?
     */
    public function init_table($courseid) {
        global $DB;
        $this->course = $DB->get_record('course',array('id'=>$courseid));
        
             // Get criteria for course
        $this->completion = new completion_info($this->course);
   
        // Retrieve course_module data for all modules in the course
        $this->activities =  $this->completion->get_activities();
        print_object(activities);

    }

    /**
     * Render the table.
     *
     * @param int $pagesize Size of page for paginated displayed table.
     * @param bool $useinitialsbar Whether to use the initials bar which will only be used if there is a fullname column defined.
     * @param string $downloadhelpbutton
     */
    public function out($pagesize, $useinitialsbar, $downloadhelpbutton = '') {
        $this->downloadable = true;
        $this->set_attribute('class', 'table-bordered');
        // Define the headers and columns.
        $headers = [get_string('firstname'),
            get_string('lastname'),
            get_string('idnumber'),
            get_string('institution'),
            get_string('department'),
            get_string('team'),
            get_string('group'),
            get_string('email'),
            get_string('suspended'),
            get_string('lastaccess')];

        $columns = ['firstname',
            'lastname',
            'idnumber',
            'institution',
            'department',
            'team',
            'group',
            'email',
            'suspended',
            'lastaccess'];
        // $this->gtree = new grade_tree($this->courseid);
        $this->no_sorting("team");
        $this->no_sorting("group");
        $extrafields = [];
        
        foreach ($this->activities as $moduleitem) {
            $extrafields[] = $moduleitem->id;
            $headers[] = $moduleitem->id;
            $columns[] = $moduleitem->id;
            $this->no_sorting($moduleitem->id);
 }

        $this->define_columns($columns);
        $this->define_headers($headers);

        // Make this table sorted by last name by default.
        $this->sortable(true, 'lastname');
        $this->extrafields = $extrafields;
        $this->set_attribute('id', 'learneractivity');
        parent::out($pagesize, $useinitialsbar, $downloadhelpbutton);
    }

    /**
     * Generate the fullname column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_email($data) {
        if ($this->is_downloading()) {
            return $data->email;
        } else {
            return '<a href="mailto:">' . $data->email . '</a>';
        }
    }

    /**
     * Generate the fullname column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_firstname($data) {
        return $data->firstname;
    }

    /**
     * Generate the fullname column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_idnumber($data) {
        return $data->idnumber;
    }

    /**
     * Generate the fullname column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_lastname($data) {
        return $data->lastname;
    }

    /**
     * Generate the fullname column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_institution($data) {
        return $data->institution;
    }

    /**
     * Generate the fullname column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_department($data) {
       return $data->department;
    }

    
        /**
     * Generate the fullname column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_suspended($data) {
        return $data->suspended;
    }
    
        /**
     * Generate the fullname column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_lastaccess($data) {
        return $data->lastaccess;
    }
    /**
     * Generate the fullname column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_team($data) {
        return "";//$data->{get_config('report_learneractivity', 'teamcustomfield')};
    }
    
        /**
     * Generate the fullname column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_group($data) {
        return "";//$data->{get_config('report_learneractivity', 'companyidcustomfield')};
    }

    /**
     * Generate the email column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_fullname($data) {
        global $OUTPUT;
        $userurl = new moodle_url('/user/view.php', array('id' => $data->id));
        if ($this->is_downloading()) {
            return fullname($data);
        } else {
            return '<a href="' . $userurl->out() . '">' . $OUTPUT->user_picture($data, array('size' => 35, 'includefullname' => true, 'link' => false)) . '</a>';
        }
    }

    /**
     * This function is used for the extra user fields.
     *
     * These are being dynamically added to the table so there are no functions 'col_<userfieldname>' as
     * the list has the potential to increase in the future and we don't want to have to remember to add
     * a new method to this class. We also don't want to pollute this class with unnecessary methods.
     *
     * @param string $colname The column name
     * @param \stdClass $data
     * @return string
     */
    public function other_cols($colname, $data) {
        // Do not process if it is not a part of the extra fields.
        if (!in_array($colname, $this->extrafields)) {
            return '';
        }
        return $data->{$colname};
    }

    /**
     * Query the database for results to display in the table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar.
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;

        //Count all users.
        $total = $this->count_users();

        if ($this->is_downloading()) {
            $this->pagesize($total, $total);
        } else {
            $this->pagesize($pagesize, $total);
        }

        //Get users data.
        $rawdata = $this->get_users($this->get_sql_sort(), $this->get_page_start(), $this->get_page_size(), $this->courseid);

        $this->rawdata = [];
        foreach ($rawdata as $user) {
            foreach ( $this->activities as $moduleitem) {
                $completionstate = "-";

                $fieldname = $moduleitem->id;
        
                $user->$fieldname = $completionstate;

                $customfields = profile_user_record($user->id);
                $user = (object) array_merge((array) $user, (array) $customfields);
            }
            $this->rawdata[$user->id] = $user;
        }

        // Set initial bars.
        if ($useinitialsbar) {
            $this->initialbars(true);
        }
    }

    /**
     * Override the table show_hide_link to not show for select column.
     *
     * @param string $column the column name, index into various names.
     * @param int $index numerical index of the column.
     * @return string HTML fragment.
     */
    protected function show_hide_link($column, $index) {
        return '';
    }

    /**
     * Guess the base url for the participants table.
     */
    public function guess_base_url(): void {
        $this->baseurl = new moodle_url('/report/learneractivity/index.php');
    }

    /**
     * Query users for table.
     */
    public function count_users() {
        global $DB;
        $sqlstart = "SELECT COUNT(u.id) ";
        $sqlfrom = "FROM {user} u ";
        $sqlinner = "INNER JOIN {role_assignments} ra ON u.id = ra.userid AND ra.roleid =5 ";
        $sqlinner .= "LEFT JOIN {context} ct ON ct.id = ra.contextid ";
        $sqlinner .= "INNER JOIN {course} c ON c.id = ct.instanceid AND c.id =:courseid ";
        $sqlinner .= "INNER JOIN {user_enrolments} ue ON ue.id = u.id LEFT JOIN {enrol} e ON ue.enrolid = e.id AND e.courseid = c.id ";
        $sqlwhere .= "AND c.id =:courseid2 ";
        $sql = $sqlstart . $sqlfrom . $sqlinner . $sqlwhere;
        $params['courseid'] = $this->course->id;
        $params['courseid2'] = $this->course->id;
        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Query users for table.
     */
    public function get_users($sort, $start, $size, $courseid) {
        global $DB;
        $params = [];
        $sqlstart = "SELECT ";
        $sqlwhat = "u.* ";
        $sqlfrom = "FROM {user} u ";
        $sqlinner = "INNER JOIN {role_assignments} ra ON u.id = ra.userid AND ra.roleid =:studentrole ";
        $sqlinner .= "LEFT JOIN {context} ct ON ct.id = ra.contextid ";
        $sqlinner .= "INNER JOIN {course} c ON c.id = ct.instanceid AND c.id =:courseid ";
        $sqlinner .= "INNER JOIN {user_enrolments} ue ON ue.id = u.id LEFT JOIN {enrol} e ON ue.enrolid = e.id AND e.courseid = c.id ";

        $sqlwhere .= "AND c.id =:courseid2 ";
        $sqlgroup = "GROUP BY u.id ";
        $sqlorder = "ORDER BY {$sort} ";

        $sql = $sqlstart . $sqlwhat . $sqlfrom . $sqlinner . $sqlwhere . $sqlgroup . $sqlorder;
        $params['studentrole'] = get_config('report_learneractivity', 'studentrole');
        $params['courseid'] = $courseid;
        $params['courseid2'] = $courseid;

        return $DB->get_records_sql($sql, $params, $start, $size);
    }

}
