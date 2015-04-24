<?php
// This file is a plugin for Moodle - http://moodle.org/
// Developed by EcoMerc - http://code.ecomerc.com/
//
/**
 * Stripe enrolments plugin settings and presets.
 *
 * @package    enrol_stripe
 * @copyright  2015 EcoMerc
 * @author     EcoMerc - based on paypalplugin code by Petr Skoda, Eugene Venter and others
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    //--- settings ------------------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_stripe_settings', '', get_string('pluginname_desc', 'enrol_stripe')));

    $settings->add(new admin_setting_configtext('enrol_stripe/stripeapikey', get_string('stripekey', 'enrol_stripe'), get_string('stripekey_desc', 'enrol_stripe'), '', PARAM_ALPHANUMEXT));

    $settings->add(new admin_setting_configcheckbox('enrol_stripe/mailstudents', get_string('mailstudents', 'enrol_stripe'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_stripe/mailteachers', get_string('mailteachers', 'enrol_stripe'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_stripe/mailadmins', get_string('mailadmins', 'enrol_stripe'), '', 0));

    // Note: let's reuse the ext sync constants and strings here, internally it is very similar,
    //       it describes what should happen when users are not supposed to be enrolled any more.
    $options = array(
        ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
    );
    $settings->add(new admin_setting_configselect('enrol_stripe/expiredaction', get_string('expiredaction', 'enrol_stripe'), get_string('expiredaction_help', 'enrol_stripe'), ENROL_EXT_REMOVED_SUSPENDNOROLES, $options));

    //--- enrol instance defaults ----------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_stripe_defaults',
        get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

    $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                     ENROL_INSTANCE_DISABLED => get_string('no'));
    $settings->add(new admin_setting_configselect('enrol_stripe/status',
        get_string('status', 'enrol_stripe'), get_string('status_desc', 'enrol_stripe'), ENROL_INSTANCE_DISABLED, $options));

    $settings->add(new admin_setting_configtext('enrol_stripe/cost', get_string('cost', 'enrol_stripe'), '', 0, PARAM_FLOAT, 4));

    $stripecurrencies = enrol_get_plugin('stripe')->get_currencies();
    $settings->add(new admin_setting_configselect('enrol_stripe/currency', get_string('currency', 'enrol_stripe'), '', 'USD', $stripecurrencies));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_stripe/roleid',
            get_string('defaultrole', 'enrol_stripe'), get_string('defaultrole_desc', 'enrol_stripe'), $student->id, $options));
    }

    $settings->add(new admin_setting_configduration('enrol_stripe/enrolperiod',
        get_string('enrolperiod', 'enrol_stripe'), get_string('enrolperiod_desc', 'enrol_stripe'), 0));
}
