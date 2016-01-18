<?php

/**
  * Meetup.com crawler
  *
  * Get a list of Twitter users from a Meetup group...
  *
  * @note I have used a custom version of Goutte that enables caching of downloaded files!
  */

define('MEETUP_MEMBERS_URL', 'http://www.meetup.com/PerthPHP/members/');

require __DIR__ . '/vendor/autoload.php';

use MeetupCrawler\MemberList;
use MeetupCrawler\Tasks;

$meetupTasks = new Tasks();
$meetupTasks->setMeetupURL(MEETUP_MEMBERS_URL);
$number_members = $meetupTasks->getNumberOfMembers();

// Loop through all the pages
for ($offset = 0; $offset < $number_members; $offset=$offset+20) {
    $user_ids = $meetupTasks->getMemberIDs($offset);

    foreach($user_ids as $user_id) {
        try {
            $twitter = $meetupTasks->getMemberTwitterAccount($user_id);

            MemberList::add($twitter);
        } catch (\MeetupCrawler\SocialAccountNotFound $e) {

        }
    }
}

print_r(MemberList::get());

