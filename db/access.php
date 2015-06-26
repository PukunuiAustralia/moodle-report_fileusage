<?php
/**
 * Report and manage file usage within a site
 *
 * Capability definitions
 *
 * @package    report_fileusage
 * @copyright  2015 onwards, Pukunui
 * @author     Shane Elliott <shane@pukunui.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$capabilities = array(

    'report/fileusage:view' => array(
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        ),
    )

);

