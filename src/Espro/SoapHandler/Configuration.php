<?php
namespace Espro\SoapHandler;

class Configuration
{
    const INFO_DEBUG = 1;
    const INFO_SILENT = 2;

    /**
     * @var string
     */
    protected $baseUrl;
    /**
     * @var string
     */
    protected $endpoint;
    /**
     * @var int
     */
    protected $mode;
    /**
     * @var bool
     */
    protected $throwExceptions;
    /**
     * @var int
     */
    protected $timeoutInSeconds;
    /**
     * Soap extension options
     * @var array
     */
    protected $options = [];

    public function __construct()
    {
        self::mode( self::INFO_SILENT );
        self::throwExceptions( false );
        self::timeout( 5 );
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * @param string $_baseUrl
     * @return $this
     */
    public function setBaseUrl( $_baseUrl )
    {
        $this->baseUrl = $_baseUrl;
        return $this;
    }

    /**
     * @return string
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * @param string $_endpoint
     * @return $this
     */
    public function setEndpoint( $_endpoint )
    {
        $this->endpoint = $_endpoint;
        return $this;
    }

    /**
     * @return int
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @param int $_mode
     * @return $this
     */
    public function setMode( $_mode )
    {
        $this->mode = $_mode;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getThrowExceptions()
    {
        return $this->throwExceptions;
    }

    /**
     * @param boolean $_throwExceptions
     * @return $this
     */
    public function setThrowExceptions( $_throwExceptions )
    {
        $this->throwExceptions = $_throwExceptions;
        return $this;
    }

    public function getTimeout()
    {
        return $this->timeoutInSeconds;
    }

    public function setTimeout( $_timeoutInSeconds )
    {
        $this->timeoutInSeconds = $_timeoutInSeconds;
        return $this;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function setOptions( $_options )
    {
        $this->options = $_options;
    }

    public function setOption( $_key, $_value )
    {
        $this->options[ $_key ] = $_value;
    }


    /* Shorthand methods */
    /**
     * Shorthand to get/set throwExceptions
     * @return bool|Configuration
     */
    public function throwExceptions()
    {
        if( func_num_args() == 0 ) {
            return self::getThrowExceptions();
        } else {
            return self::setThrowExceptions( func_get_arg( 0 ) );
        }
    }

    public function mode()
    {
        if( func_num_args() == 0 ) {
            return self::getMode();
        } else {
            return self::setMode( func_get_arg( 0 ) );
        }
    }

    public function timeout()
    {
        if( func_num_args() == 0 ) {
            return self::getTimeout();
        } else {
            return self::setTimeout( func_get_arg( 0 ) );
        }
    }

    public function isModeDebug()
    {
        return $this->mode === self::INFO_DEBUG;
    }
}