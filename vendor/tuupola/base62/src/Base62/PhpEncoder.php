<?php

/*
 * This file is part of the Base62 package
 *
 * Copyright (c) 2011-2016 Anthony Ferrara, Mika Tuupola
 *
 * Based on BaseConverter by Anthony Ferrara
 *   https://github.com/ircmaxell/SecurityLib/tree/master/lib/SecurityLib
 *
 * Licensed under the MIT license:
 *   http://www.opensource.org/licenses/mit-license.php
 *
 * Project home:
 *   https://github.com/tuupola/base62
 *
 */

namespace Tuupola\Base62;

class PhpEncoder
{
    public static $characters = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";

    public static function encode($data)
    {
        $data = str_split($data);
        $data = array_map(function ($character) {
            return ord($character);
        }, $data);

        $converted = self::baseConvert($data, 256, 62);

        return implode("", array_map(function ($index) {
            return self::$characters[$index];
        }, $converted));
    }

    public static function decode($data)
    {
        $data = str_split($data);
        $data = array_map(function ($character) {
            return strpos(self::$characters, $character);
        }, $data);

        $converted = self::baseConvert($data, 62, 256);

        return implode("", array_map(function ($ascii) {
            return chr($ascii);
        }, $converted));
    }

    /* http://codegolf.stackexchange.com/questions/1620/arbitrary-base-conversion/1626#1626 */

    public static function baseConvert(array $source, $source_base, $target_base)
    {
        $result = [];
        while ($count = count($source)) {
            $quotient = [];
            $remainder = 0;
            for ($i = 0; $i !== $count; $i++) {
                $accumulator = $source[$i] + $remainder * $source_base;
                $digit = floor($accumulator / $target_base);
                $remainder = $accumulator % $target_base;
                if (count($quotient) || $digit) {
                    array_push($quotient, $digit);
                };
            }
            array_unshift($result, $remainder);
            $source = $quotient;
        }

        return $result;
    }
}
