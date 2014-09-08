moodle-assignsubmission-mahara
============================

Mahara assignment submission plugin for Moodle 2.6

This plugin adds Mahara pages submission functionality to assignments in Moodle.
The plugin works with the new "mod/assign" type introduced in 2.3. It requires 
at least one Mahara site linked to Moodle via MNet.

This plugin allows a teacher to add a "Mahara" item to the submission options for 
a Moodle assignment. Students can then select one of the pages or collections from
their Mahara portfolio as part of their assignment submission.

The submitted Mahara page or collection will be locked from editing in Mahara, the
same as if it had been submitted to a Mahara group. However, unlike group submissions,
pages and collections submitted to Moodle remain permanently locked even after grading.
If you'd like the submitted pages and collections to be unlocked after grading, install 
the Mahara assignment feedback plugin for Moodle:
https://github.com/catalyst/moodle-assignfeedback_mahara/tree/moodle26-merged

The plugin also allows migrating old Mahara "mod/assignment" assignments to the new
type. The plugin does not include featues to communicate with the outcomes artefact
plugin.

This particular git branch (moodle26-merged) is meant to merge the two forks of the
Mahara assignment submission plugin for Moodle 2.3+:
 - The version developed by the University of Portland: https://github.com/fellowapeman/moodle-assign_mahara
 - The version developed by Lancaster University: https://github.com/catalyst/assignsubmission_mahara

Installation
------------
1. Make sure that your Moodle and Mahara versions are up to date.
2. Apply the patch "mahara-patch.txt" to your Mahara site.
3. If you are using Moodle 2.6 or earlier, apply the patch "moodle-patch.txt" to your 
      Moodle site.
4. Copy the contents of this project to mod/assign/submission/mahara in your Moodle site.
5. Proceed with plugin installation in Moodle.
6. On the Moodle page "Site Admin" -> "Networking" -> "Peers", choose the Mahara site.
      Open the "Services" tab and enable "Assign Submission Mahara" services.
7. Now you may create your first Mahara assignment.


Upgrading
---------

This plugin is designed to allow you to upgrade from either the University of Portland
version, or the Lancaster University version. It will automatically detect which version
of the plugin you have installed, and migrate it accordingly. So all you need to do is:

1. Remove the current contents of your mod/assign/submission/mahara directory
2. Follow the steps under "Installation" above. (This will trigger the database upgrade script.)
3. If you have also installed the Mahara assignment feedback plugin (mod/assign/feedback/mahara), you should now upgrade it to the version at https://github.com/catalyst/moodle-assignfeedback_mahara/tree/moodle26-merged
4. If you have also installed the Mahara local plugin (local/mahara), you should now uninstall it.

About those patches
-------------------

As you may have noticed in the installation instructions, this plugin requires you to apply a patch to your Mahara site and possibly another patch to your Moodle site. The Moodle patch provides an additional hook for the assignment submission plugin to respond when an assignment is reopened. The Mahara patch provides support for the Mahara web services to handle collections.

The Moodle patch has been upstreamed into Moodle 2.7, so if you are using that version or later, you do not need to manually apply the patch file. The Mahara patch is still in the process of code review as of May 2014 (see https://reviews.mahara.org/#/c/3239/ ), targetted for Mahara 1.10.

For information about how to apply a patch file, try Google. If you are using Linux, the process will look something like this:

```Shell
cd /var/www/path/to/mahara
patch -p0 < /path/to/mahara-patch.txt
cd /var/www/path/to/moodle
patch -p0 < /path to/moodle-patch.txt
```

Implementation logic:
---------------------

A Moodle assignment with a "Mahara" submission component, allows the student to pick
one of their pages or collections from Mahara, as part of their assignment submission.

* Individual pages that are part of collections cannot be picked on their own (the entire collection must be picked instead)
* Pages or collections that are already locked due to being submitted to a Mahara group or another Moodle assignment, are also not available

The page or collection will be locked from editing in Mahara if the assignment is
submitted and/or locked in the Moodle gradebook.

This means that if the Moodle assignment requires students to click the submit
button to declare the submission final (an option in the assignment settings), the
page or collection will not be locked in Mahara until the submit button is clicked.
If the assignment does not require students to click the submit button, the page or
collection will be locked in Mahara as soon as it is selected. If the student changes
their selected page (e.g. before the assignment deadline), the originally selected
page will be unlocked and the newly selected one locked instead.

By itself, this plugin will permanently locked pages and collections in Mahara once
they are submitted to Mahara. As mentioned earlier, you can use the related assignment
feedback plugin to make pages and collections unlock after grading.
