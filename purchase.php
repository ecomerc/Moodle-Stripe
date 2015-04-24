<?php
// This file is a plugin for Moodle - http://moodle.org/
// Developed by EcoMerc - http://code.ecomerc.com/
//
/**
 * Listens for Instant Payment Notification from stripe
 *
 * This script waits for Payment notification from stripe,
 * then double checks that data by sending it back to stripe.
 * If stripe verifies this then it sets up the enrolment for that
 * user.
 * 
 * @package    enrol_stripe
 * @copyright  2015 EcoMerc
 * @author     EcoMerc - based on stripeplugin code by Petr Skoda, Eugene Venter and others
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 
require("../../config.php");
require_once("$CFG->dirroot/enrol/stripe/lib.php");
require_once("$CFG->dirroot/enrol/stripe/purchase.functions.php");
require_once("$CFG->dirroot/enrol/stripe/lib/stripe/init.php");

require_once($CFG->libdir.'/eventslib.php');
require_once($CFG->libdir.'/enrollib.php');
require_once($CFG->libdir . '/filelib.php');

$id = required_param('id', PARAM_INT);

if (!$course = $DB->get_record("course", array("id"=>$id))) {
    redirect($CFG->wwwroot);
}

$context = context_course::instance($course->id, MUST_EXIST);
$PAGE->set_context($context);

require_login();

if (!empty($SESSION->wantsurl)) {
    $destination = $SESSION->wantsurl;
    unset($SESSION->wantsurl);
} else {
    $destination = "$CFG->wwwroot/course/view.php?id=$course->id";
}

$fullname = format_string($course->fullname, true, array('context' => $context));


// Set your secret key: remember to change this to your live secret key in production
// See your keys here https://dashboard.stripe.com/account/apikeys
\Stripe\Stripe::setApiKey("sk_test_XkeaNWxzWeIM3YRJG55ADPeq");

// Get the credit card details submitted by the form
$token = $_POST['stripeToken'];

// Create the charge on Stripe's servers - this will charge the user's card
try {
	
	
	$data = new stdClass();

	foreach ($_POST as $key => $value) {
		$data->$key = $value;
	}

	$custom = explode('-', $data->custom);
	$data->userid           = (int)$custom[0];
	$data->courseid         = (int)$custom[1];
	$data->instanceid       = (int)$custom[2];
	$data->timeupdated      = time();
	$data->txn_id  			= $data->stripeToken; //yes yes, this is stupid we should simple just store it in token, but this was a rookie mistake - so live with it.
		
	if ($id != $data->courseid) {
		message_stripe_error_to_admin("Parameter error", $data);
		die;
	}
	
	if ($USER->id != $data->userid) {
		message_stripe_error_to_admin("Parameter error", $data);
		die;
	}	
	if (! $course = $DB->get_record("course", array("id"=>$data->courseid))) {
		message_stripe_error_to_admin("Not a valid course id", $data);
		die;
	}	
	if (! $context = context_course::instance($course->id, IGNORE_MISSING)) {
		message_stripe_error_to_admin("Not a valid context id", $data);
		die;
	}
	if (! $plugin_instance = $DB->get_record("enrol", array("id"=>$data->instanceid, "status"=>0))) {
		message_stripe_error_to_admin("Not a valid instance id", $data);
		die;
	}
	$plugin = enrol_get_plugin('stripe');
	
	
	if ( (float) $plugin_instance->cost <= 0 ) {
		$cost = (float) $plugin->get_config('cost');
	} else {
		$cost = (float) $plugin_instance->cost;
	}

	if (abs($cost) < 0.01) { // no cost, other enrolment methods (instances) should be used
		message_stripe_error_to_admin("No cost for course", $data);
		die;
	}
	
	if ($existing = $DB->get_record("enrol_stripe", array("txn_id"=>$data->txn_id))) {   // Make sure this transaction doesn't exist already
		message_stripe_error_to_admin("Transaction $data->txn_id is being repeated!", $data);
		die;
	}
	
	$data->payment_gross    = $cost;
	$data->payment_currency = $plugin_instance->currency;
	
	
	$charge = \Stripe\Charge::create(array(
	  "amount" => ($cost * 100), // amount in cents, again
	  "currency" => $plugin_instance->currency,
	  "source" => $token,
	  "description" => $course->fullname)
	);



	$DB->insert_record("enrol_stripe", $data);

	if ($plugin_instance->enrolperiod) {
		$timestart = time();
		$timeend   = $timestart + $plugin_instance->enrolperiod;
	} else {
		$timestart = 0;
		$timeend   = 0;
	}

	// Enrol user
	$plugin->enrol_user($plugin_instance, $USER->id, $plugin_instance->roleid, $timestart, $timeend);

	// Pass $view=true to filter hidden caps if the user cannot see them
	if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
										 '', '', '', '', false, true)) {
		$users = sort_by_roleassignment_authority($users, $context);
		$teacher = array_shift($users);
	} else {
		$teacher = false;
	}

	$mailstudents = $plugin->get_config('mailstudents');
	$mailteachers = $plugin->get_config('mailteachers');
	$mailadmins   = $plugin->get_config('mailadmins');
	$shortname = format_string($course->shortname, true, array('context' => $context));


	if (!empty($mailstudents)) {
		$a = new stdClass();
		$a->coursename = format_string($course->fullname, true, array('context' => $context));
		$a->profileurl = "$CFG->wwwroot/user/view.php?id=$USER->id";

		$eventdata = new stdClass();
		$eventdata->modulename        = 'moodle';
		$eventdata->component         = 'enrol_stripe';
		$eventdata->name              = 'stripe_enrolment';
		$eventdata->userfrom          = empty($teacher) ? core_user::get_support_user() : $teacher;
		$eventdata->userto            = $USER;
		$eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
		$eventdata->fullmessage       = get_string('welcometocoursetext', '', $a);
		$eventdata->fullmessageformat = FORMAT_PLAIN;
		$eventdata->fullmessagehtml   = '';
		$eventdata->smallmessage      = '';
		message_send($eventdata);

	}

	if (!empty($mailteachers) && !empty($teacher)) {
		$a->course = format_string($course->fullname, true, array('context' => $context));
		$a->user = fullname($USER);

		$eventdata = new stdClass();
		$eventdata->modulename        = 'moodle';
		$eventdata->component         = 'enrol_stripe';
		$eventdata->name              = 'stripe_enrolment';
		$eventdata->userfrom          = $USER;
		$eventdata->userto            = $teacher;
		$eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
		$eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
		$eventdata->fullmessageformat = FORMAT_PLAIN;
		$eventdata->fullmessagehtml   = '';
		$eventdata->smallmessage      = '';
		message_send($eventdata);
	}

	if (!empty($mailadmins)) {
		$a->course = format_string($course->fullname, true, array('context' => $context));
		$a->user = fullname($USER);
		$admins = get_admins();
		foreach ($admins as $admin) {
			$eventdata = new stdClass();
			$eventdata->modulename        = 'moodle';
			$eventdata->component         = 'enrol_stripe';
			$eventdata->name              = 'stripe_enrolment';
			$eventdata->userfrom          = $USER;
			$eventdata->userto            = $admin;
			$eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
			$eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
			$eventdata->fullmessageformat = FORMAT_PLAIN;
			$eventdata->fullmessagehtml   = '';
			$eventdata->smallmessage      = '';
			message_send($eventdata);
		}
	}

	
	
    redirect($destination, get_string('paymentthanks', '', $fullname));



} catch(\Stripe\Error\Card $e) {
  // The card has been declined
	$data->exception = $e;
  
	$DB->insert_record("enrol_stripe", $data, false);
	message_stripe_error_to_admin("Received an invalid payment notification!! (Fake payment?)", $data);

    $PAGE->set_url($destination);
    echo $OUTPUT->header();
    $a = new stdClass();
    $a->teacher = get_string('defaultcourseteacher');
    $a->fullname = $fullname;
    notice(get_string('paymentsorry', '', $a), $destination);
}


