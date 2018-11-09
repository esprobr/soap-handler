<?php
/**
 * Created by PhpStorm.
 * User: wesley.sousa
 * Date: 09/11/2018
 * Time: 13:58
 */

namespace Espro\SoapHandler\Exception;


class InvalidShorthandArgumentsException extends SoapHandlerException
{
    public function __construct($_method, $_file, $_line)
    {
        parent::__construct(
            sprintf( ExceptionLevel::getMessage( ExceptionLevel::ERRLVL_SHORTHAND ), $_method ),
            E_ERROR,
            $_file,
            $_line
        );
    }
}