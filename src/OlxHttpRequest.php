<?php

namespace OlxApiClient;

class OlxHttpRequest
{
    public $url;
    public $postBody;
    public $ssl_verifypeer = true;
    public $curlOpts = array();

    public function __construct($url, $curlOpts = array())
    {
        $this->url = $url;
        $this->curlOpts = $curlOpts;
    }

    /**
     * @codeCoverageIgnore
     */
    public function executeRequest()
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $this->postBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
        ) + array_combine(array_keys((array) $this->curlOpts), array_values((array) $this->curlOpts)));
        $response = curl_exec($curl);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $result['header'] = substr($response, 0, $header_size);
        $result['body'] = substr($response, $header_size);
        $result['http_code'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $result['last_url'] = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
        curl_close($curl);

        return $result;
    }
}
