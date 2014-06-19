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
    * @global stdClass $DB
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
            $mform->disabledIf('assignsubmission_mahara_mnethostid', 'assignsubmission_mahara_enabled', 'notchecked');
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
     * @global stdClass $DB
     * @param mixed $submission stdClass|null
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @param int $userid
     * @return bool
     */
    public function get_form_elements_for_user($submission, MoodleQuickForm $mform, stdClass $data, $userid) {
        global $DB;

        // Getting submission.
        if ($submission) {
            $maharasubmission = $this->get_mahara_submission($submission->id);
        }
        // Getting views (pages) user have in linked site.
        $views = $this->mnet_get_views();

        if ($views) {
            // Filter out collection views, special views, and already-submitted views
            foreach ($views['data'] as $i => $view) {
                if ($view['collid'] || $view['submittedtime'] || $view['type'] != 'portfolio') {
                    unset($views['ids'][$i]);
                    unset($views['data'][$i]);
                    $views['count']--;
                }
            }
            // Filter out submitted collections
            foreach ($views['collections']['data'] as $i => $coll) {
                if (
                        $coll['submittedtime']
                        || (
                                array_key_exists('numviews', $coll)
                                && $coll['numviews'] == 0
                        )
                ) {
                    unset($views['collections']['data'][$i]);
                    $views['collections']['count']--;
                }
            }
        }
        $viewids = $views['ids'];

        // Prepare the header.
        $remotehost = $DB->get_record('mnet_host', array('id'=>$this->get_config('mnethostid')));
        $url = new moodle_url('/auth/mnet/jump.php', array('hostid' => $remotehost->id));
        $remotehost->jumpurl = $url->out();
        // Add label and description line.
        $mform->addElement('static', 'description', $remotehost->name, get_string('selectmaharaview', 'assignsubmission_mahara', $remotehost));

        // See if any of views are already in use, we will remove them from select.
        if (count($viewids) || count($views['collections']['data'])) {
            $viewoptions = array();
            foreach ($views['data'] as $view) {
                $viewoptions['v' . $view['id']] = $view['title'];
            }
            $colloptions = array();
            foreach ($views['collections']['data'] as $coll) {
                $colloptions['c' . $coll['id']] = $coll['name'];
            }

            $viewstr = get_string('option_views', 'assignsubmission_mahara');
            $collstr = get_string('option_collections', 'assignsubmission_mahara');
            $options = array();

            if ($viewoptions) {
                $options[$viewstr] = $viewoptions;
            }
            if ($colloptions) {
                $options[$collstr] = $colloptions;
            }

            $mform->addElement('selectgroups', 'viewid', '', $options);
            if (!empty($maharasubmission)) {
                if ($maharasubmission->iscollection) {
                    $prefix = 'c';
                } else {
                    $prefix = 'v';
                }
                $mform->setDefault('viewid', $prefix . $maharasubmission->viewid);
            }
            return true;
        }

        // No pages found.
        $mform->addElement('static', '', '', get_string('noviewscreated', 'assignsubmission_mahara'));
        return true;
    }

    /**
     * Retrieve user views from Mahara portfolio.
     *
     * @global stdClass $USER
     * @param string $query Search query
     * @return mixed
     */
    public function mnet_get_views($query = '') {
        global $USER;
        return $this->mnet_send_request('get_views_for_user', array($USER->username, $query));
    }

    /**
     * Submit view or collection for assessment in Mahara. This marks the view/collection
     * as "submitted", creates an access token, and locks the view/collection from editing
     * or further submissions in Mahara.
     *
     * @global stdClass $USER
     * @param stdClass $submission The submission record (used for verification)
     * @param int $viewid Id of the view or collection to submit
     * @param boolean $iscollection True if it's a collection, False if not
     * @param $viewownermoodleid ID of the view ower's Moodle user record
     * @return mixed
     */
    public function mnet_submit_view($submission, $viewid, $iscollection, $viewownermoodleid = null) {
        global $USER, $DB;

        // Verify that it's not already submitted to another Mahara assignment in this Moodle site.
        // We can't do this on the Mahara side, because Mahara only knows the remote site's wwwroot.
        if (
                $DB->record_exists_select(
                        'assignsubmission_mahara',
                        'viewid = ? AND iscollection = ? AND viewaccesskey IS NOT NULL AND submission != ?',
                        array(
                                $viewid,
                                ($iscollection ? 1 : 0),
                                $submission->id
                        )
                )
        ) {
            throw new moodle_exception('vieworcollectionalreadysubmitted', 'assignsubmission_mahara');
        }

        if (!$viewownermoodleid) {
            $username = $USER->username;
        }
        else {
            $username = $DB->get_field('user', 'username', array('id'=>$viewownermoodleid));
        }
        return $this->mnet_send_request('submit_view_for_assessment', array($username, $viewid, $iscollection));
    }

    /**
     * Release submitted view for assessment.
     *
     * @global stdClass $USER
     * @param int $viewid View or Collection ID
     * @param array $viewoutcomes Outcomes data
     * @param boolean $iscollection Whether the $viewid is a view or a collection
     * @return mixed
     */
    public function mnet_release_submited_view($viewid, $viewoutcomes, $iscollection = false) {
        global $USER;
        return $this->mnet_send_request('release_submitted_view', array($viewid, $viewoutcomes, $USER->username, $iscollection));
    }

    /**
     * Send Mnet request to Mahara portfolio.
     *
     * @global stdClass $CFG
     * @param string $methodname name of remote method to call
     * @param array $parameters list of method parameters
     * @return mixed $responsedata Mnet response
     */
    private function mnet_send_request($methodname, $parameters) {
        global $CFG;

        $error = false;
        $responsedata = false;
        if (!is_enabled_auth('mnet')) {
            $error = get_string('authmnetdisabled', 'mnet');
        } else if (!has_capability('moodle/site:mnetlogintoremote', context_system::instance(), NULL, false)) {
            $error = get_string('notpermittedtojump', 'mnet');
        } else {
            // Set up the RPC request.
            require_once $CFG->dirroot . '/mnet/xmlrpc/client.php';
            require_once $CFG->dirroot . '/mnet/peer.php';
            $mnetpeer = new mnet_peer();
            $mnetpeer->set_id($this->get_config('mnethostid'));
            $mnetrequest = new mnet_xmlrpc_client();
            $mnetrequest->set_method('mod/mahara/rpclib.php/' . $methodname);
            foreach ($parameters as $parameter) {
                $mnetrequest->add_param($parameter);
            }

            if ($mnetrequest->send($mnetpeer) === true) {
                $responsedata = $mnetrequest->response;
            } else {
                $error = "RPC mod/mahara/rpclib.php/" . $methodname . ":<br/>";
                foreach ($mnetrequest->error as $errormessage) {
                    list($code, $errormessage) = array_map('trim',explode(':', $errormessage, 2));
                    $error .= "ERROR $code:<br/>$errormessage<br/>";
                }
            }
        }
        if ($error) {
            $this->set_error($error);
        }
        return $responsedata;
    }

     /**
      * Save submission data to the database
      *
      * @global stdClass $DB
      * @param stdClass $submission
      * @param stdClass $data
      * @return bool
      */
     public function save(stdClass $submission, stdClass $data) {
        global $DB;

        // Because the drop-down menu contains collections & views, we make the id
        // start with "v" or "c" to indicate the type, e.g. v30, c100
        $iscollection = ($data->viewid[0] == 'c');
        $data->viewid = substr($data->viewid, 1);

        $maharasubmission = $this->get_mahara_submission($submission->id);
        if ($submission->status === ASSIGN_SUBMISSION_STATUS_DRAFT) {
            // Draft. All we need to do is just save or update submitted view data.
            if (!$views = $this->mnet_get_views()) {
                // Wrap recorded error in language string and return false.
                $this->set_error(get_string('errormnetrequest', 'assignsubmission_mahara', $this->get_error()));
                return false;
            }
            if ($iscollection) {
                $foundcoll = false;
                if (!is_array($views['collections']['data'])) {
                    return false;
                }
                foreach ($views['collections']['data'] as $coll) {
                    if ($coll['id'] == $data->viewid) {
                        $foundcoll = true;
                        $url = $coll['url'];
                        $title = clean_text($coll['name']);
                        break;
                    }
                }
                // The submitted collection id isn't one of the allowed options for this user
                if (!$foundcoll) {
                    return false;
                }
            } else {
                $keys = array_flip($views['ids']);
                // The submitted view id isn't one of the allowed options for this user
                if (!array_key_exists($data->viewid, $keys)) {
                    return false;
                }
                $viewdata = $views['data'][$keys[$data->viewid]];
                $url = $viewdata['url'];
                $title = clean_text($viewdata['title']);
            }

            if ($maharasubmission) {
                $maharasubmission->viewid = $data->viewid;
                $maharasubmission->viewurl = $url;
                $maharasubmission->viewtitle = $title;
                $maharasubmission->iscollection = (int) $iscollection;
                return $DB->update_record('assignsubmission_mahara', $maharasubmission);
            } else {
                $maharasubmission = new stdClass();
                $maharasubmission->viewid = $data->viewid;
                $maharasubmission->viewurl = $url;
                $maharasubmission->viewtitle = $title;
                $maharasubmission->iscollection = (int) $iscollection;

                $maharasubmission->submission = $submission->id;
                $maharasubmission->assignment = $this->assignment->get_instance()->id;
                return $DB->insert_record('assignsubmission_mahara', $maharasubmission) > 0;
            }
        } else {
            // This is not the draft, but the actual submission. Process it properly.
            // Lock submission on mahara side.
            if (!$response = $this->mnet_submit_view($submission, $data->viewid, $iscollection)) {
                throw new moodle_exception('errormnetrequest', 'assignsubmission_mahara', '', $this->get_error());
            }

            if ($maharasubmission) {
                // If we are updating previous submission, release previous submission first.
                if ($maharasubmission->viewid != $data->viewid) {
                    if ($this->mnet_release_submited_view($maharasubmission->viewid, array(), $maharasubmission->iscollection) === false) {
                        throw new moodle_exception('errormnetrequest', 'assignsubmission_mahara', '', $this->get_error());
                    }
                }
                // Update submission data.
                $maharasubmission->viewid = $data->viewid;
                $maharasubmission->viewurl = $response['url'];
                $maharasubmission->viewtitle = clean_text($response['title']);
                $maharasubmission->viewaccesskey = $response['accesskey'];
                $maharasubmission->iscollection = (int) $iscollection;
                return $DB->update_record('assignsubmission_mahara', $maharasubmission);
            } else {
                // We are dealing with the new submission.
                $maharasubmission = new stdClass();
                $maharasubmission->viewid = $data->viewid;
                $maharasubmission->viewurl = $response['url'];
                $maharasubmission->viewtitle = clean_text($response['title']);
                $maharasubmission->viewaccesskey = $response['accesskey'];
                $maharasubmission->iscollection = (int) $iscollection;

                $maharasubmission->submission = $submission->id;
                $maharasubmission->assignment = $this->assignment->get_instance()->id;
                return $DB->insert_record('assignsubmission_mahara', $maharasubmission) > 0;
            }
        }
    }

    /**
     * Check if the submission plugin has all the required data to allow the work
     * to be submitted for grading
     * @param stdClass $submission the assign_submission record being submitted.
     * @return bool|string 'true' if OK to proceed with submission, otherwise a
     *                        a message to display to the user
     */
    public function precheck_submission($submission) {
        $maharasubmission = $this->get_mahara_submission($submission->id);
        if (!$maharasubmission) {
            return get_string('emptysubmission', 'assignsubmission_mahara');
        }
        return true;
    }

     /**
      * Process submission for grading
      *
      * @global stdClass $DB
      * @param stdClass $submission
      * @return void
      */
    public function submit_for_grading($submission) {
        global $DB;

        // If the submission has been locked in the gradebook, then it has already been submitted on the Mahara side
        $flags = $this->assignment->get_user_flags($submission->userid, false);
        if ($flags && $flags->locked == 1) {
            return;
        }

        $maharasubmission = $this->get_mahara_submission($submission->id);
        // Lock view on Mahara side as it has been submitted for assessment.
        if (!$response = $this->mnet_submit_view($submission, $maharasubmission->viewid, $maharasubmission->iscollection)) {
            throw new moodle_exception('errormnetrequest', 'assignsubmission_mahara', '', $this->get_error());
        }
        $maharasubmission->viewurl = $response['url'];
        $maharasubmission->viewaccesskey = $response['accesskey'];
        $DB->update_record('assignsubmission_mahara', $maharasubmission);
    }

    /**
      * Process locking
      *
      * @global stdClass $DB
      * @param stdClass $submission
      * @return void
      */
    public function lock($submission, stdClass $flags = null) {
        global $DB;

        // If it's in submitted status, then it has already been locked
        if ($submission->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
            return;
        }

        $maharasubmission = $this->get_mahara_submission($submission->id);

        // If no page is selected, then we don't need to do anything special here.
        if (!$maharasubmission || !$maharasubmission->viewid) {
            return;
        }

        // Lock view on Mahara side as it has been submitted for assessment.
        if (!$response = $this->mnet_submit_view($submission, $maharasubmission->viewid, $maharasubmission->iscollection, $submission->userid)) {
            throw new moodle_exception('errormnetrequest', 'assignsubmission_mahara', '', $this->get_error());
        }
        $maharasubmission->viewurl = $response['url'];
        $maharasubmission->viewaccesskey = $response['accesskey'];
        $DB->update_record('assignsubmission_mahara', $maharasubmission);
    }

    /**
      * Process unlocking
      *
      * @global stdClass $DB
      * @param stdClass $submission
      * @return void
      */
    public function unlock($submission, stdClass $flags = null) {
        global $DB;

        // If it has been submitted, it needs to remain locked
        if ($submission->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
            return;
        }

        $maharasubmission = $this->get_mahara_submission($submission->id);

        // If no page is selected, then we don't need to do anything special here.
        if (!$maharasubmission || !$maharasubmission->viewid) {
            return;
        }

        // Unlock view on Mahara side as it has been unlocked.
        if ($this->mnet_release_submited_view($maharasubmission->viewid, array(), $maharasubmission->iscollection) === false) {
            throw new moodle_exception('errormnetrequest', 'assignsubmission_mahara', '', $this->get_error());
        }
        $maharasubmission->viewaccesskey = '';
        $DB->update_record('assignsubmission_mahara', $maharasubmission);
    }

    /**
      * Process reverting to draft
      *
      * @global stdClass $DB
      * @param stdClass $submission
      * @return void
      */
    public function revert_to_draft(stdClass $submission) {
        global $DB;

        // If the submission has been locked in the gradebook, then we don't want to release it on the Mahara side
        $flags = $this->assignment->get_user_flags($submission->userid, false);
        if ($flags && $flags->locked == 1) {
            return;
        }

        $maharasubmission = $this->get_mahara_submission($submission->id);
        // Unlock view on Mahara side as it has been reverted to draft.
        if ($this->mnet_release_submited_view($maharasubmission->viewid, array(), $maharasubmission->iscollection) === false) {
            throw new moodle_exception('errormnetrequest', 'assignsubmission_mahara', '', $this->get_error());
        }
        $maharasubmission->viewaccesskey = '';
        $DB->update_record('assignsubmission_mahara', $maharasubmission);
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
     * Get view URL
     *
     * @param stdClass $maharasubmission assignsubmission_mahara record
     * @return stdClass $url Moodle URL object
     */
    public function get_view_url(stdClass $maharasubmission) {
        global $DB;
        $remotehost = $DB->get_record('mnet_host', array('id'=>$this->get_config('mnethostid')));
        $url = new moodle_url('/auth/mnet/jump.php', array(
            'hostid' => $remotehost->id,
            'wantsurl' => $maharasubmission->viewurl,
        ));
        return $url;
    }

     /**
      * Display onlinetext word count in the submission status table
      *
      * @global stdClass $DB
      * @global stdClass $OUTPUT
      * @global stdClass $USER
      * @param stdClass $submission
      * @param bool $showviewlink - If the summary has been truncated set this to true
      * @return string
      */
    public function view_summary(stdClass $submission, & $showviewlink) {
        global $OUTPUT, $DB, $USER;

        $maharasubmission = $this->get_mahara_submission($submission->id);
        // Instead of letting Moodle generate the the view link,
        // we will substitute own preview link in the summary output.
        $link = '';
        if ($maharasubmission) {
            if ($submission->userid == $USER->id || !empty($maharasubmission->viewaccesskey)) {
                // Either the page is viewed by the author or access code has been issued
                $icon = $OUTPUT->pix_icon('t/preview', get_string('view' . substr($this->get_subtype(), strlen('assign')), 'mod_assign'));
                $link .= $OUTPUT->action_link($this->get_view_url($maharasubmission), $icon);
                $link .= $OUTPUT->spacer(array('width'=>15));
            } else {
                $showviewlink = true;
            }
            $link .= $maharasubmission->viewtitle;
        }
        return $link;
    }

    /**
     * Display the view of submission.
     *
     * We should not normally hit this, as we override view link in view_summary
     * method. But just in case user shomehow hit viewing from Moodle context,
     * display the link to portfolio page.
     *
     * @global stdClass $DB
     * @global stdClass $USER
     * @param stdClass $submission
     * @return string
     */
    public function view(stdClass $submission) {
        global $DB, $USER;

        $result = '';
        $maharasubmission = $this->get_mahara_submission($submission->id);
        if ($maharasubmission) {
            $lastattempt = $DB->get_field('assign_submission', 'max(attemptnumber)', array('assignment' => $submission->assignment, 'groupid' => $submission->groupid, 'userid'=>$submission->userid));
            if ($submission->attemptnumber < $lastattempt) {
                // TODO: lang string
                $result .= get_string('previousattemptsnotvisible', 'assignsubmission_mahara');
            } else if ($submission->userid == $USER->id || !empty($maharasubmission->viewaccesskey)) {
                // Either the page is viewed by the author or access code has been issued
                $remotehost = $DB->get_record('mnet_host', array('id'=>$this->get_config('mnethostid')));
                $url = $this->get_view_url($maharasubmission);
                $remotehost->jumpurl = $url->out();
                $remotehost->viewtitle = $maharasubmission->viewtitle;
                $result .= get_string('viewsaved', 'assignsubmission_mahara', $remotehost);
            } else if (empty($maharasubmission->viewaccesskey)) {
                $result .= get_string('needstobelocked', 'assignsubmission_mahara');
            }
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
        if ($type == 'mahara' && $version >= 2011070110) {
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
        $this->set_config('mnethostid', $oldassignment->var2);
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

        $maharadata = unserialize($oldsubmission->data2);

        $maharasubmission = new stdClass();
        $maharasubmission->viewid = $maharadata['id'];
        $maharasubmission->viewurl = $maharadata['url'];
        $maharasubmission->viewtitle = $maharadata['title'];

        $url = new moodle_url($maharadata['url']);
        if ($url->get_param('mt')) {
            $maharasubmission->viewaccesskey = $url->get_param('mt');
        }

        $maharasubmission->submission = $submission->id;
        $maharasubmission->assignment = $this->assignment->get_instance()->id;

        if (!$DB->insert_record('assignsubmission_mahara', $maharasubmission) > 0) {
            $log .= get_string('couldnotconvertsubmission', 'mod_assign', $submission->userid);
            return false;
        }
        return true;
    }

    /**
     * Formatting for log info
     *
     * @global stdClass $DB
     * @param stdClass $submission The new submission
     * @return string
     */
    public function format_for_log(stdClass $submission) {
        global $DB;
        $remotehost = $DB->get_record('mnet_host', array('id'=>$this->get_config('mnethostid')));
        if ($maharasubmission = $this->get_mahara_submission($submission->id)) {
            $maharasubmission->remotehostname = $remotehost->name;
            $output = get_string('outputforlog', 'assignsubmission_mahara', $maharasubmission);
        } else {
            $output = get_string('outputforlognew', 'assignsubmission_mahara', $remotehost->name);
        }
        return $output;
    }

    /**
     * The assignment has been deleted - cleanup
     *
     * @global stdClass $DB
     * @return bool
     */
    public function delete_instance() {
        global $DB;
        // First of all release all pages on remote site.
        $records = $DB->get_records('assignsubmission_mahara', array('assignment'=>$this->assignment->get_instance()->id));
        foreach ($records as $record) {
            if ($this->mnet_release_submited_view($record->viewid, array(), $record->iscollection) === false) {
                throw new moodle_exception('errormnetrequest', 'assignsubmission_mahara', '', $this->get_error());
            }
        }
        // Now delete records.
        $DB->delete_records('assignsubmission_mahara', array('assignment'=>$this->assignment->get_instance()->id));

        return true;
    }

    /**
     * Carry out any extra processing required when a student is given a new attempt
     * (i.e. when the submission is "reopened"
     * @param stdClass $oldsubmission The previous attempt
     * @param stdClass $newsubmission The new attempt
     */
    public function add_attempt(stdClass $oldsubmission, stdClass $newsubmission) {
        global $DB;
        // Unlock the previous submission's page if the assignment is reopened. That way
        // the student can make improvements and then resubmit.
        $maharasubmission = $this->get_mahara_submission($oldsubmission->id);
        if ($maharasubmission) {
            if ($this->mnet_release_submited_view($maharasubmission->viewid, array(), $maharasubmission->iscollection) === false) {
                throw new moodle_exception('errormnetrequest', 'assignsubmission_mahara', '', $this->get_error());
            }
            $maharasubmission->viewaccesskey = '';
            $DB->update_record('assignsubmission_mahara', $maharasubmission);
        }
    }
}
