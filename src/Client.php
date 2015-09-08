<?php

namespace OlxApiClient;

class Olx_Auth_Exception extends \Exception
{
}

/**
 * Classe de Autenticação ao serviço de Oauth da Olx.
 *
 * @copyright Olx / 2015
 */
class Client
{
    /**
     * @var array
     *            Estrutura para validacao de chaves do config
     */
    private $struct_config = array(
        'client_id' => array(
            'obrigatorio' => true
        ),
        'scope' => array(
            'obrigatorio' => true,
            'valores' => array('basic_user_info','autoupload'),
        ),
        'redirect_uri' => array(
            'obrigatorio' => true
        ),
        'state' => array(
            'obrigatorio' => false
        ),
        'client_secret' => array(
            'obrigatorio' => false
        ),
        'apps_url' => array(
            'obrigatorio' => false
        ),
        'auth_url' => array(
            'obrigatorio' => false
        ),
        'curlOpts' => array(
            'obrigatorio' => false
        )
    );

    /**
     * @var stdClass Objeto de configuração de acordo com o atributo json da instancia d classe
     *
     * Atributo da classe que será preenchido com um array de configuração.
     * O array de configuração será preenchdo do acordo com um arquivo json que
     * será passado na instância da classe conforme descrito a seguite:
     * Conteúdo do arquivo json:
     *	{
     *		"client_id":"9876",
     *		"scope":"basic_user_info",
     *		"redirect_uri":"http://www.site.com.br",
     *		"state":"ativo"
     *	}
     *   Onde:
     *		<string> client_id: (Obrigatório)
     *			Identifica o cliente que está enviando a requisição.
     *			O valor do parâmetro tem que ser idêntico ao valor fornecido pela olx.com.br durante o registro da aplicação.
     *			A identificação do cliente que foi fornecida pelo olx.com.br através do registro da aplicação.
     *
     *		<string> scope: coma as opçoes:  (Obrigatório)
     *			 - basic_user_info (Permite acesso as informações básicas do usuário. Ex: nome completo e email)
     *			 - autoupload (Permite acesso ao sistema de autouploads (Envio de anúncios de forma automática))
     *
     *		<url> redirect_uri:	(Obrigatório)
     *			Determina para qual servidor a resposta da requisição será enviada. O valor do parâmetro
     *			tem que ser idêntico a um dos URI cadastrados no registro da aplicação no olx.com.br.
     *
     * 		<string> state: (opcional)
     *			Fornece qualquer valor que pode ser útil a aplicação ao receber a resposta de requisição.
     */
    private $config;

    private $request;

    /**
     * @param string $config Path do arquivo json de configuração
     *
     * @see Client::setConfig()
     * @see Client::createAuthUrl()
     *
     * Construtor da classe que seta o arquivo de configuração
     *
     * Obs:
     * 		Não é obrigatório na instância da classe passar a path do json, pode ser passado null como argumento, porém
     *		Será necessário setar cada parâmetro (client_id, scope, redirect_uri) com o método setConfig para poder gerar
     *  		a url corretamente.
     */
    public function __construct($config = null)
    {
        if ($config) {
            $this->config = json_decode(file_get_contents($config));
        } else {
            $this->config = new \StdClass();
        }
        $this->config->response_type = 'code';
        if (!isset($this->config->auth_url)) {
            $this->config->auth_url = 'https://auth.olx.com.br/oauth';
        }
        if (!isset($this->config->apps_url)) {
            $this->config->apps_url = 'https://apps.olx.com.br/oauth';
        }
        if (!isset($this->config->curlOpts)) {
            $this->config->curlOpts = array();
        }
        $this->request = new \OlxApiClient\OlxHttpRequest($this->config->auth_url, $this->config->curlOpts);
    }

    /**
     * @param string $key   Nome da chave do config que receberá o conteúdo do segundo parâmetro
     * @param string $value mixed valor que será armazenado na chave do config fornecido pelo primeiro parâmentro
     *
     * @see Client::$config
     * @see Client::validConfig
     *
     * Sobrescreve as chaves do config
     */
    public function setConfig($key, $value)
    {
        $this->validConfigItem($key, $value);
        $this->config->$key = $value;
    }

    /**
     * Cria a url para ser usada no link para pagina da olx de liberação do serviço pelo cliente com Oauth.
     * Ele usa os dados setados no config para gerar a url.
     *
     * @see Client::setConfig
     */
    public function createAuthUrl()
    {
        $this->validConfig();
        $query_string = filter_var_array((array) $this->config, array(
            'client_id' => FILTER_SANITIZE_STRING,
            'scope' => FILTER_SANITIZE_STRING,
            'redirect_uri' => FILTER_SANITIZE_STRING,
            'response_type' => FILTER_SANITIZE_STRING,
        ));
        $auth_url = filter_var($this->config->auth_url, FILTER_SANITIZE_STRING);

        return $auth_url.'/?'.\http_build_query(array(
            'client_id' => $query_string['client_id'],
            'scope' => $query_string['scope'],
            'redirect_uri' => $query_string['redirect_uri'],
            'response_type' => $query_string['response_type'],
        ));
    }

    public function authenticate($code)
    {
        if (!is_string($code) || strlen($code) != 40) {
            throw new Olx_Auth_Exception('Invalid code');
        }

        $arguments = array(
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_id' => $this->config->client_id,
            'client_secret' => $this->config->client_secret,
            'redirect_uri' => $this->config->redirect_uri,
        );

        $this->request->method = 'POST';
        $this->request->postBody = \http_build_query($arguments);
        $this->request->url = $this->config->auth_url.'/token';
        $return = $this->request->executeRequest();
        $return['body'] = json_decode($return['body']);
        if (isset($return['body']->access_token)) {
            return $this->access_token = $return['body']->access_token;
        }

        return $return['body'];
    }

    /**
     * @param string $key   Nome da chave a ser validada
     * @param string $value mixed valor que será validado
     *
     * @see Client::setConfig
     *
     * @return bool
     *
     * Valida a chave e o valor que será setado no config
     */
    private function validConfigItem($key, $value)
    {
        if (!isset($this->struct_config[$key])) {
            throw new Olx_Auth_Exception("Chave fornecida [$key] é inválida para o config");
        } else {
            if (isset($this->struct_config[$key]['valores']) && !in_array($value, $this->struct_config[$key]['valores'])) {
                if ($key == 'scope') {
                    $array_scope = explode(' ', $value);
                    foreach ($array_scope as $v) {
                        if (!in_array($v, $this->struct_config[$key]['valores'])) {
                            throw new Olx_Auth_Exception("Valor inválido ($value) para Chave fornecida ($key)");
                        }
                    }
                } else {
                    throw new Olx_Auth_Exception("Valor inválido ($value) para Chave fornecida ($key)");
                }
            }
        }
    }

    /**
     * @see Client:createAuthUrl
     *
     * @return bool
     *
     * Valida se todas as informações necessárias estão no config para que seja gerada a url de autenticação
     */
    private function validConfig()
    {
        foreach ($this->struct_config as $key => $value) {
            if ($value['obrigatorio'] && !isset($this->config->$key)) {
                throw new Olx_Auth_Exception("Valor obrigatório não preenchido no config [$key].");
            }
        }
    }

    /**
     * @codeCoverageIgnore
     */
    public function call($name, $arguments, $method = 'POST')
    {
        $this->request->method = $method;
        $this->request->postBody = $arguments;
        $this->request->url = $this->config->apps_url.'_api/'.$name;

        return $this->request->executeRequest();
    }
}
