<?php
/**
 * Report and manage file usage within a site
 *
 * Settings and links
 *
 * @package    report_fileusage
 * @copyright  2015 onwards, Pukunui
 * @author     Shane Elliott <shane@pukunui.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$ADMIN->add('reports', new admin_externalpage('report_fileusage', get_string('pluginname', 'report_fileusage'), "$CFG->wwwroot/report/fileusage/index.php",'report/fileusage:view'));
