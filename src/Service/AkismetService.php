<?php

namespace SilverStripe\Akismet\Service;

use SilverStripe\Control\Director;
use Exception;

class AkismetService
{
    private $apiKey;

    private $endpoint;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
        $this->endpoint =  sprintf('https://%s.rest.akismet.com/1.1/', $apiKey);
    }


    public function verifyKey()
    {
        $response = $this->post($this->endpoint . 'verify-key', [
            'key' => $this->apiKey,
            'blog' => Director::protocolAndHost()
        ]);

        return 'valid' == trim(strtolower($response['body']));
    }


    public function buildData($content, $author = null, $email = null, $url = null, $permalink = null, $server = [])
    {
        $data = [
            'blog' => Director::protocolAndHost(),
            'user_ip' => (isset($server['REMOTE_ADDR'])) ? $server['REMOTE_ADDR'] : '',
            'user_agent' => (isset($server['HTTP_USER_AGENT'])) ? $server['HTTP_USER_AGENT'] : '',
            'referrer' => (isset($server['HTTP_REFERER'])) ? $server['HTTP_REFERER'] : '',
            'permalink' => $permalink,
            'comment_type' => 'comment',
            'comment_author' => $author,
            'comment_author_email' => $email,
            'comment_author_url' => $url,
            'comment_content' => $content,
        ];

        return $data;
    }


    public function isSpam($content, $author = null, $email = null, $url = null, $permalink = null, $server = null)
    {
        if (is_null($server)) {
            $server = $_SERVER;
        }

        $data = $this->buildData($content, $author, $email, $url, $permalink, $server);
        $response = $this->checkSpam($data, $server);

        return (isset($response['spam']) && $response['spam']);
    }


    public function submitSpam($content, $author = null, $email = null, $url = null, $permalink = null, $server = null)
    {
        if (is_null($server)) {
            $server = $_SERVER;
        }

        $data = $this->buildData($content, $author, $email, $url, $permalink);
        $this->post($this->endpoint . 'submit-spam', $data);
    }


    public function checkSpam($data, $state = [])
    {
        $keys = array_intersect_key($state, array_fill_keys([
            'HTTP_HOST', 'HTTP_USER_AGENT', 'HTTP_ACCEPT', 'HTTP_ACCEPT_LANGUAGE', 'HTTP_ACCEPT_ENCODING',
            'HTTP_ACCEPT_CHARSET', 'HTTP_KEEP_ALIVE', 'HTTP_REFERER', 'HTTP_CONNECTION', 'HTTP_FORWARDED',
            'HTTP_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP',
            'REMOTE_ADDR', 'REMOTE_HOST', 'REMOTE_PORT', 'SERVER_PROTOCOL', 'REQUEST_METHOD'],
            0
        ));

        $data = array_merge($keys, $data);
        $response = $this->post($this->endpoint . 'comment-check', $data);
        $response['error'] = $response['discard'] = $response['spam'] = null;

        $body = trim(strtolower($response['body']));

        if ('true' == $body) {
            $response['spam'] = true;

            if ( array_key_exists('x-akismet-pro-tip', $response['akismet_headers']) && $response['akismet_headers']['x-akismet-pro-tip'] == 'discard' ) {
                $response['discard'] = true;
            }
        } else if ('false' == $body) {
            $response['spam'] = false;
        } else if (array_key_exists('x-akismet-debug-help', $response['akismet_headers'])) {
            $response['error'] = $response['akismet_headers']['x-akismet-debug-help'];
        }

        return $response;
    }

    protected function post($endpoint, $data)
    {
        $response = [];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        $curl_response = curl_exec($ch);

        if (false === $curl_response) {
            throw new Exception('There was an error sending the Akismet request.');
        }

        $response['info'] = curl_getinfo($ch);
        $response['info']['request_header'] .= http_build_query($data);
        $response['header'] = substr($curl_response, 0, $response['info']['header_size']);
        $response['body'] = substr($curl_response, $response['info']['header_size']);

        $response['akismet_headers'] = [];

        foreach (explode("\n", $response['header']) as $header) {
            if (stripos($header, 'x-akismet') === 0) {
                list($key, $value) = explode(':', $header, 2);
                $response['akismet_headers'][strtolower($key)] = $value;
            }
        }

        curl_close($ch);

        return $response;
    }
}
