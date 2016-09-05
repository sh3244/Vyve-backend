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

require __DIR__ . "/vendor/autoload.php";

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$app = new \Slim\App([
    "settings" => [
        "displayErrorDetails" => true
    ]
]);

require __DIR__ . "/config/logger.php";
require __DIR__ . "/config/handlers.php";
require __DIR__ . "/config/middleware.php";
require __DIR__ . "/config/database.php";

require __DIR__ . "/routes/chat.php";
require __DIR__ . "/routes/user.php";

$app->run();
