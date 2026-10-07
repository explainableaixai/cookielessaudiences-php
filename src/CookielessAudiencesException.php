<?php

namespace CookielessAudiences;

/** Thrown when the JSON body carries a status other than 200. */
class CookielessAudiencesException extends \RuntimeException
{
    /** @var array */
    public $body;

    public function __construct(int $status, string $message, array $body = [])
    {
        parent::__construct("[$status] $message", $status);
        $this->body = $body;
    }
}
