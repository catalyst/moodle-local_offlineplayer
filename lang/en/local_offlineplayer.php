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

//strings for offlineplayer
$string['pluginname'] = 'Offline Player';
$string['remotecourses'] = 'Courses available for download';
$string['downloadcourse'] = 'Download course';
$string['updatecourse'] = 'Update (Delete existing course)';
$string['coursenotavailable'] = 'This course is not available for download';
$string['coursenotvalid'] = 'The downloaded course was not valid';
$string['downloadingcourse'] = 'Downloading course';
$string['pleasewait'] = 'Please wait.';
$string['importingcourse'] = 'Importing course... (this may take some time)';
$string['synccourse_desc'] = 'The sync process will take all tracking data in your offline player and store it on the main {$a} site.';
$string['synccoursenodata'] = 'No data was found in this site that could be synchronised with the main {$a} site.';
$string['datatosync'] = 'The following data will be synchronised with the main {$a} site.';
$string['sync'] = 'Upload tracking data to main {$a} site';
$string['uploadingdata'] = 'Uploading data to main {$a} site.';
$string['generatingupload'] = 'Preparing data for upload to main {$a} site.';
$string['nolocaluser'] = '<h2>Welcome to {$a->mothershipname} Offline!</h2>
<p>To start using this software, you will need to create a local account, synchronized with your {$a->mothershipname} website account (an internet connection is needed).</p>
<p>For more information on how to use the offline player, please read the  <a href="{$a->quickstart}" target="_blank">quick start guide</a></p>
<div id="createaccount"><a href="{$a->createaccount}">Create my account</a></p>';
$string['cannotuseadmin'] = 'You cannot sync the admin or guest user with the offline player - please use a different account.';
$string['chooseuser'] = 'Choose the user account to use with the offline player';
$string['createnewuser'] = 'Create new user';
$string['requiresconnection'] = 'Requires connection with the main {$a} site.';
$string['confirmdelete'] = 'Are you sure you want to delete the course: {$a}';
$string['confirmdeleteuser'] = 'Are you sure you want to delete the user: {$a}';
$string['coursedeleted'] = 'Course deleted';
$string['nointernets'] = 'You don\'t appear to have an internet connection at the moment.';
$string['tryanyway'] = 'Try anyway.';
$string['mustenrolonmothership'] = 'You are not enrolled in any courses on the main {$a} site that can be downloaded and used offline.';
$string['toaddmorecourses'] = 'To make more activities available you must enrol on the main {$a} site.';
$string['changeprofile'] = 'Change profile';
$string['thiswaslastsync'] = 'This account was last synchronised on {$a}';
$string['neversync'] = 'This account has never been synchronised with the main {$a} site';
$string['deleteaccount'] = 'Delete account';
$string['lastlogin'] = 'Logged in last: {$a}';
$string['invalidaccount'] = 'Invalid account';
$string['thefollowingcourseswillberemoved'] = 'The following activities will be removed with this account';
$string['userdeleted'] = 'User deleted';
$string['downloadmoreactivities'] = 'Download more activities';
$string['synccoursedata'] = 'Sync course data';
$string['wrongmothershipuser'] = 'You are logged into the main {$a} site as a different user than your offline player, please logout of {$a} and try again.';
$string['synccompleted'] = 'The data was synchronised';
$string['forummissingmessage'] = '<div class="availabilityinfo">The main {$a} site includes a discussion which is unavailable in this offline version.</div>';
$string['downloadfailed'] = 'The download failed to complete, please try again.';
$string['downloadfailedresume'] = 'Unfortunately the download has been interrupted and {$a} has been downloaded so far, please try again to resume downloading this course.';
$string['offlineplayernotlinked'] = 'The Offline Player must be linked to the mothership site.';
$string['offlineplayernotmanaginglogin'] = 'The Offline Player has not been configured to allow user-managment. Enabling this will allow course/users in this site to be deleted anonymously and should not be done on a normal site.';
$string['mothershipname'] = 'Mothership name';
$string['mothershipname_desc'] = 'The name of your main site for example "Learn at Catalyst" - used in text such as "Upload my activity to main Learn at Catalyst site", "You are not enrolled in any courses on the main Learn at Catalyst site"';
$string['mothershipurl'] = 'Mothership url';
$string['mothershipurl_desc'] = 'This is the url to your main site - it should match the $CFG->wwwroot setting from your main site.';
$string['usersalt'] = 'Offline player salt';
$string['usersalt_desc'] = 'This is used by the offline player to verify communication - this should be copied from the main site setting.';


// Custom Kaya strings that override normal ones;
$string['downloadfailedresume'] = 'Unfortunately, the download was interrupted. Please check your connection. You can click the link below to resume downloading this course, or to download a different course.';
$string['sync'] = 'Upload my activity to main {$a} site';
$string['synccourse_desc'] = 'The sync process will take all activity data in your offline player and store it on the main {$a} site.';
