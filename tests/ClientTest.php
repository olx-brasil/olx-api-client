<?php

use OlxApiClient\Client;
use org\bovigo\vfs\vfsStream;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    public function testConfigFileExists()
    {
        vfsStream::setup('root', 644);
        file_put_contents(vfsStream::url('root').DIRECTORY_SEPARATOR.'config.json', json_encode(array(
            'client_id' => 'efbd522be22d67e1ec7224283dcd5dbad9d594d9',
            'scope' => 'basic_user_info',
            'redirect_uri' => 'http://www.vitormattos.com.br/return_page.php',
            'client_secret' => 'a315f7cc0ae584a3da8ef0f6092693fb',
        )));
        $client = new Client(vfsStream::url('root').DIRECTORY_SEPARATOR.'config.json');
        $config = PHPUnit_Framework_Assert::readAttribute($client, 'config');
        $this->assertObjectHasAttribute('client_id', $config);
        $this->assertObjectHasAttribute('scope', $config);
        $this->assertObjectHasAttribute('redirect_uri', $config);
        $this->assertEquals('efbd522be22d67e1ec7224283dcd5dbad9d594d9', $config->client_id);
        $this->assertEquals('basic_user_info', $config->scope);
        $this->assertEquals('http://www.vitormattos.com.br/return_page.php', $config->redirect_uri);

        return $client;
    }

    public function testConfigNull()
    {
        $client = new Client();
        $config = PHPUnit_Framework_Assert::readAttribute($client, 'config');
        $this->assertObjectHasAttribute('response_type', $config);
        $this->assertEquals('code', $config->response_type);
        $this->assertObjectNotHasAttribute('client_id', $config);
        $this->assertObjectNotHasAttribute('scope', $config);
        $this->assertObjectNotHasAttribute('redirect_uri', $config);
        $this->assertObjectNotHasAttribute('state', $config);
    }

    public function testSetConfig()
    {
        $client = new Client();
        $client->setConfig('client_id', '123');
        $client->setConfig('scope', 'basic_user_info');
        $client->setConfig('redirect_uri', 'https://teste.com.br/return_page');
        $client->setConfig('state', 'xxxxx');
        $config = PHPUnit_Framework_Assert::readAttribute($client, 'config');
        $this->assertObjectHasAttribute('client_id', $config);
        $this->assertObjectHasAttribute('scope', $config);
        $this->assertObjectHasAttribute('redirect_uri', $config);
        $this->assertObjectHasAttribute('state', $config);
        $this->assertEquals('123', $config->client_id);
        $this->assertEquals('basic_user_info', $config->scope);
        $this->assertEquals('https://teste.com.br/return_page', $config->redirect_uri);
        $this->assertEquals('xxxxx', $config->state);
    }

    public function testSetConfigKeyInvalid()
    {
        $client = new Client();
        $this->setExpectedException('\OlxApiClient\Olx_Auth_Exception', 'Chave fornecida [key_invalida] é inválida para o config');
        $client->setConfig('key_invalida', '123');
    }

    public function testSetConfigValueInvalidScope()
    {
        $client = new Client();
        $this->setExpectedException('\OlxApiClient\Olx_Auth_Exception', 'Valor inválido (123) para Chave fornecida (scope)');
        $client->setConfig('scope', '123');
    }

    public function testSetConfigValueInvalid()
    {
        $client = new Client();
        $reflectionClass = new ReflectionClass('\OlxApiClient\Client');
        $reflectionProperty = $reflectionClass->getProperty('struct_config');
        $reflectionProperty->setAccessible(true);
        $struct = $reflectionProperty->getValue($client);
        $struct['client_id'] = array('obrigatorio' => true, 'valores' => array('xpto'));
        $reflectionProperty->setValue($client, $struct);
        $this->setExpectedException('\OlxApiClient\Olx_Auth_Exception', 'Valor inválido (x123) para Chave fornecida (client_id)');
        $client->setConfig('client_id', 'x123');
    }

    public function testSetConfigValueValid()
    {
        $client = new Client();
        $client->setConfig('scope', 'basic_user_info autoupload');
        $config = PHPUnit_Framework_Assert::readAttribute($client, 'config');
        $this->assertEquals('basic_user_info autoupload', $config->scope);
    }

    public function testCreateAuthUrlInvalidConfig()
    {
        $client = new Client();
        $url = 'https://auth.olx.com.br/oauth/init/0?';

        $client_id = 'efbd522be22d67e1ec7224283dcd5dbad9d594d9';
        $scope = 'basic_user_info';
        $redirect_uri = 'www.google.com.br';

        $client->setConfig('client_id', $client_id);
        $client->setConfig('scope', $scope);

        $url_ret = $url.'response_type=code'.'&client_id='.$client_id.'&scope='.$scope.'&redirect_uri='.$redirect_uri;

        $this->setExpectedException('OlxApiClient\Olx_Auth_Exception', 'Valor obrigatório não preenchido no config [redirect_uri].');
        $this->assertEquals($url_ret, $client->createAuthUrl());
    }

    /**
     * @depends testConfigFileExists
     */
    public function testCreateAuthUrl($client)
    {
        $this->assertEquals('https://auth.olx.com.br/oauth/?client_id=efbd522be22d67e1ec7224283dcd5dbad9d594d9&scope=basic_user_info&redirect_uri=http%3A%2F%2Fwww.vitormattos.com.br%2Freturn_page.php&response_type=code', $client->createAuthUrl());
    }

    /**
     * @depends testConfigFileExists
     */
    public function testAuthenticateInvalidCode($client)
    {
        $this->setExpectedException(
            '\OlxApiClient\Olx_Auth_Exception', 'Invalid code'
        );
        $client->authenticate('');
    }

    /**
     * @depends testConfigFileExists
     */
    public function testAuthenticateResponseErrorCode($client)
    {
        $reflectionClass = new ReflectionClass('\OlxApiClient\Client');
        $this->setExpectedException('\OlxApiClient\Olx_Auth_Exception', 'Invalid code');
        $client->authenticate(str_repeat('a', 41));
    }

    /**
     * @depends testConfigFileExists
     */
    public function testAuthenticateResponseError($client)
    {
        $reflectionClass = new ReflectionClass('\OlxApiClient\Client');
        $reflectionProperty = $reflectionClass->getProperty('request');
        $reflectionProperty->setAccessible(true);

        $request = $this->getMock('\OlxApiClient\OlxHttpRequest', array('executeRequest'), array('www.teste.com.br'));
        $request->expects($this->any())
            ->method('executeRequest')
            ->will($this->returnValue(array(
                'http_code' => 400,
                'body' => '[{"property":"access_token","message":"is missing and it is required"}]',
            )));

        $reflectionProperty->setValue($client, $request);
        $ret = $client->authenticate(sha1('b'));
        $this->assertEquals('is missing and it is required', $ret[0]->message);
    }

    /**
     * @depends testConfigFileExists
     */
    public function testAuthenticateResponseSuccess($client)
    {
        $reflectionClass = new ReflectionClass('\OlxApiClient\Client');
        $reflectionProperty = $reflectionClass->getProperty('request');
        $reflectionProperty->setAccessible(true);

        $request = $this->getMock('\OlxApiClient\OlxHttpRequest', array('executeRequest'), array('www.teste.com.br'));
        $request->expects($this->any())
            ->method('executeRequest')
            ->will($this->returnValue(array(
                'http_code' => 200,
                'body' => json_encode(array('access_token' => sha1('a'), 'token_type' => 'Bearer')),
            )));

        $reflectionProperty->setValue($client, $request);
        $ret = $client->authenticate(sha1('b'));
        $this->assertInternalType('string', $ret);
        $this->assertEquals(40, strlen($ret));
    }

    /**
     * @depends testConfigFileExists
     */
    public function testInvalidCode($client)
    {
        $this->setExpectedException('\OlxApiClient\Olx_Auth_Exception', 'Invalid code');
        $client->authenticate(str_repeat('a', 41));
    }
}
