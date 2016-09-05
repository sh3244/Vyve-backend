<?php

/**
 * User: Sasaki Kenski
 * Date: 2016-03-03
 */

namespace App\Controller;

use \App\Config;

abstract class BaseController
{
    protected $request = null;
    protected $response = null;
    protected static $db = null;

    function __construct($request, $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    function __destruct()
    {
        $this->request = null;
        $this->response = null;
        $this->db = null;
    }

    public static function database()
    {
        if (self::$db == null) {
            $dbhost = Config::database()['host'];
            $dbuser = Config::database()['username'];
            $dbpass = Config::database()['password'];
            $dbname = Config::database()['database'];
            self::$db = new \PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
            self::$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }

        return self::$db;
    }

    public function abort($statuscode, $result)
    {
        $this->response->withHeader('Content-Type','application/json');
        $this->response->withStatus(200);
        $this->response->getBody()->write(json_encode(['resultcode' => $statuscode, 'resultmsg' => $result]));
    }

    public function response($result)
    {
        $this->response->withHeader('Content-Type','application/json');
        $this->response->withStatus(200);
        $this->response->getBody()->write(json_encode(['resultcode' => 200, 'resultmsg' => $result]));
    }

    public function responseNoBody()
    {
        $this->response->withHeader('Content-Type', 'application/json');
        $this->response->withStatus(204);
    }

    public function view()
    {
        $loader = new \Twig_Loader_Filesystem('./views');
        $twig = new \Twig_Environment($loader);
        return $twig;
    }

    protected function getApp() {
        return $this->request->app;
    }

    protected function param($key)
    {
        return $this->request->getParsedBody()[$key];
    }
    /*
      protected function putParam($key) {
        return $this->app->request->put($key);
      }

      protected function postParam($key) {
        return $this->app->request->post($key);
      }

      protected function getParam($key) {
        return $this->app->request->get($key);
      }*/
}
