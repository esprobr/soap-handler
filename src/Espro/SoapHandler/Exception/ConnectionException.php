<?php
namespace Espro\SoapHandler\Exception;

class ConnectionException extends SoapHandlerException
{
    public function __construct($_message, $_file, $_line)
    {
        parent::__construct($_message, E_ERROR, $_file, $_line);
    }
}