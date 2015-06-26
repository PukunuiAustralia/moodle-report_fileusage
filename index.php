<?php
/**
 * Report and manage file usage within a site
 *
 * Main landing page
 *
 * @package    report_fileusage
 * @copyright  2015 onwards, Pukunui
 * @author     Shane Elliott <shane@pukunui.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('locallib.php');

require_login();
$context = context_system::instance();
require_capability('report/fileusage:view', $context);

$report       = optional_param('report', '', PARAM_ALPHA);
$delete       = optional_param('delete', 0, PARAM_INT);
$bulkdelete   = optional_param('bulkdelete', 0, PARAM_INT);
$bulkdownload = optional_param('bulkdownload', 0, PARAM_INT);
$confirm      = optional_param('confirm', '', PARAM_ALPHANUM);

$strpluginname = get_string('pluginname', 'report_fileusage');
$returnurl     = '/report/fileusage/index.php';
$reporttypes = array('backup' => get_string('userbackupfiles', 'report_fileusage'));


$PAGE->set_url($returnurl);
$PAGE->set_pagelayout('report');
$PAGE->navbar->add($strpluginname, new moodle_url($returnurl));
$PAGE->set_context($context);
$PAGE->set_heading($strpluginname);


if ($report == 'backup') {

    // Deal with any file deletions
    if (!empty($delete) and ($confirm == md5($USER->sesskey.$delete))) {
        if (report_fileusage_delete_file($delete)) {
            redirect(new moodle_url($returnurl, array('report'=>$report)), get_string('filedeleted', 'report_fileusage'));
            exit;
        }
    }

    // Deal with user bulk file deletions.
    if (!empty($bulkdelete) and ($confirm == md5($USER->sesskey.$bulkdelete))) {
        if ($userfiles = report_fileusage_get_backup_file_list($bulkdelete)) {
            $deletecount = 0;
            foreach ($userfiles as $uf) {
                if (report_fileusage_delete_file($uf->id)) {
                    $deletecount += 1;
                }
            }
            redirect(new moodle_url('/report/fileusage', array('report'=>$report)), get_string('bulkfilesdeleted', 'report_fileusage', $deletecount));
            exit;
        }
    }

    // Bulk download of backup files.
    if (!empty($bulkdownload)) {
        report_fileusage_download_backup_files($bulkdownload);
        // We should never here as exit has been called. But just in case...
        redirect(new moodle_url($returnurl, array('report'=>$report)));
        exit;
    }
}



echo $OUTPUT->header();

echo $OUTPUT->box(get_string('plugindescription', 'report_fileusage'));
echo $OUTPUT->render(new single_select(new moodle_url($returnurl), 'report', $reporttypes, $report));
if (!empty($report)) {
    echo $OUTPUT->heading($reporttypes[$report], 3);
}

switch ($report) {
    case 'backup':
        if (!empty($delete)) {
            echo $OUTPUT->confirm(get_string('confirmfiledelete', 'report_fileusage'),
                                  new moodle_url($returnurl, array('report'  => $report,
                                                                   'delete'  => $delete,
                                                                   'confirm' => md5($USER->sesskey.$delete))),
                                  new moodle_url($returnurl, array('report' => $report)));
        } else if (!empty($bulkdelete)) {
            echo $OUTPUT->confirm(get_string('confirmbulkfiledelete', 'report_fileusage'),
                                  new moodle_url($returnurl, array('report'      => $report,
                                                                   'bulkdelete'  => $bulkdelete,
                                                                   'confirm'     => md5($USER->sesskey.$bulkdelete))),
                                  new moodle_url($returnurl, array('report' => $report)));
        } else {
            echo html_writer::table(report_fileusage_get_backup_file_usage_table());
        }
        break;
}

echo $OUTPUT->footer();
