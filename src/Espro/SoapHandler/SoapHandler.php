<?php
namespace Espro\SoapHandler;

use Espro\SoapHandler\Exception\BaseUrlNotRespondingException;
use Espro\SoapHandler\Exception\CallException;
use Espro\SoapHandler\Exception\ConnectionException;
use Espro\SoapHandler\Exception\ExceptionLevel;
use Espro\Utils\ModelResult;
use Espro\Utils\Url;

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
     * SoapHandler constructor.
     * @param Configuration $_config
     * @throws \Exception
     */
    public function __construct( Configuration $_config )
    {
        $this->config = $_config;

        $exists = Url::exists( $this->config->getBaseUrl(),  $this->config->getTimeout() );

        if ( $exists->getStatus() ) {
            try {
                $this->soap = new \SoapClient(
                    $this->config->getBaseUrl() . '/' . $this->config->getEndpoint(),
                    $this->config->getOptions()
                );
                $this->connected = true;
            } catch ( \SoapFault $e ) {
                $sf = new ConnectionException(
                    self::soapFaultToString($e),
                    __FILE__,
                    __LINE__
                );
                self::setConnectionError( $e );
                self::errorHandler( $sf );
            }
        } else {
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
                $execucao = $this->soap->__soapCall( $_params->getMethod(), $_params->getArgs() );
            } catch (\SoapFault $sf) {
                $sf = new CallException(
                    self::soapFaultToString($sf),
                    __FILE__,
                    __LINE__
                );
                self::fillReturnObject( $retorno, ExceptionLevel::ERRLVL_CALL );
                self::errorHandler($sf);
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

    protected function soapFaultToString(\SoapFault $_sf)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $ret = '[Code] ' . $_sf->faultcode .
             "\n[Message] " . $_sf->getMessage();
        if(!is_null($this->soap)) {
            $ret .= "\n[LastRequestHeaders] " . $this->soap->__getLastRequestHeaders() .
                    "\n[LastRequest] " . $this->soap->__getLastRequest() .
                    "\n[LastResponse] " . $this->soap->__getLastResponse();
        }
        return $ret;
    }
}