assignsubmission-mahara
============================

Mahara assignment submission plugin for Moodle 2.3+

This plugin adds Mahara pages submission functionality to assignments in
Moodle. Plugin works with the new assignment type introduced in 2.3. It
requres to have at least one Mahara site linked to Moodle.

The plugin has funtionality of old Mahara submission type plugin
(https://gitorious.org/mahara-contrib/mod-assignment-type-mahara). It also
allows exporting old Mahara submission assignments to the assignments of new
type. The plugin does not include featues to communicate with outcomes
artefact plugin.

Installation:

1. Make sure that your Moodle version is up-to-date (recent update included
   assign mod chnages required for this plugin).
2. Copy the content to mod/assign/submission/mahara
3. Proceed with installation in Moodle.
4. In Site Admin > Networking > Peers choose the Mahara one, open Services
      tab and enable Assign Submission Mahara services.
5. Now you may create your first Mahara assignment.
