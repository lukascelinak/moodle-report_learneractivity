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
 * The Monthly Grade Report.
 *
 * @package     report_learneractivity
 * @category    admin
 * @copyright   2021 Lukas Celinak <lukascelinak@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\report_helper;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once("{$CFG->libdir}/completionlib.php");

require_login();
$context = context_system::instance();
require_capability('report/learneractivity:view', $context);

$download = optional_param('download', '', PARAM_ALPHA);

// Paging params for paging bars.
$page = optional_param('page', 0, PARAM_INT); // Which page to show.
$pagesize = optional_param('perpage', 25, PARAM_INT); // How many per page.

$url = new moodle_url('/report/learneractivity/index.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
admin_externalpage_setup('report_learneractivity', '', null, '', array('pagelayout' => 'report'));
$PAGE->set_title(get_string('pluginname', 'report_learneractivity'));
$PAGE->set_heading(get_string('pluginname', 'report_learneractivity'));


$mform = new \report_learneractivity\form\search();
$courseid = null;
/** @var cache_session $cache */
$cache = cache::make_from_params(cache_store::MODE_SESSION, 'report_learneractivity', 'search');
if ($cachedata = $cache->get('data')) {
    $mform->set_data($cachedata);
}

// Check if we have a form submission, or a cached submission.
$data = ($mform->is_submitted() ? $mform->get_data() : fullclone($cachedata));
if ($data instanceof stdClass) {
    $courseid = !empty($data->course) ? $data->course : null;
    // Cache form submission so that it is preserved while paging through the report.
    unset($data->submitbutton);
    $cache->set('data', $data);
}

$mtable = new \report_learneractivity\table\learneractivity_table('reportlearneractivitytable');

if ($courseid) {
    $course = $DB->get_record('course', array('id' => $courseid));
    $mtable->is_downloading($download, $course->fullname . " - " . get_string('pluginname', 'report_learneractivity') . " - " . date('d-M-Y g-i a'), 'learneractivityexport');
    $mtable->define_baseurl($url);
}

if (!$mtable->is_downloading()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('pluginname', 'report_learneractivity'));
    $mform->display();
}

if ($courseid) {
    $mtable->init_table($courseid);
    ob_start();
    $mtable->out($pagesize, false);
    $mtablehtml = ob_get_contents();
    ob_end_clean();
}

if (!$mtable->is_downloading()) {
    echo html_writer::tag(
            'p',
            get_string('userstotal', 'report_learneractivity', $mtable->totalrows),
            [
                'data-region' => 'reportlearneractivitytable-count',
            ]
    );
}

if (!$mtable->is_downloading() && $courseid) {
    echo $mtablehtml;
}

if (!$mtable->is_downloading()) {
    echo $OUTPUT->footer();
}