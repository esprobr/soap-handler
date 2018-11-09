<?php
namespace Espro\SoapHandler\Exception;

class BaseUrlNotRespondingException extends SoapHandlerException
{
    public function __construct( $_baseUrl, $_message, $_file, $_line )
    {
        parent::__construct(
           sprintf( ExceptionLevel::getMessage( ExceptionLevel::ERRLVL_URL_NOT_RESPONDING ), $_baseUrl, $_message ),
            E_ERROR,
            $_file,
            $_line
        );
    }
}