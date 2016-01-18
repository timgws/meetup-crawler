<?php
namespace MeetupCrawler;


class MemberList {
    private static $list = [];
    static function add($name) {
        self::$list[] = $name;
    }

    static function get() {
        return self::$list;
    }
}