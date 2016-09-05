<?php

/*
 * This file is part of the Slim API skeleton package
 *
 * Copyright (c) 2016 Mika Tuupola
 *
 * Licensed under the MIT license:
 *   http://www.opensource.org/licenses/mit-license.php
 *
 * Project home:
 *   https://github.com/tuupola/slim-api-skeleton
 *
 */

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

use App\Controller;

$app->group('/api', function () use ($app) {
	$app->group('/user', function () use ($app) {
        $app->post('/signin', function (Request $request, Response $response) {
			(new Controller\UserController($request, $response))->signin();
		});

        $app->post('/changesns', function (Request $request, Response $response) {
            (new Controller\UserController($request, $response))->changeSNS();
        });

        $app->post('/savesnslist', function (Request $request, Response $response) {
            (new Controller\UserController($request, $response))->saveSNSList();
        });

		$app->post('/addpicture', function (Request $request, Response $response) {
			(new Controller\UserController($request, $response))->addPicture();
		});

		$app->post('/removepicture', function (Request $request, Response $response) {
			(new Controller\UserController($request, $response))->removePicture();
		});

		$app->post('/changesetting', function (Request $request, Response $response) {
			(new Controller\UserController($request, $response))->changeSetting();
		});

		$app->post('/logout', function (Request $request, Response $response) {
			(new Controller\UserController($request, $response))->logout();
		});

		$app->post('/deleteaccount', function (Request $request, Response $response) {
			(new Controller\UserController($request, $response))->deleteAccount();
		});

        $app->post('/unlimitblind', function (Request $request, Response $response) {
            (new Controller\UserController($request, $response))->unlimitBlind();
        });
	});
});