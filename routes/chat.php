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

use \App\Controller;

$app->group('/api', function () use ($app) {
	$app->group('/chat', function () use ($app) {
        $app->post('/showquestionlog', function (Request $request, Response $response) {
            (new Controller\ChatController($request, $response))->showQuestionLog();
        });

        $app->post('/startblinddate', function (Request $request, Response $response) {
			(new Controller\ChatController($request, $response))->startBlindDate($this->token);
		});

		$app->post('/answerblinddate', function (Request $request, Response $response) {
			(new Controller\ChatController($request, $response))->answerBlindDate();
		});

        $app->post('/startblindchat', function (Request $request, Response $response) {
            (new Controller\ChatController($request, $response))->startBlindChat();
        });

        $app->post('/sendmessageinchat', function (Request $request, Response $response) {
            (new Controller\ChatController($request, $response))->sendMessageInChat();
        });

        $app->post('/sendmessagereceived', function (Request $request, Response $response) {
            (new Controller\ChatController($request, $response))->sendMessageReceived();
        });

		$app->post('/getblindlist', function (Request $request, Response $response) {
			(new Controller\ChatController($request, $response))->getBlindList();
		});

		$app->post('/getmatchlist', function (Request $request, Response $response) {
			(new Controller\ChatController($request, $response))->getMatchList();
		});

		$app->post('/getchatcontent', function (Request $request, Response $response) {
			(new Controller\ChatController($request, $response))->getChatContent();
		});

		$app->post('/getchatinform', function (Request $request, Response $response) {
			(new Controller\ChatController($request, $response))->getChatInform();
		});

		$app->post('/getprofile', function (Request $request, Response $response) {
			(new Controller\ChatController($request, $response))->getProfile();
		});

		$app->post('/reportuser', function (Request $request, Response $response) {
			(new Controller\ChatController($request, $response))->reportUser();
		});

        $app->post('/connectuser', function (Request $request, Response $response) {
            (new Controller\ChatController($request, $response))->connectUser();
        });

		$app->post('/requestdisconnect', function (Request $request, Response $response) {
			(new Controller\ChatController($request, $response))->requestDisconnect();
		});

        $app->post('/sendlastchance', function (Request $request, Response $response) {
            (new Controller\ChatController($request, $response))->sendLastChance();
        });

		$app->post('/confirmdisconnect', function (Request $request, Response $response) {
			(new Controller\ChatController($request, $response))->terminate();
		});

        $app->post('/reconnect', function (Request $request, Response $response) {
            (new Controller\ChatController($request, $response))->reconnect();
        });

        $app->post('/byeforever', function (Request $request, Response $response) {
            (new Controller\ChatController($request, $response))->terminate();
        });

        $app->post('/terminate', function (Request $request, Response $response) {
            (new Controller\ChatController($request, $response))->terminate();
        });

        $app->post('/checkterminate', function (Request $request, Response $response) {
            (new Controller\ChatController($request, $response))->checkTerminate();
        });

        $app->post('/unmatchuser', function (Request $request, Response $response) {
            (new Controller\ChatController($request, $response))->unmatchUser();
        });

        $app->post('/sendonlinestate', function (Request $request, Response $response) {
            (new Controller\ChatController($request, $response))->sendOnlineState();
        });
	});
});