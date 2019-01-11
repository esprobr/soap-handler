<?php
namespace Espro\SoapHandler;

use Espro\SoapHandler\Exception\BaseUrlNotRespondingException;
use Espro\SoapHandler\Exception\CallException;
use Espro\SoapHandler\Exception\ConnectionException;
use Espro\SoapHandler\Exception\ExceptionLevel;
use Espro\Utils\ModelResult;
use Espro\Utils\Url;
use Monolog\Handler\StreamHandler;
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
     * SoapHandler constructor.
     * @param Configuration $_config
     * @throws \Exception
     */
    public function __construct( Configuration $_config )
    {
        $this->config = $_config;

        if( !is_null( $this->config->getLogConfiguration() ) ) {
            $this->logger = new Logger( $this->config->getLogConfiguration()->getChannel() );
            $this->logger->pushHandler(
                new StreamHandler(
                    $this->config->getLogConfiguration()->getPath(),
                    $this->config->getLogConfiguration()->getLevel()
                )
            );
        }

        if( !is_null($this->logger) ) {
            $this->logger->info("Checking if webservice's base url exists", [
                'baseUrl' => $this->config->getBaseUrl(),
                'timeout' => $this->config->getTimeout()
            ]);
        }
        $exists = Url::exists( $this->config->getBaseUrl(),  $this->config->getTimeout() );

        if ( $exists->getStatus() ) {
            if( !is_null($this->logger) ) {
                $this->logger->info("Webservice's base url is active", [
                    'baseUrl' => $this->config->getBaseUrl(),
                    'timeout' => $this->config->getTimeout()
                ]);
            }

            try {
                if( !is_null($this->logger) ) {
                    $this->logger->info("Trying to connect", [
                        'url' => $this->config->getBaseUrl() . '/' . $this->config->getEndpoint(),
                        'timeout' => $this->config->getTimeout(),
                        'options' => $this->config->getOptions()
                    ]);
                }
                $this->soap = new \SoapClient(
                    $this->config->getBaseUrl() . '/' . $this->config->getEndpoint(),
                    $this->config->getOptions()
                );
                $this->connected = true;
                if( !is_null($this->logger) ) {
                    $this->logger->info("Webservice connection successful", [
                        'url' => $this->config->getBaseUrl() . '/' . $this->config->getEndpoint(),
                        'timeout' => $this->config->getTimeout(),
                        'options' => $this->config->getOptions()
                    ]);
                }
            } catch ( \SoapFault $e ) {
                $this->logger->critical("Webservice connection error", [
                    'url' => $this->config->getBaseUrl() . '/' . $this->config->getEndpoint(),
                    'timeout' => $this->config->getTimeout(),
                    'options' => $this->config->getOptions(),
                    'details' => self::soapFaultToArray($e)
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
                    'baseUrl' => $this->config->getBaseUrl(),
                    'timeout' => $this->config->getTimeout(),
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
        $retorno = new ModelResult(false, '');

        $execucao = null;

        if ( self::isConnected() ) {
            try {
                if( !is_null($this->logger) ) {
                    $this->logger->info("Trying to call method", [
                        'url' => $this->config->getBaseUrl() . '/' . $this->config->getEndpoint(),
                        'timeout' => $this->config->getTimeout(),
                        'options' => $this->config->getOptions(),
                        'method' => $_params->getMethod(),
                        'args' => $_params->getArgs()
                    ]);
                }
                $execucao = $this->soap->__soapCall( $_params->getMethod(), $_params->getArgs() );
                if( !is_null($this->logger) && $execucao) {
                    $this->logger->info("Method executed", [
                        'url' => $this->config->getBaseUrl() . '/' . $this->config->getEndpoint(),
                        'timeout' => $this->config->getTimeout(),
                        'options' => $this->config->getOptions(),
                        'method' => $_params->getMethod(),
                        'args' => $_params->getArgs(),
                        'response' => $execucao
                    ]);
                }
            } catch (\SoapFault $sf) {
                $this->logger->critical("Method execution failed", [
                    'url' => $this->config->getBaseUrl() . '/' . $this->config->getEndpoint(),
                    'timeout' => $this->config->getTimeout(),
                    'options' => $this->config->getOptions(),
                    'method' => $_params->getMethod(),
                    'args' => $_params->getArgs(),
                    'response' => self::soapFaultToArray( $sf )
                ]);
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