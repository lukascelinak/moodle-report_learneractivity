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

use DateTime;
use context;
use moodle_url;
use core_user\output\status_field;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/grade/report/lib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once("{$CFG->libdir}/completionlib.php");

/**
 * Class for the displaying the table.
 *
 * @package     report_learneractivity
 * @copyright   2021 Lukas Celinak <lukascelinak@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learneractivity_table extends \table_sql {

    public $course;
    public $completion;
    public $activities;
    public $group;

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
    public function init_table($courseid, $group = null, $institution = null) {
        global $DB, $CFG;
        $this->course = $DB->get_record('course', array('id' => $courseid));
        $this->context = \context_course::instance($courseid);
        if ($group != 0) {
            $this->group = $group;
        }
        if (!empty($institution)) {
            $this->institution = $institution;
        }
        // Get criteria for course
        $this->completion = new \completion_info($this->course);
        $this->groups = groups_get_all_groups($this->course->id, 0, 0, 'g.*', true);
        // Retrieve course_module data for all modules in the course
        $this->activities = $this->completion->get_activities();
        $this->gtree = new \grade_tree($this->course->id, true);
        $this->modinfo = get_fast_modinfo($this->course);
        $this->gradebookroles = $CFG->gradebookroles;
        $this->allgradeitems = $this->get_allgradeitems();
        $sqlitems = "SELECT gi.*,cm.section FROM {grade_items} gi "
                . "INNER JOIN {modules} m ON gi.itemmodule=m.name "
                . "LEFT JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = gi.iteminstance "
                . "WHERE gi.courseid=:courseid AND gi.itemtype LIKE \"mod\" ORDER BY cm.section ASC ";
        $params = ['courseid' => $this->course->id];
        $this->moduleitems = $DB->get_records_sql($sqlitems, $params);
        $this->setup_groups();
    }

    /**
     * Render the table.
     *
     * @param int $pagesize Size of page for paginated displayed table.
     * @param bool $useinitialsbar Whether to use the initials bar which will only be used if there is a fullname column defined.
     * @param string $downloadhelpbutton
     */
    public function out($pagesize, $useinitialsbar, $downloadhelpbutton = '') {
        global $DB;
        $this->downloadable = true;
        $this->set_attribute('class', 'table-bordered');
        // Define the headers and columns.
        $headers = [get_string('firstname'),
            get_string('lastname'),
            get_string('idnumber'),
            get_string('institution'),
            get_string('department'),
            get_string('team', 'report_learneractivity'),
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
        $this->no_sorting("suspended");
        $extrafields = [];

        foreach ($this->moduleitems as $moduleitem) {
            if($moduleitem->hidden !=1){
                $cm = get_coursemodule_from_instance($moduleitem->itemmodule, $moduleitem->iteminstance);
            $category = $DB->get_record('course_sections', array('id' => $moduleitem->section));
            $extrafields[] = "activitid_" . $cm->id;
            $categoryname = $this->is_downloading() ? $category->name . ": " : "<span class=\"badge badge-primary\">{$category->name}</span><br/>";
            $headers[] = $categoryname . $this->modinfo->cms[$cm->id]->get_formatted_name();
            $columns[] = "activitid_" . $cm->id;
            $this->no_sorting("activitid_" . $cm->id);}
            
        }

        $this->define_columns($columns);
        $this->define_headers($headers);

        // Make this table sorted by last name by default.
        $this->sortable(true, 'lastname');
        $this->extrafields = $extrafields;
        //$this->set_attribute('id', 'learneractivity');
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
     * Generate the status column.
     *
     * @param \stdClass $data The data object.
     * @return string
     */
    public function col_suspended($data) {
        $context = \context_course::instance($this->course->id);
        if (is_enrolled($context, $data->id, '', true)) {
            return get_string('no');
        } else {
            return get_string('yes');
        }
    }

    /**
     * Generate the last access column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_lastaccess($data) {
        if ($data->lastaccess) {
            return userdate($data->lastaccess, get_string('strftimedatetimeshort'));
        }

        return get_string('never');
    }

    /**
     * Generate the fullname column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_team($data) {
        return $data->{get_config('report_learneractivity', 'teamcustomfield')};
    }

    /**
     * Generate the fullname column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_group($data) {
        $displayvalue = get_string('groupsnone');
        $usergroups = [];
        foreach ($this->groups as $coursegroup) {
            if (isset($coursegroup->members[$data->id])) {
                $usergroups[] = $coursegroup->name;
            }
        }

        if (!empty($usergroups)) {
            $displayvalue = implode(', ', $usergroups);
        } else {
            $$displayvalue = get_string('groupsnone');
        }
        return $displayvalue;
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
        $rawdata = $this->get_users($this->get_sql_sort(), $this->get_page_start(), $this->get_page_size(), $this->course->id);

        $this->rawdata = [];
        foreach ($rawdata as $user) {
            foreach ($this->moduleitems as $moduleitem) {
                if($moduleitem->hidden !=1){
                $cm = get_coursemodule_from_instance($moduleitem->itemmodule, $moduleitem->iteminstance);
                switch ($moduleitem->itemmodule) {
                    case 'quiz':
                        $sql = "SELECT a.*
                                FROM {quiz_attempts} a
                                WHERE a.quiz=:quizid AND a.userid=:userid ORDER BY a.id ASC LIMIT 1";
                        $params = ['quizid' => $moduleitem->iteminstance, 'userid' => $user->id];
                        if ($quizgrade = $DB->get_record_sql($sql, $params)) {
                            if (is_null($quizgrade->sumgrades)) {
                                $completionstate = get_string('inprogress', 'report_learneractivity');
                            } else {
                                $completionstate = get_string('completed', 'report_learneractivity');
                            }
                        } else {
                            $completionstate = '';
                        }
                        break;
                        case 'assign':
                        $sql = "SELECT s.*,g.grade,g.grader,g.timecreated as gradecreated,g.timemodified as grademodified 
                                FROM {assign_submission} s
                                LEFT JOIN {assign_grades} g ON g.assignment=s.assignment AND g.userid = s.userid
                                WHERE s.assignment=:assignmentid AND s.userid=:userid AND s.latest=1 ORDER BY s.id ASC LIMIT 1";
                        $params = ['assignmentid' => $moduleitem->iteminstance, 'userid' => $user->id];
                        if ($quizgrade = $DB->get_record_sql($sql, $params)) {
                            if (is_null($quizgrade->grade)) {
                                switch ($quizgrade->status) {
                                    case "new":
                                         $completionstate = get_string('inprogress', 'report_learneractivity');

                                        break;
                                    case "submitted":
                                         $completionstate = get_string('readyforgrade', 'report_learneractivity');

                                        break;

                                    default:
                                        "-";
                                        break;
                                }
                               
                            } else {
                                $completionstate = get_string('completed', 'report_learneractivity');
                            }
                        } else {
                            $completionstate = '';
                        }
                        break;
                    default:
                        $completionstate = '';
                        break;
                }

//            
//            foreach ($this->activities as $moduleitem) {
//                $completiondata = $this->completion->get_data($moduleitem, false, $user->id);
//                if ($completiondata->completionstate == 0 && $this->user_viewed_activity($moduleitem->id, $user->id)) {
//                    $completiontype = 'inprogress';
//                } elseif ($completiondata->completionstate > 0) {
//                    switch ($completiondata->completionstate) {
//                        case COMPLETION_INCOMPLETE :
//                            $completiontype = 'notcompleted';
//                            break;
//                        case COMPLETION_COMPLETE :
//                            $completiontype = 'completed';
//                            break;
//                        case COMPLETION_COMPLETE_PASS :
//                            $completiontype = 'completed';
//                            break;
//                        case COMPLETION_COMPLETE_FAIL :
//                            $completiontype = 'notcompleted';
//                            break;
//                    }
//                } else {
//                    $completiontype = null;
//                }
//
//
//                $completionstate = $completiontype ? get_string($completiontype, 'report_learneractivity') : "-";
//
                $fieldname = "activitid_" . $cm->id;
                $user->$fieldname = $completionstate;}
                
            }
            $customfields = profile_user_record((int) $user->id);
            $user = (object) array_merge((array) $user, (array) $customfields);
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

        if ($this->group > 0) {
            $groupsql = $this->groupsql;
            $groupwheresql = $this->groupwheresql;
            $groupwheresqlparams = $this->groupwheresql_params;
        } else {
            $groupsql = "";
            $groupwheresql = "";
            $groupwheresqlparams = array();
        }
        list($relatedctxsql, $relatedctxparams) = $DB->get_in_or_equal($this->context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'relatedctx');

        $sqlstart = "SELECT COUNT(u.id) ";
        $sqlfrom = "FROM {user} u ";
        $sqlinner = " JOIN (
                           SELECT DISTINCT ra.userid
                             FROM {role_assignments} ra
                            WHERE ra.roleid IN ($this->gradebookroles)
                              AND ra.contextid $relatedctxsql
                       ) rainner ON rainner.userid = u.id {$groupsql} ";
        $sqlwhere = "WHERE 1 ";
        $sqlwhere .= "{$groupwheresql} ";
        $sqlgroup = "";
        $sqlwhere .= property_exists($this, "institution") && !empty($this->institution) ? "AND u.institution LIKE \"{$this->institution}\" " : "";
        $sql = $sqlstart . $sqlfrom . $sqlinner . $sqlwhere . $sqlgroup;
        $params = array_merge($relatedctxparams, $groupwheresqlparams);
        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Query users for table.
     */
    public function get_users($sort, $start, $size, $courseid) {
        global $DB;
        if ($this->group > 0) {
            $groupsql = $this->groupsql;
            $groupwheresql = $this->groupwheresql;
            $groupwheresqlparams = $this->groupwheresql_params;
        } else {
            $groupsql = "";
            $groupwheresql = "";
            $groupwheresqlparams = array();
        }

        //$sqlwhere = property_exists($this, "institution")&&$this->institution > 0 ? "u.institution LIKE \"{$this->institution}\" ":"1 ";

        list($relatedctxsql, $relatedctxparams) = $DB->get_in_or_equal($this->context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'relatedctx');
        $sqlstart = "SELECT ";
        $sqlwhat = "u.* ";
        $sqlfrom = "FROM {user} u ";
        $sqlwhere = "WHERE 1 ";
        $sqlwhere .= "{$groupwheresql} ";
        $sqlgroup = "";
        $sqlwhere .= property_exists($this, "institution") && !empty($this->institution) ? "AND u.institution LIKE \"{$this->institution}\" " : "";

        $sqlinner = " JOIN (
                           SELECT DISTINCT ra.userid
                             FROM {role_assignments} ra
                            WHERE ra.roleid IN ($this->gradebookroles)
                              AND ra.contextid {$relatedctxsql}
                       ) rainner ON rainner.userid = u.id {$groupsql} ";
        $sqlorder = "ORDER BY {$sort} ";
        $sql = $sqlstart . $sqlwhat . $sqlfrom . $sqlinner . $sqlwhere . $sqlgroup . $sqlorder;
        $params = array_merge($relatedctxparams, $groupwheresqlparams);
        return $DB->get_records_sql($sql, $params, $start, $size);
    }

    public function user_viewed_activity($cmid, $userid) {
        global $DB;
        $sql = "SELECT id, action, userid,timecreated FROM {logstore_standard_log} "
                . "WHERE userid=:userid AND contextinstanceid=:cmid AND realuserid IS NULL AND action NOT LIKE \"failed\" "
                . "ORDER BY id ASC, timecreated ASC LIMIT 1";
        $params = ['userid' => $userid, 'cmid' => $cmid];
        if ($loggedinrows = $DB->get_record_sql($sql, $params)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Load all grade items.
     */
    protected function get_allgradeitems() {
        if (!empty($this->allgradeitems)) {
            return $this->allgradeitems;
        }
        $allgradeitems = \grade_item::fetch_all(array('courseid' => $this->course->id));
        // But hang on - don't include ones which are set to not show the grade at all.
        $this->allgradeitems = array_filter($allgradeitems, function($item) {
            return $item->gradetype != GRADE_TYPE_NONE;
        });

        return $this->allgradeitems;
    }

    /**
     * Sets up this object's group variables, mainly to restrict the selection of users to display.
     */
    protected function setup_groups() {
        // find out current groups mode         
        if ($this->group) {
            if ($group = groups_get_group($this->group)) {
                $this->currentgroupname = $group->name;
            }
            $this->groupsql = " JOIN {groups_members} gm ON gm.userid = u.id ";
            $this->groupwheresql = " AND gm.groupid = :gr_grpid ";
            $this->groupwheresql_params = array('gr_grpid' => $this->group);
        }
    }

}
