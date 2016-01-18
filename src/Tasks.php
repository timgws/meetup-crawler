<?php
namespace MeetupCrawler;

use Concat\Http\Handler\CacheHandler;
use Doctrine\Common\Cache\FilesystemCache;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;


class Tasks
{
    /** @var Goutte\Client */
    private $client;

    private $url;

    public function __construct()
    {
        $this->setClient($this->newClient());
    }

    public function newClient()
    {
        // Basic directory cache example
        // (from https://github.com/rtheunissen/guzzle-cache-handler/blob/master/README.md)
        $cacheProvider = new FilesystemCache(__DIR__ . '/../cache');
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

    /**
     * @param string $url
     */
    public function setMeetupURL($url)
    {
        $this->url = $url;
    }

    public function setClient(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Find the number of members in a Meetup group
     * @return integer
     * @throws \Exception
     */
    public function getNumberOfMembers()
    {
        $content = $this->client->request('GET', $this->url);

        // Get the number of members
        $members = $content->filter('span.D_count')->first()->text();
        if (!preg_match('|\(([0-9]*)\)|', $members, $t)) {
            throw new \Exception("Could not find number of members");
        }

        return $t[1];
    }

    public function getMemberURLs($offset)
    {
        $url = $this->url . "?offset=$offset&sort=last_visited&desc=1";

        $content = $this->client->request('GET', $url);

        $list = array();

        $content->filter('h4 > a.memName')->each(function (Crawler $item) use (&$list) {
            $url = $item->attr('href');

            $list[] = $url;
        });

        return $list;
    }

    public function getMemberIDs($offset)
    {
        $list = $this->getMemberURLs($offset);
        $ids = [];

        foreach($list as $member_url) {
            $ids[] = $this->extractMemberID($member_url);
        }

        return $ids;
    }

    private function extractMemberID($url)
    {
        // eg http://www.meetup.com/PerthPHP/members/11195454/
        if (!preg_match('|/members/([0-9]*)/?|', $url, $t)) {
            throw new \Exception("member url does not look like it's correct");
        }

        return $t[1];
    }

    public function getMemberTwitterAccount($member)
    {
        // eg http://www.meetup.com/PerthPHP/members/11195454/
        $content = $this->client->request('GET', $this->url . $member . '/');

        $account = null;
        $content->filter('div.D_memberProfileSocial a')->each(function (Crawler $item) use (&$account) {
            $title = $item->attr('title');

            if (preg_match('|Twitter:\s@(.*)|', trim($title), $found)) {
                if (!empty($found[1])) {
                    $account = $found[1];
                }
            }
        });

        if (empty($account)) {
            throw new SocialAccountNotFound('Twitter account not found!');
        }

        return $account;
    }
}