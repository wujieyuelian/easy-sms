<?php

/*
 * This file is part of the overtrue/easy-sms.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Overtrue\EasySms\Gateways;

use Overtrue\EasySms\Contracts\MessageInterface;
use Overtrue\EasySms\Contracts\PhoneNumberInterface;
use Overtrue\EasySms\Exceptions\GatewayErrorException;
use Overtrue\EasySms\Support\Config;
use Overtrue\EasySms\Traits\HasHttpRequest;

/**
 * Class JSMSGateway.
 * ��������
 * @see https://api.sms.jpush.cn/v1/
 */
class JsmsGateway extends Gateway
{
    use HasHttpRequest;

    const URL = 'https://api.sms.jpush.cn/v1/';

    const SUCCESS_CODE = '200';

    private $appKey;
    private $masterSecret;

    /**
     * @param \Overtrue\EasySms\Contracts\PhoneNumberInterface $to
     * @param \Overtrue\EasySms\Contracts\MessageInterface     $message
     * @param \Overtrue\EasySms\Support\Config                 $config
     *
     * @return array
     *
     * @throws \Overtrue\EasySms\Exceptions\GatewayErrorException ;
     */
    public function send(PhoneNumberInterface $to, MessageInterface $message, Config $config)
    {
        $this->appKey = (string)$config->get('appKey');
        $this->masterSecret = (string)$config->get('masterSecret');
        $this->options = array_merge([
            'ssl_verify'  => false,
            'disable_ssl' => false
        ], array());

        $mobile = (string)$to->getNumber();
//        $msg = (string)$message->getData($this);

        $temp_id = $message->getTemplate($this);
        $temp_para = $message->getData($this);

        $path = 'messages';
        $body = array(
            'mobile'    => $mobile,
            'temp_id'   => $temp_id,
        );
        if (!empty($temp_para)) {
            $body['temp_para'] = $temp_para;
        }
        if (isset($time)) {
            $path = 'schedule';
            $body['send_time'] = $time;
        }
        $url = self::URL . $path;
        $result = $this->request1('POST', $url, $body);

        if (self::SUCCESS_CODE != $result['code']) {
            throw new GatewayErrorException($result['msg'], $result['code'], $result);
        }

        return $result;
    }

    private function request1($method, $url, $body = []) {
        $ch = curl_init();
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Connection: Keep-Alive'
            ),
            CURLOPT_USERAGENT => 'JSMS-API-PHP-CLIENT',
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 120,

            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->appKey . ":" . $this->masterSecret,

            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
        );
        if (!$this->options['ssl_verify']
            || (bool) $this->options['disable_ssl']) {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        }
        if (!empty($body)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $options);
        $output = curl_exec($ch);

        if($output === false) {
//            return "Error Code:" . curl_errno($ch) . ", Error Message:".curl_error($ch);
            $response['code'] = -1;
            $response['msg'] = curl_error($ch);
        } else {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
//            $header_text = substr($output, 0, $header_size);
            $body = substr($output, $header_size);
//            $headers = array();
//
//            foreach (explode("\r\n", $header_text) as $i => $line) {
//                if (!empty($line)) {
//                    if ($i === 0) {
//                        $headers[0] = $line;
//                    } else if (strpos($line, ": ")) {
//                        list ($key, $value) = explode(': ', $line);
//                        $headers[$key] = $value;
//                    }
//                }
//            }
//
//            $response['headers'] = $headers;
            $response['body'] = json_decode($body, true);
            $response['code'] = $httpCode;
            $response['msg'] = isset($response['body']['error']['message']) ? $response['body']['error']['message'] : '';
        }
        curl_close($ch);
        return $response;
    }

}
