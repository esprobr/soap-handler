<?php
namespace Espro\SoapHandler\Exception;

class CallingAMethodWhenDisconnectedException extends SoapHandlerException
{
    public function __construct( $_file, $_line )
    {
        parent::__construct(
            ExceptionLevel::getMessage( ExceptionLevel::ERRLVL_CON ),
            E_ERROR,
            $_file,
            $_line
        );
    }
}