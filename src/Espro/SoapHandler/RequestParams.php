<?php 
namespace Espro\SoapHandler;

class RequestParams {
	protected $method = '';
	protected $args = [];
	/**
	 * Estrutura de retorno do método
	 *
	 * O array informado deve conter o seguinte formato:
	 * array($estrutura, $status, $msg [, $field [, $extra]]
	 * $estrutura: Estrutura de retorno do WebService chamado
	 * $status: Campo de status do retorno do WebService
	 * $msg: Campo onde vem a mensagem de sucesso ou descrição do erro que ocorreu na chamada
	 * $field: Campo que complementa a mensagem de erro. Normalmente só existe no WS do banco de talentos
	 * $extra: array indexado numericamente, que pode conter nomes de campos não contemplados normalmente no retorno do WS. Pode receber
	 * quantos campos forem necessários, e retorna um array associativo quando a execução ocorre corretamente
	 *
	 * @var array
	 */
	protected $struct = [];
	/**
	 * Valor para comparação de acordo com a estrutura do webservice 
	 * @var mixed
	 */
	protected $comparison;

	public function __construct($_comparison = null) {
        if(!is_callable($_comparison)) {
            $this->comparison = function ($_status) {
                return $_status == 1;
            };
        }
	}

	/**
	 * @param string $_method
     * @return RequestParams
	 */
	public function setMethod($_method = '') {
		$this->method = $_method;
        return $this;
	}

	/**
	 * @return string
	 */
	public function getMethod() {
		return $this->method;
	}

	/**
	 * @param array $_args
	 * @return RequestParams
	 */
	public function setArgs(array $_args = []) {
		$this->args = $_args;
        return $this;
	}

	/**
	 * @return array
	 */
	public function getArgs() {
		return $this->args;
	}

	/**
	 * @param array $_struct
	 * @return RequestParams
	 */
	public function setStruct(array $_struct = []) {
		$this->struct = $_struct;
        return $this;
	}

	/**
	 * @return array
	 */
	public function getStruct() {
		return $this->struct;
	}

	/**
	 * @param \Closure $_value
	 * @return RequestParams
	 */
	public function setComparison(\Closure $_value) {
		$this->comparison = $_value;
        return $this;
	}

	/**
	 * @return \Closure
	 */
	public function getComparison() {
		return $this->comparison;
	}
}