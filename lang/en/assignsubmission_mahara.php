<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'assignsubmission_mahara', language 'en'
 *
 * @package   assignsubmission_mahara
 * @copyright 2014 Lancaster University (@link http://www.lancaster.ac.uk/)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['assign_submission_mahara_name'] = 'Assign Submission Mahara services';
$string['assign_submission_mahara_description'] = 'Mahara functions used in Mahara portfolio Assign Submission plugin.<br />Publishing this service on a Moodle site has no effect. Subscribe to this service if you want to be able to use assignments with {$a}.<br />';
$string['default'] = 'Enabled by default';
$string['default_help'] = 'If set, this submission method will be enabled by default for all new assignments.';
$string['enabled'] = 'Mahara portfolio';
$string['enabled_help'] = "If enabled, students are able to submit Mahara pages for assessment in Moodle. The Mahara site must already be configured for mnet networking with this Moodle site.";
$string['emptysubmission'] = 'You have not chosen the page to submit.';
$string['errormnetrequest'] = 'Attempt to send mnet request resulted in error: {$a}';
$string['eventassessableuploaded'] = 'A Mahara page or collection has been submitted.';
$string['vieworcollectionalreadysubmitted'] = 'The selected Mahara view or collection could not be submitted. Please choose another.';
$string['mahara'] = 'Mahara portfolio';
$string['needstobelocked'] = 'Draft submission either needs to be submitted for assessment by user or locked before view link will be available.';
$string['nomaharahostsfound'] = 'No mahara hosts found.';
$string['noviewscreated'] = 'No available pages or collections found.';
$string['option_collections'] = 'Collections';
$string['option_views'] = 'Views';
$string['pluginname'] = 'Mahara portfolio';
$string['previousattemptsnotvisible'] = 'Previous attempts with the Mahara submission plugin are not visible.';
$string['selectmaharaview'] = 'Select one of your "{$a->name}" portfolio pages from this complete list, or <a href="{$a->jumpurl}">click here</a> to visit "{$a->name}" and create a page.';
$string['site'] = 'Site';
$string['site_help'] = 'This setting lets you select which Mahara site your students should submit their pages from.';
$string['outputforlog'] = '{$a->remotehostname}: {$a->viewtitle} (view id: {$a->viewid})';
$string['outputforlognew'] = 'New {$a} submission.';
$string['viewsaved'] = '<a href="{$a->jumpurl}">Click here</a> to view "{$a->viewtitle}" page in "{$a->name}" portfolio.';
