[![Build Status](https://travis-ci.org/olxbr/api-client.svg?branch=master)](https://travis-ci.org/olxbr/api-client)
[![Coverage Status](https://coveralls.io/repos/olxbr/api-client/badge.svg?branch=master&service=github)](https://coveralls.io/github/olxbr/api-client?branch=master)
[![Latest Stable Version](https://poser.pugx.org/olxbr/olx-api-client/v/stable)](https://packagist.org/packages/olxbr/olx-api-client)
[![License](https://poser.pugx.org/olxbr/olx-api-client/license)](https://packagist.org/packages/olxbr/olx-api-client)

# Olx Oauth API

Este documento descreve como utilizar o protocolo oAuth 2.0 como forma de
autenticação na API olx.com.br através de uma aplicação web. 

OAuth 2.0 é um protocolo relativamente simples. No início, você registra sua
aplicação no olx.com.br, depois a aplicação solicita uma chave de acesso ao
servidor de autenticação do olx.com.br e então utiliza essa chave para receber
as informações de um recurso da API olx.com.br que deseja acessar.

## Primeiro passo

Quer integrar com a OLX? Envie um e-mail para integracao@olxbr.com

## Criando uma aplicação
Antes de iniciar o protocolo de autenticação com o servidor olx.com.br, o
cliente deverá registrar sua aplicação, fornecendo os seguintes dados:

* Nome do cliente
* Nome da aplicação
* Descrição da aplicação
* Website
* Telefone
* E-mail
* URIs de redirecionamento (identifica um end-point do cliente que será alvo de
redirecionamento no processo de autenticação; mínimo 1 e máximo 3; ver seção
2.1)

Após receber os dados, o olx.com.br entrará em contato com o cliente para
fornecer sua identificação client_id e sua chave de segurança, necessários para
iniciar a sequência de autorização.

## Instalação via composer

    $ composer require olxbr/olx-api-client

## Instalação manual

> Recomendamos fortemente o uso de composer para que você possa sempre ter a
> versão mais nova de nossa classe sempre que executar o composer update.

Baixe as duas classes que estão em src e faça a include delas em seu projeto
manualmente.

## Exemplo completo de uso da API

O exemplo que segue irá exibir os dados de um usuário que autorizou a
autenticação via oauth e está utilizando o autoload do composer.


Crie um arquivo de configuração em sua aplicação com os dados recebidos pela
olx.com.br para uso de nossa api:

### olx_oauth_secrets.json
Todas as informações que seguem devem ser específicas para o seu projeto, elas
são informadas pela Olx. A redirect_uri deve ser a mesma que você cadastrou em
nosso sistema, do contrário não conseguirá utilizar a API.
```json
{
	"client_id":"4414d3003b0794ba3dfc8da29493c8497cf352e7",
	"client_secret": "ed63d0a437cd6f9db6299ffe779bca81",
	"scope":"basic_user_info autoupload",
	"redirect_uri":"http://www.suapagina.com.br/return_page.php"
}
```
Insira o código que segue na página onde irá exibir as consultas a api da Olx,
neste exemplo utilizamos o index.php na raiz do projeto. Lembre-se de colocar o
caminho correto para o arquivo json ao instanciar a classe client.
### index.php
```php
<?php
use OlxApiClient\Client;
require 'vendor/autoload.php';
session_start();

$client = new Client('olx_oauth_secrets.json');
if (isset($_SESSION['olx_access_token'])) {
    $dadosUsuario = $client->call('basic_user_info', json_encode(array(
        'access_token' => $_SESSION['olx_access_token']
    )));
    echo '<h1>Dados do Usuario</h1>';
    var_dump(json_decode($dadosUsuario['body']));
} else {
    echo '<a href = '.$client->createAuthUrl().'>Autentica</a>';
}
```

Se o usuário conceder a permissão solicitada o servidor de autenticação gera um
código de autorização e envia-o para o cliente através dos seguintes parâmetros
na URI de redirecionamento:

| Parâmetro                                            | Valores                                                     | Descrição                                                                                                                                                       |
|------------------------------------------------------|-------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------|
| code (obrigatório)                                   | Código de autorização gerado pelo servidor de autenticação. | Código de autorização utilizado para solicitar permissão de acesso a recursos de um usuário. Expira 10 minutos após ter sido gerado e não pode ser reutilizado. |
| state (obrigatório se esteve presente na requisição) | Mesmo valor enviado pelo cliente na requisição              | Fornece qualquer valor que pode ser útil a aplicação ao receber a resposta de requisição.                                                                       |

### return_page.php
```php
<?php
use OlxApiClient\Client;
require 'vendor/autoload.php';
session_start();

$client = new Client('olx_oauth_secrets.json');

if (!isset($_GET['code'])) {
    echo '<a href = '.$client->createAuthUrl().'>Autentica</a>';
} else {
    $_SESSION['olx_access_token'] = $client->authenticate($_GET['code']);
    header('Location: http://'.$_SERVER['HTTP_HOST'].'/');
}
```
A return_page.php, após receber por GET a variável code, irá concluir a
autenticação do usuário por oauth e retornar o token, este token você deve 
armazenar em sua aplicação associado ao usuário para utilizá-lo sempre que
necessitar usar a nossa api.
