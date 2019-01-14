<?php
namespace Espro\SoapHandler;

use Espro\SoapHandler\Exception\BaseUrlNotRespondingException;
use Espro\SoapHandler\Exception\CallException;
use Espro\SoapHandler\Exception\ConnectionException;
use Espro\SoapHandler\Exception\ExceptionLevel;
use Espro\Utils\ModelResult;
use Espro\Utils\Url;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

class SoapHandler
{
    /**
     * SOAP Instance
     * @var \SoapClient
     */
    protected $soap = null;
    /**
     * SOAP is connected?
     * @var boolean
     */
    protected $connected = false;
    /**
     * Last error
     * @var string
     */
    protected $connectionErrorString = '';
    /**
     * @var Configuration
     */
    protected $config;
    /**
     * @var Logger
     */
    protected $logger = null;
    /**
     * @var string
     */
    protected $instanceId;

    /**
     * SoapHandler constructor.
     * @param Configuration $_config
     * @throws \Exception
     */
    public function __construct( Configuration $_config )
    {
        $this->config = $_config;

        $this->instanceId = md5( uniqid() );

        if( !is_null( $this->config->getLogConfiguration() ) ) {
            $this->logger = new Logger( $this->config->getLogConfiguration()->getChannel() );
            $this->logger->pushHandler(
                new RotatingFileHandler(
                    $this->config->getLogConfiguration()->getPath(),
                    0,
                    $this->config->getLogConfiguration()->getLevel()
                )
            );
        }

        if( !is_null( $this->logger ) ) {
            $info = [
                'instance' => $this->instanceId
            ];
            if( $this->config->getLogConfiguration()->isLevel( LogConfiguration::DEBUG ) ) {
                $info['url'] = $this->config->getBaseUrl() . '/' . $this->config->getEndpoint();
                $info['timeout'] = $this->config->getTimeout();
                $info['options'] = $this->config->getOptions();
            }

            $this->logger->info(
                "Checking if base url \"{$this->config->getBaseUrl()}\" exists",
                $info
            );
        }
        $exists = Url::exists( $this->config->getBaseUrl(),  $this->config->getTimeout() );

        if ( $exists->getStatus() ) {
            if( !is_null($this->logger) ) {
                $this->logger->info("Base url \"{$this->config->getBaseUrl()}\" is active", [
                    'instance' => $this->instanceId
                ]);
            }

            try {
                if( !is_null($this->logger) ) {
                    $this->logger->info("Trying to connect to endpoint \"{$this->config->getBaseUrl()}/{$this->config->getEndpoint()}\"", [
                        'instance' => $this->instanceId
                    ]);
                }
                $this->soap = new \SoapClient(
                    $this->config->getBaseUrl() . '/' . $this->config->getEndpoint(),
                    $this->config->getOptions()
                );
                $this->connected = true;
                if( !is_null($this->logger) ) {
                    $this->logger->info("Webservice connection successful", [
                        'instance' => $this->instanceId
                    ]);
                }
            } catch ( \SoapFault $e ) {
                $this->logger->critical("Webservice connection error", [
                    'instance' => $this->instanceId,
                    'response' => self::soapFaultToArray( $e )
                ]);
                $sf = new ConnectionException(
                    self::soapFaultToString($e),
                    __FILE__,
                    __LINE__
                );
                self::setConnectionError( $e );
                self::errorHandler( $sf );
            }
        } else {
            if( !is_null($this->logger) ) {
                $this->logger->critical("Webservice's base url isn't responding", [
                    'instance' => $this->instanceId,
                    'response' => $exists->getMessage()
                ]);
            }

            $e = new BaseUrlNotRespondingException( $this->config->getBaseUrl(), $exists->getMessage(), __FILE__, __LINE__);
            self::setConnectionError( $e );
            self::errorHandler( $e );
        }
    }

    /**
     * @param RequestParams $_params
     * @return ModelResult
     * @throws \Exception
     */
    public function call( RequestParams $_params )
    {
        $execId = md5( uniqid() );

        $retorno = new ModelResult(false, '');

        $execucao = null;

        $info = [
            'instance' => $this->instanceId,
            'execId' => $execId
        ];

        if ( self::isConnected() ) {
            try {
                if( !is_null($this->logger) ) {
                    $infoLog = $this->config->getLogConfiguration()->isLevel( LogConfiguration::DEBUG )
                        ? array_merge($info, [
                            'url' => $this->config->getBaseUrl() . '/' . $this->config->getEndpoint(),
                            'timeout' => $this->config->getTimeout(),
                            'options' => $this->config->getOptions(),
                            'method' => $_params->getMethod(),
                            'args' => $_params->getArgs()
                        ])
                        : [];
                    $this->logger->info("Trying to call endpoints's \"{$this->config->getBaseUrl()}/{$this->config->getEndpoint()}\" method \"{$_params->getMethod()}\"", $infoLog);
                }
                $execucao = $this->soap->__soapCall( $_params->getMethod(), $_params->getArgs() );
                if( !is_null($this->logger) && $execucao) {
                    $this->logger->info(
                        "Method \"{$_params->getMethod()}\" executed",
                        $this->config->getLogConfiguration()->isLevel( LogConfiguration::DEBUG )
                            ? array_merge( $info, [
                                'response' => $execucao
                            ] )
                            : $info
                    );

                }
            } catch (\SoapFault $sf) {
                $this->logger->critical(
                    "Method execution failed",
                    array_merge( $info, [
                        'response' => self::soapFaultToArray( $sf )
                    ] )
                );
                $sf = new CallException(
                    self::soapFaultToString( $sf ),
                    __FILE__,
                    __LINE__
                );
                self::fillReturnObject( $retorno, ExceptionLevel::ERRLVL_CALL );
                self::errorHandler( $sf );
            }
        } else {
            self::fillReturnObject( $retorno, ExceptionLevel::ERRLVL_CON );
            self::handleResultAsError(ExceptionLevel::ERRLVL_CON, __FILE__, __LINE__);
        }

        if ( is_object( $execucao ) ) {
            $struct = $_params->getStruct();

            if ( property_exists( $execucao, $struct['estrutura'] ) ) {
                if ( property_exists( $execucao->$struct['estrutura'], $struct['status'] ) ) {
                    $message = $execucao->$struct['estrutura']->$struct['msg'];

                    if ( property_exists( $execucao->$struct['estrutura'], $struct['field'] ) ) {
                        $message .= ' ' . $execucao->$struct['estrutura']->$struct['field'];
                    }

                    $retorno->setMessage($message);

                    $comparison = $_params->getComparison();

                    if ( $comparison( $execucao->$struct['estrutura']->$struct['status'], $execucao->$struct['estrutura'] ) ) {
                        $retorno->setStatus( true );

                        if ( isset( $struct['extra'] ) && count( $struct['extra'] ) > 0 ) {
                            $resultados = [];

                            foreach ( $struct['extra'] as $itemExtra ) {
                                $resultados[$itemExtra] = property_exists(
                                    $execucao->$struct['estrutura'],
                                    $itemExtra
                                )
                                    ? $execucao->$struct['estrutura']->$itemExtra
                                    : null;
                            }

                            $retorno->setResult( $resultados );
                        } else {
                            $retorno->setResult([
                                $struct['status'] => $execucao->$struct['estrutura']->$struct['status']
                            ]);
                        }
                    } else {
                        if ( trim( $retorno->getMessage() ) == '' ) {
                            $lastResponse = $this->soap->__getLastResponse();

                            if ( $this->config->isModeDebug() ) {
                                $retorno->setMessage( "SOAP ERROR: " . print_r($lastResponse, true) );
                            } else {
                                $retorno->setMessage( ExceptionLevel::getMessage(ExceptionLevel::ERRLVL_VALIDATION) );
                            }
                        }
                        $retorno->setResult(ExceptionLevel::ERRLVL_VALIDATION);
                    }
                } else {
                    self::fillReturnObject( $retorno, ExceptionLevel::ERRLVL_INTERNALSTRUCT );
                    self::handleResultAsError(ExceptionLevel::ERRLVL_INTERNALSTRUCT, __FILE__, __LINE__);
                }
            } else {
                self::fillReturnObject( $retorno, ExceptionLevel::ERRLVL_STRUCT );
                self::handleResultAsError(ExceptionLevel::ERRLVL_STRUCT, __FILE__, __LINE__);
            }
        }

        return $retorno;
    }

    public function isConnected()
    {
        return $this->connected;
    }

    protected function setConnectionError( \Exception $e )
    {
        $this->connectionErrorString = $e->getMessage();
    }

    public function getConnectionError()
    {
        return $this->connectionErrorString;
    }

    /**
     * @param \Exception $e
     * @throws \Exception
     */
    protected function errorHandler( \Exception $e )
    {
        if ($this->config->getThrowExceptions()) {
            throw $e;
        }
    }

    protected function fillReturnObject(ModelResult $_obj, $_errLevel)
    {
        $_obj
            ->setMessage( ExceptionLevel::getMessage( $_errLevel ) )
            ->setResult( $_errLevel )
        ;
    }

    /**
     * @param $_errLvl
     * @param $_file
     * @param $_line
     * @throws \Exception
     */
    protected function handleResultAsError( $_errLvl, $_file, $_line )
    {
        $e = ExceptionLevel::getExceptionByLevel( $_errLvl, $_file, $_line );
        self::errorHandler($e);
    }

    protected function soapFaultToString( \SoapFault $_sf )
    {
        $ret = '[Code] ' . $_sf->faultcode .
             "\n[Message] " . $_sf->getMessage();
        if(!is_null($this->soap)) {
            $ret .= "\n[LastRequestHeaders] " . $this->soap->__getLastRequestHeaders() .
                    "\n[LastRequest] " . $this->soap->__getLastRequest() .
                    "\n[LastResponse] " . $this->soap->__getLastResponse();
        }
        return $ret;
    }

    protected function soapFaultToArray( \SoapFault $_sf )
    {
        $ret = [
            'code' => $_sf->faultcode,
            'message' => $_sf->getMessage()
        ];

        if(!is_null($this->soap)) {
            $ret['lastRequestHeaders'] = $this->soap->__getLastRequestHeaders();
            $ret['lastRequest'] = $this->soap->__getLastRequest();
            $ret['lastResponse'] = $this->soap->__getLastResponse();
        }

        return $ret;
    }
}