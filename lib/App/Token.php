<?php

namespace App;

class Token
{
    public $decoded;

    public function hydrate($decoded)
    {
        $this->decoded = $decoded;
    }

    public function getUserId()
    {
        return $this->decoded->user_id;
    }
}
