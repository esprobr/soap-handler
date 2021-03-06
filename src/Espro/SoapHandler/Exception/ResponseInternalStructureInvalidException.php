<?php
namespace Espro\SoapHandler\Exception;

class ResponseInternalStructureInvalidException extends SoapHandlerException
{
    public function __construct( $_file, $_line )
    {
        parent::__construct(
            ExceptionLevel::getMessage( ExceptionLevel::ERRLVL_INTERNALSTRUCT ),
            E_ERROR,
            $_file,
            $_line
        );
    }
}