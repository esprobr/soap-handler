<?php
/**
 * Created by PhpStorm.
 * User: wesley.sousa
 * Date: 13/11/2018
 * Time: 08:19
 */

namespace Espro\SoapHandler\Exception;


class CallException extends SoapHandlerException
{
    public function __construct($_message, $_file, $_line)
    {
        parent::__construct($_message, E_ERROR, $_file, $_line);
    }
}