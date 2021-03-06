<?php
namespace Espro\SoapHandler;

use Monolog\Logger;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator;

class LogConfiguration
{
    /**
     * For further documention reference to Logger erros levels
     * @see Logger
     */
    const DEBUG = 100;
    /**
     * For further documention reference to Logger erros levels
     * @see Logger
     */
    const INFO = 200;
    /**
     * For further documention reference to Logger erros levels
     * @see Logger
     */
    const NOTICE = 250;
    /**
     * For further documention reference to Logger erros levels
     * @see Logger
     */
    const WARNING = 300;
    /**
     * For further documention reference to Logger erros levels
     * @see Logger
     */
    const ERROR = 400;
    /**
     * For further documention reference to Logger erros levels
     * @see Logger
     */
    const CRITICAL = 500;
    /**
     * For further documention reference to Logger erros levels
     * @see Logger
     */
    const ALERT = 550;
    /**
     * For further documention reference to Logger erros levels
     * @see Logger
     */
    const EMERGENCY = 600;

    /**
     * @var string
     */
    protected $channel = 'SoapHandler';
    /**
     * @var int
     */
    protected $level;
    /**
     * @var string
     */
    protected $path;
    /**
     * @var int
     */
    protected $maxFiles = 0;

    /**
     * LogConfiguration constructor.
     * @param int $_logLevel
     * @param string $_logPath
     * @param string $_logChannel
     * @param int $_maxFiles
     * @throws ValidationException
     */
    public function __construct($_logLevel, $_logPath, $_logChannel = null, $_maxFiles = 0)
    {
        Validator::between(self::DEBUG, self::EMERGENCY)->setName('LogLevel')->assert($_logLevel);
        Validator::file()->setName('LogPath')->assert($_logPath);
        Validator::intVal()->min(0)->assert($_maxFiles);

        $this->level = $_logLevel;
        $this->path = $_logPath;
        $this->maxFiles = $_maxFiles;

        if( !is_null($_logChannel) && trim($_logChannel) != '' ) {
            $this->channel = $_logChannel;
        }
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getLevel()
    {
        return $this->level;
    }

    public function isLevel($_level)
    {
        return $_level == $this->level;
    }

    public function getChannel()
    {
        return $this->channel;
    }

    public function getMaxFiles()
    {
        return $this->maxFiles;
    }
}