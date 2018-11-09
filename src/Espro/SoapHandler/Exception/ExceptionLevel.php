<?php
namespace Espro\SoapHandler\Exception;

class ExceptionLevel
{
    const ERRLVL_NONE = -1;
    const ERRLVL_CON = 0;
    const ERRLVL_STRUCT = 1;
    const ERRLVL_INTERNALSTRUCT = 2;
    const ERRLVL_VALIDATION = 3;
    const ERRLVL_CALL = 4;
    const ERRLVL_URL_NOT_RESPONDING = 5;
    const ERRLVL_SHORTHAND = 6;

    protected static $messages = [
        self::ERRLVL_URL_NOT_RESPONDING => "The base url %s insn't responding - HTTP Status Code: %s",
        self::ERRLVL_NONE => 'This message is probably in the wrong place',
        self::ERRLVL_CON => 'Connection error: calling a method when disconnected',
        self::ERRLVL_STRUCT => 'Response error: the expected response structure cannot be found',
        self::ERRLVL_INTERNALSTRUCT => 'Response error: the expected response structure cannot be ',
        self::ERRLVL_VALIDATION => 'The expected response value is invalid',
        self::ERRLVL_CALL => 'Calling error: soap returned an error',
        self::ERRLVL_SHORTHAND => "Arguments for shorthand method %s are invalid"
    ];

    public static function getMessage($_errorLevel)
    {
        return self::$messages[$_errorLevel];
    }

    public static function replaceMessage($_errorLevel, $_errorMessage)
    {
        self::$messages[$_errorLevel] = $_errorMessage;
    }

    public static function getExceptionByLevel($_errorLevel, $_file, $_line)
    {
        switch($_errorLevel) {
            case self::ERRLVL_CON:
                $ret = new CallingAMethodWhenDisconnectedException( $_file, $_line );
                break;
            case self::ERRLVL_STRUCT:
                $ret = new ResponseStrutcureInvalidException( $_file, $_line );
                break;
            case self::ERRLVL_INTERNALSTRUCT:
                $ret = new ResponseInternalStructureInvalidException( $_file, $_line );
                break;
            default:
                $ret = new SoapHandlerException(self::$messages[self::ERRLVL_NONE], E_ERROR, $_file, $_line);
                break;
        }
        return $ret;
    }
}