<?php

namespace App;

class Config
{
    public static function isTest() {
        return true;
    }

	public static function host() {
		if (self::isTest()) {
			return [
				'host' => 'http://localhost/mingle/index.php',
				'resource' => 'http://localhost/mingle/resources/'
			];
		} else {
			return [
				'host' => 'http://50.18.197.132/index.php',
				'resource' => 'http://50.18.197.132/resources/'
			];
		}
    }

    public static function database() {
		if (self::isTest()) {
			return  [
				'host' => 'localhost',
				'database' => 'mingle_db',
				'username' => 'root',
				'password' => '',
			];
		} else {
			return  [
				'host' => 'localhost',
				'database' => 'vyve_db',
				'username' => 'vyve_user',
				'password' => 'vyveuser1234!@#$',
			];
		}
    }
}
