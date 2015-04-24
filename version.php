<?php
// This file is a plugin for Moodle - http://moodle.org/
// Developed by EcoMerc - http://code.ecomerc.com/
//
/**
 * Stripe enrolment plugin version specification.
 *
 * @package    enrol_stripe
 * @copyright  2015 EcoMerc
 * @author     EcoMerc - based on paypalplugin code by Petr Skoda, Eugene Venter and others
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2015042300;        // The current plugin version (Date: YYYYMMDDXX)
$plugin->requires  = 2014110400;        // Requires this Moodle version
$plugin->component = 'enrol_stripe';    // Full name of the plugin (used for diagnostics)
$plugin->cron      = 60;
$plugin->maturity = MATURITY_BETA;
$plugin->release = 'v0.9';
