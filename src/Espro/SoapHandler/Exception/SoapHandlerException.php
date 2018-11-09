<?php
namespace Espro\SoapHandler\Exception;

class SoapHandlerException extends \ErrorException
{
    public function __construct($_message, $_severity, $_file, $_line)
    {
        parent::__construct($_message, 0, $_severity, $_file, $_line);
    }
}