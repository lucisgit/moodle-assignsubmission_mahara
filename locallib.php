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
 * This file contains the definition for the library class for Mahara submission plugin
 *
 * This class provides all the functionality for the new assign module.
 *
 * @package    assignsubmission_mahara
 * @copyright  2012 Lancaster University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * library class for Mahara submission plugin extending submission plugin base class
 *
 * @package    assignsubmission_mahara
 * @copyright  2012 Lancaster University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_mahara extends assign_submission_plugin {

    /**
     * Get the name of the Mahara submission plugin
     *
     * @return string
     */
    public function get_name() {
        return get_string('mahara', 'assignsubmission_mahara');
    }

   /**
    * Get Mahara submission information from the database
    *
    * @param  int $submissionid
    * @return mixed
    */
    private function get_mahara_submission($submissionid) {
        global $DB;

        return $DB->get_record('assignsubmission_mahara', array('submission'=>$submissionid));
    }

    /**
     * Get the settings form for Mahara submission plugin
     *
     * @global stdClass $CFG
     * @global stdClass $DB
     * @param MoodleQuickForm $mform The form to add elements to
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        global $CFG, $DB;

        $defaultmnethostid = $this->get_config('mnethostid');

        // Get Mahara hosts we are doing SSO with.
        $sql = "
             SELECT DISTINCT
                 h.id,
                 h.name
             FROM
                 {mnet_host} h,
                 {mnet_application} a,
                 {mnet_host2service} h2s_IDP,
                 {mnet_service} s_IDP,
                 {mnet_host2service} h2s_SP,
                 {mnet_service} s_SP
             WHERE
                 h.id != :mnet_localhost_id AND
                 h.id = h2s_IDP.hostid AND
                 h.deleted = 0 AND
                 h.applicationid = a.id AND
                 h2s_IDP.serviceid = s_IDP.id AND
                 s_IDP.name = 'sso_idp' AND
                 h2s_IDP.publish = '1' AND
                 h.id = h2s_SP.hostid AND
                 h2s_SP.serviceid = s_SP.id AND
                 s_SP.name = 'sso_idp' AND
                 h2s_SP.publish = '1' AND
                 a.name = 'mahara'
             ORDER BY
                 h.name";

        if ($hosts = $DB->get_records_sql($sql, array('mnet_localhost_id'=>$CFG->mnet_localhost_id))) {
            // Some hosts found, build select element.
            foreach ($hosts as &$h) {
                $h = $h->name;
            }
            $mform->addElement('select', 'assignsubmission_mahara_mnethostid', get_string('site', 'assignsubmission_mahara'), $hosts);
            $mform->setDefault('assignsubmission_mahara_mnethostid', $defaultmnethostid);
            $mform->disabledIf('assignsubmission_mahara_mnethostid', 'assignsubmission_mahara_enabled', 'eq', 0);
        } else {
            // No hosts found.
            $mform->addElement('static', 'assignsubmission_mahara_mnethostid', get_string('site', 'assignsubmission_mahara'), get_string('nomaharahostsfound', 'assignsubmission_mahara'));
            $mform->updateElementAttr('assignsubmission_mahara_enabled', array('disabled' => true));
        }
        $mform->addHelpButton('assignsubmission_mahara_mnethostid', 'site', 'assignsubmission_mahara');
    }

    /**
     * Save the settings for Mahara plugin
     *
     * @param stdClass $data
     * @return bool
     */
    public function save_settings(stdClass $data) {
        $this->set_config('mnethostid', $data->assignsubmission_mahara_mnethostid);
        return true;
    }

    /**
     * Add elements to user submission form
     *
     * @param mixed $submission stdClass|null
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @param int $userid
     * @global stdClass $DB
     * @global stdClass $CFG
     * @return bool
     */
    public function get_form_elements_for_user($submission, MoodleQuickForm $mform, stdClass $data, $userid) {
        global $DB, $CFG;

        $submissionid = $submission ? $submission->id : 0;
        $maharasubmission = $this->get_mahara_submission($submissionid);
        // Getting views (pages) user have in linked site.
        list($error, $views) = $this->get_views();
        if ($error) {
            throw new moodle_exception('errorgettingviews', 'assignsubmission_mahara', '', $error);
        }

        $remotehost = $DB->get_record('mnet_host', array('id'=>$this->get_config('mnethostid')));
        $remotehost->jumpurl = $CFG->wwwroot . '/auth/mnet/jump.php?hostid=' . $remotehost->id;
        // Updating section header and adding description line.
        $mform->getElement('header_mahara')->_text = $remotehost->name;
        $mform->addElement('static', '', '', get_string('selectmaharaview', 'assignsubmission_mahara', $remotehost));

        if ($views['count'] == 0) {
            // No pages found.
            $mform->addElement('static', '', '', get_string('noviewscreated', 'assignsubmission_mahara'));
        } else {
            // Build select element containing user pages
            $selectitems = array();
            foreach ($views['data'] as $view) {
                $selectitems[$view['id']] = $view['title'];
            }
            $mform->addElement('select', 'viewid', '', $selectitems);
            if ($maharasubmission) {
                $mform->setDefault('viewid', $maharasubmission->viewid);
            }
        }
        return true;
    }

    /**
     * Retrieve user views from Mahara portfolio.
     *
     * @param string $query Search query
     * @global stdClass $CFG
     * @global stdClass $USER
     * @return array
     */
    function get_views($query = '') {
        global $CFG, $USER;

        $error = false;
        $viewdata = array();
        if (!is_enabled_auth('mnet')) {
            $error = get_string('authmnetdisabled', 'mnet');
        } else if (!has_capability('moodle/site:mnetlogintoremote', get_context_instance(CONTEXT_SYSTEM), NULL, false)) {
            $error = get_string('notpermittedtojump', 'mnet');
        } else {
            // Set up the RPC request.
            require_once $CFG->dirroot . '/mnet/xmlrpc/client.php';
            $mnet_sp = $this->get_mnet_peer();
            $mnetrequest = new mnet_xmlrpc_client();
            $mnetrequest->set_method('mod/mahara/rpclib.php/get_views_for_user');
            $mnetrequest->add_param($USER->username);
            $mnetrequest->add_param($query);

            if ($mnetrequest->send($mnet_sp) === true) {
                $viewdata = $mnetrequest->response;
            } else {
                $error = "RPC mod/mahara/rpclib.php/get_views_for_user:<br/>";
                foreach ($mnetrequest->error as $errormessage) {
                    list($code, $errormessage) = array_map('trim',explode(':', $errormessage, 2));
                    $error .= "ERROR $code:<br/>$errormessage<br/>";
                }
            }
        }
        return array($error, $viewdata);
    }

    /**
     * Get mnet peer object
     *
     * @global stdClass $CFG
     * @return stdClass
     */
    function get_mnet_peer() {
        global $CFG;
        require_once $CFG->dirroot . '/mnet/peer.php';
        $mnetpeer = new mnet_peer();
        $mnetpeer->set_id($this->get_config('mnethostid'));
        return $mnetpeer;
    }

     /**
      * Save submission data to the database
      *
      * @param stdClass $submission
      * @param stdClass $data
      * @global stdClass $DB
      * @global stdClass $CFG
      * @global stdClass $USER
      * @return bool
      */
     public function save(stdClass $submission, stdClass $data) {
        global $DB, $CFG, $USER;

        $maharasubmission = $this->get_mahara_submission($submission->id);

        require_once $CFG->dirroot . '/mnet/xmlrpc/client.php';
        $mnet_sp = $this->get_mnet_peer();
        $mnetrequest = new mnet_xmlrpc_client();
        $mnetrequest->set_method('mod/mahara/rpclib.php/submit_view_for_assessment');
        $mnetrequest->add_param($USER->username);
        $mnetrequest->add_param($data->viewid);

        if ($mnetrequest->send($mnet_sp) !== true) {
            return false;
        }
        $mnetresponse = $mnetrequest->response;

        if ($maharasubmission) {
            $maharasubmission->viewid = $data->viewid;
            $maharasubmission->viewurl = $mnetresponse['url'];
            $maharasubmission->viewtitle = clean_text($mnetresponse['title']);
            return $DB->update_record('assignsubmission_mahara', $maharasubmission);
        } else {
            $maharasubmission = new stdClass();
            $maharasubmission->viewid = $data->viewid;
            $maharasubmission->viewurl = $mnetresponse['url'];
            $maharasubmission->viewtitle = clean_text($mnetresponse['title']);

            $maharasubmission->submission = $submission->id;
            $maharasubmission->assignment = $this->assignment->get_instance()->id;
            return $DB->insert_record('assignsubmission_mahara', $maharasubmission) > 0;
        }
    }

    /**
     * Check if submission has been made
     *
     * @param stdClass $submission
     * @return bool
     */
    public function is_empty(stdClass $submission) {
        $maharasubmission = $this->get_mahara_submission($submission->id);

        return empty($maharasubmission);
    }

     /**
      * Display onlinetext word count in the submission status table
      *
      * @param stdClass $submission
      * @param bool $showviewlink - If the summary has been truncated set this to true
      * @return string
      */
    public function view_summary(stdClass $submission, & $showviewlink) {
        global $OUTPUT, $DB;

        $maharasubmission = $this->get_mahara_submission($submission->id);

        // Instead of letting Moodle generate the the view link,
        // we will substitute own preview link in the summary output.
        if ($maharasubmission) {
            $remotehost = $DB->get_record('mnet_host', array('id'=>$this->get_config('mnethostid')));
            $url = new moodle_url('/auth/mnet/jump.php', array('hostid' => $remotehost->id, 'wantsurl' => $maharasubmission->viewurl));
            $icon = $OUTPUT->pix_icon('t/preview', get_string('view' . substr($this->get_subtype(), strlen('assign')), 'mod_assign'));
            $link = $OUTPUT->action_link(new moodle_url($url), $icon);
            $link .= $OUTPUT->spacer(array('width'=>15));

            return $link . $maharasubmission->viewtitle;
        }
        return '';
    }

    /**
     * Produce a list of files suitable for export that represent this submission
     *
     * @param stdClass $submission - For this is the submission data
     * @return array - return an array of files indexed by filename
     */
    public function get_files(stdClass $submission) {
        global $DB;
        $files = array();
        $onlinetextsubmission = $this->get_onlinetext_submission($submission->id);
        if ($onlinetextsubmission) {
            $user = $DB->get_record("user", array("id"=>$submission->userid),'id,username,firstname,lastname', MUST_EXIST);

            $prefix = clean_filename(fullname($user) . "_" .$submission->userid . "_");
            $finaltext = str_replace('@@PLUGINFILE@@/', $prefix, $onlinetextsubmission->onlinetext);
            $submissioncontent = "<html><body>". format_text($finaltext, $onlinetextsubmission->onlineformat, array('context'=>$this->assignment->get_context())). "</body></html>";      //fetched from database

            $files[get_string('onlinetextfilename', 'assignsubmission_onlinetext')] = array($submissioncontent);

            $fs = get_file_storage();

            $fsfiles = $fs->get_area_files($this->assignment->get_context()->id, 'assignsubmission_onlinetext', ASSIGNSUBMISSION_ONLINETEXT_FILEAREA, $submission->id, "timemodified", false);

            foreach ($fsfiles as $file) {
                $files[$file->get_filename()] = $file;
            }
        }

        return $files;
    }

    /**
     * Display the saved text content from the editor in the view table
     *
     * @param stdClass $submission
     * @return string
     */
    public function view(stdClass $submission) {
        $result = '';

        $maharasubmission = $this->get_mahara_submission($submission->id);

        if ($maharasubmission) {
            // render for portfolio API
            $result .= $maharasubmission->viewurl;

        }

        return $result;
    }

     /**
     * Return true if this plugin can upgrade an old Moodle 2.2 assignment of this type and version.
     *
     * @param string $type old assignment subtype
     * @param int $version old assignment version
     * @return bool True if upgrade is possible
     */
    public function can_upgrade($type, $version) {
        if ($type == 'mahara' && $version >= 2010102600) {
            return true;
        }
        return false;
    }


    /**
     * Upgrade the settings from the old assignment to the new plugin based one
     *
     * @param context $oldcontext - the database for the old assignment context
     * @param stdClass $oldassignment - the database for the old assignment instance
     * @param string $log record log events here
     * @return bool Was it a success?
     */
    public function upgrade_settings(context $oldcontext, stdClass $oldassignment, & $log) {
        // first upgrade settings (nothing to do)
        return true;
    }

    /**
     * Upgrade the submission from the old assignment to the new one
     *
     * @param context $oldcontext - the database for the old assignment context
     * @param stdClass $oldassignment The data record for the old assignment
     * @param stdClass $oldsubmission The data record for the old submission
     * @param stdClass $submission The data record for the new submission
     * @param string $log Record upgrade messages in the log
     * @return bool true or false - false will trigger a rollback
     */
    public function upgrade(context $oldcontext, stdClass $oldassignment, stdClass $oldsubmission, stdClass $submission, & $log) {
        global $DB;

        $onlinetextsubmission = new stdClass();
        $onlinetextsubmission->onlinetext = $oldsubmission->data1;
        $onlinetextsubmission->onlineformat = $oldsubmission->data2;

        $onlinetextsubmission->submission = $submission->id;
        $onlinetextsubmission->assignment = $this->assignment->get_instance()->id;

        if ($onlinetextsubmission->onlinetext === null) {
            $onlinetextsubmission->onlinetext = '';
        }

        if ($onlinetextsubmission->onlineformat === null) {
            $onlinetextsubmission->onlineformat = editors_get_preferred_format();
        }

        if (!$DB->insert_record('assignsubmission_onlinetext', $onlinetextsubmission) > 0) {
            $log .= get_string('couldnotconvertsubmission', 'mod_assign', $submission->userid);
            return false;
        }

        // now copy the area files
        $this->assignment->copy_area_files_for_upgrade($oldcontext->id,
                                                        'mod_assignment',
                                                        'submission',
                                                        $oldsubmission->id,
                                                        // New file area
                                                        $this->assignment->get_context()->id,
                                                        'assignsubmission_onlinetext',
                                                        ASSIGNSUBMISSION_ONLINETEXT_FILEAREA,
                                                        $submission->id);
        return true;
    }

    /**
     * Formatting for log info
     *
     * @param stdClass $submission The new submission
     * @return string
     */
    public function format_for_log(stdClass $submission) {
        // Format the info for each submission plugin add_to_log
        $maharasubmission = $this->get_mahara_submission($submission->id);
        return get_string('outputforlog', 'assignsubmission_mahara', $maharasubmission);
    }

    /**
     * The assignment has been deleted - cleanup
     *
     * @return bool
     */
    public function delete_instance() {
        global $DB;
        // Will throw exception on failure.
        $DB->delete_records('assignsubmission_mahara', array('assignment'=>$this->assignment->get_instance()->id));

        return true;
    }
}
