<?php
/**
 * Report and manage file usage within a site
 *
 * Local library functions
 *
 * @package    report_fileusage
 * @copyright  2015 onwards, Pukunui
 * @author     Shane Elliott <shane@pukunui.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Retrieve a list of users with fileusage
 *
 * @return mixed
 */
function report_fileusage_get_backup_file_usage() {
    global $DB;

    $sql = "SELECT ".$DB->sql_fullname('u.firstname', 'u.lastname')." AS fullname, f.userid, SUM(f.filesize) AS fs
            FROM {files} f
            JOIN {user} u ON u.id = f.userid
            WHERE f.component='user'
                AND f.filearea='backup'
                AND f.filesize>0
            GROUP BY fullname, f.userid
            ORDER BY fs DESC";

    return $DB->get_records_sql($sql);
}

/**
 * Return a list of backup files (and associated courses) for a given user id
 *
 * @param integer $userid  id fromt he user table
 * @return mixed
 */
function report_fileusage_get_backup_file_list($userid) {
    global $DB;

    return $DB->get_records('files',
                            array('component' => 'user',
                                  'filearea'  => 'backup',
                                  'userid'    => $userid),
                            'contextid'
                           );
}

/**
 * Given user data, return a table
 *
 * @param array $userdata
 * @return html_table
 */
function report_fileusage_get_backup_file_usage_table() {
    global $OUTPUT, $USER;

    $userdata = report_fileusage_get_backup_file_usage();

    $table = new html_table();
    $table->head = array(get_string('fullname'), get_string('fileusagebackups', 'report_fileusage'), '');
    $table->data = array();

    foreach ($userdata as $ud) {
        $list = array();
        if ($filelist = report_fileusage_get_backup_file_list($ud->userid)) {
            $uft = new html_table();
            $uft->data = array();

            foreach ($filelist as $fl) {
                if ($fl->filesize == 0) {
                    continue;
                }
                $uftrow = new html_table_row();
                $uftrow->cells[] = $fl->filename;
                $uftrow->cells[] = report_fileusage_human_filesize($fl->filesize);
                $uftrow->cells[] = html_writer::link(new moodle_url('/report/fileusage/index.php',
                                                                    array('report' => 'backup',
                                                                          'delete' => $fl->id)),
                                                     $OUTPUT->pix_icon('t/delete', get_string('delete')));
                $uftrow->cells[] = html_writer::link(new moodle_url("/pluginfile.php/$fl->contextid/$fl->component/$fl->filearea/$fl->filename"),
                                                     $OUTPUT->pix_icon('t/download', get_string('download')));
                $uft->data[] = $uftrow;
            }
        }

        $bulkdeletebutton = new single_button(new moodle_url('/report/fileusage/index.php',
                                                             array('report'     => 'backup',
                                                                   'bulkdelete' => $ud->userid,
                                                                   'confirm'    => md5($USER->sesskey.$ud->userid)
                                                                  )),
                                              get_string('bulkdelete', 'report_fileusage'));
        $bulkdeletebutton->add_confirm_action(get_string('confirmbulkfiledelete', 'report_fileusage'));

        $bulkdownloadbutton = new single_button(new moodle_url('/report/fileusage/index.php',
                                                               array('report' => 'backup',
                                                                     'bulkdownload' => $ud->userid)
                                                               ),
                                                get_string('bulkdownload', 'report_fileusage'));

        $table->data[] = array($ud->fullname,
                               report_fileusage_human_filesize($ud->fs).
                                              $OUTPUT->render($bulkdeletebutton).
                                              '<br />'.
                                              $OUTPUT->render($bulkdownloadbutton),
                               html_writer::table($uft));
    }

    return $table;
}

/**
 * Delete a file given the id from the files table
 *
 * @param integer $fileid  id from files table
 * @return boolean
 */
function report_fileusage_delete_file($fileid) {
    global $DB;

    $result = false;
    if ($filerec = $DB->get_record('files', array('id' => $fileid), 'component,filearea,itemid,contextid,filepath,filename')) {
        $fs = get_file_storage();
        if ($file = $fs->get_file($filerec->contextid,
                                  $filerec->component,
                                  $filerec->filearea,
                                  $filerec->itemid,
                                  $filerec->filepath,
                                  $filerec->filename)
                                 ) {
            $result = $file->delete();
        }
    }

    return $result;
}

/**
 * Change bytes into human readable form
 *
 * @param integer $bytes
 * @param integer $dec  number of decimal places to display
 * @return string
 */
function report_fileusage_human_filesize($bytes, $dec = 2) 
{
    $size   = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $factor = floor((strlen($bytes) - 1) / 3);

    return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

/**
 * Generate zip file from array of given files.
 *
 * Copied from mod/assign/locallib.php
 *
 * @param array $filesforzipping - array of files to pass into archive_to_pathname.
 *                                 This array is indexed by the final file name and each
 *                                 element in the array is an instance of a stored_file object.
 * @return path of temp file - note this returned file does
 *         not have a .zip extension - it is a temp file.
 */
function report_fileusage_pack_files($filesforzipping) {
    global $CFG;
    // Create path for new zip file.
    $tempzip = tempnam($CFG->tempdir . '/', 'report_fileusage_');
    // Zip files.
    $zipper = new zip_packer();
    if ($zipper->archive_to_pathname($filesforzipping, $tempzip)) {
        return $tempzip;
    }
    return false;
}

/**
 * Generate a zip file of all the backups file for a given user id
 *
 * @param integer $userid
 * @return void
 */
function report_fileusage_download_backup_files($userid) {
    $filesforzipping = array();
    if ($filerecs = report_fileusage_get_backup_file_list($userid)) {
        foreach ($filerecs as $filerec) {
            if ($filerec->filesize == 0) {
                continue;
            }

            $fs = get_file_storage();
            if ($file = $fs->get_file($filerec->contextid,
                        $filerec->component,
                        $filerec->filearea,
                        $filerec->itemid,
                        $filerec->filepath,
                        $filerec->filename)
               ) {
                $filesforzipping[$filerec->filename] = $file;
            }
        }

        if ($zipfile = report_fileusage_pack_files($filesforzipping)) {
            send_temp_file($zipfile, "report_fileusage-backups-$userid-".date("Ymd-His").'.zip');
        }
    }
}
