assignsubmission-mahara
============================

Mahara assignment submission plugin for Moodle 2.3+

This plugin adds Mahara pages submission functionality to assignments in Moodle.
The plugin works with the new "mod/assign" type introduced in 2.3. It requires 
at least one Mahara site linked to Moodle via MNet.

The plugin has the same funtionality as the older "mod/assignment" Mahara 
assignment plugin (https://gitorious.org/mahara-contrib/mod-assignment-type-mahara) 
with some improvements. In particular:

* A graded Mahara page will never become editable again.
* The same page cannot be submitted more than once.
* If the assignment allows drafts, the Mahara page is not locked from editing until the final submission.
* Collections can be submitted instead of only individual pages.

The plugin also allows migrating old Mahara "mod/assignment" assignments to the new
type. The plugin does not include featues to communicate with the outcomes artefact
plugin.

Logic
-----

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

Currently, pages and collections are designed to be permanently locked in Mahara
once they are submitted to Moodle. Future development plans may allow for pages and
collections to be unlocked. For now, the primary workaround is for students to make
a copy of the locked page or collection in Mahara and use that for future edits, 
which leaves an audit trail of past submissions.

(There is an unwieldy workaround that allows a teacher to unlock a submitted page or
collection. The teacher can grant the student another attempt, via the gradebook. If
the assignment allows drafts, this will put the student's submission back into "draft"
status, unlocking the page or collection. If the assignment does not allow drafts,
the student will need to edit their submission and choose a different page or collection.)

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
