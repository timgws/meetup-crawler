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

use Goutte\Client;
use Concat\Http\Handler\CacheHandler;
use Doctrine\Common\Cache\FilesystemCache;
use Symfony\Component\DomCrawler\Crawler;

$client = newClient();

$content = $client->request('GET', MEETUP_MEMBERS_URL);

// Hackity-hack, have a global list of members...
class MemberList {
    private static $list = [];
    static function add($name) {
        self::$list[] = $name;
    }

    static function get() {
        return self::$list;
    }
}


// Get the number of members
$members = $content->filter('span.D_count')->first()->text();
preg_match('|\(([0-9]*)\)|', $members, $t);
$number_members = $t[1];

// Loop through all the pages
for ($i = 0; $i < $number_members; $i=$i+20) {
    $url = MEETUP_MEMBERS_URL . "?offset=$i&sort=last_visited&desc=1";
    echo "[STAT] FOUND " . count(MemberList::get()) . " members with twitter accounts\n";
    echo "[LIST] Downloading ... $url...\n";
    $content = $client->request('GET', $url);

    $content->filter('h4 > a.memName')->each(function (Crawler $item) {
        $client = newClient();
        $url = $item->attr('href');

        echo "[USER] Downloading ... $url...\n";
        $content = $client->request('GET', $url);

        $content->filter('div.D_memberProfileSocial a')->each(function (Crawler $item) {
            $title = $item->attr('title');

            if (preg_match('|Twitter:\s@(.*)|', trim($title), $found)) {
                if (!empty($found[1])) {
                    MemberList::add($found[1]);
                }
            }
        });
    });
}

print_r(MemberList::get());

function newClient() {
    // Basic directory cache example 
    // (from https://github.com/rtheunissen/guzzle-cache-handler/blob/master/README.md)
    $cacheProvider = new FilesystemCache(__DIR__ . '/cache');
    $handler = new CacheHandler($cacheProvider, null, [
        /**
         * @var array HTTP methods that should be cached.
         */
        'methods' => ['GET'],

        /**
         * @var integer Time in seconds to cache a response for.
         */
        'expire' => 3600 /* one hour */,

        /**
         * @var callable Accepts a request and returns true if it should be cached.
         */
        'filter' => null,
    ]);

    $client = new Client();

    $client->setOptions(
        array('handler' => $handler)
    );

    return $client;
}
