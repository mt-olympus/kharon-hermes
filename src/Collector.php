<?php

namespace Kharon\Hermes;

use Hermes\Api\Client;
use Zend\EventManager\Event;
use Zend\Http\PhpEnvironment\Request;

/**
 * @codeCoverageIgnore
 */
class Collector
{
    private $enabled;
    private $serviceName;
    private $apiKey;
    private $kharonDir;
    private $hermesLog;
    private $sourceRequest;

    private function prepareData($data, $request)
    {
        $data['date'] = microtime(true);
        $data['method'] = $request->getMethod();

        $header = $request->getHeader('X-Request-Id');
        if ($header) {
            $requestId = $header->getFieldValue();
            $data['request_id'] = $requestId;
        }
        $header = $request->getHeader('X-Request-Name');
        if ($header) {
            $requestName = $header->getFieldValue();
            $data['request_name'] = $requestName;
        }
        $header = $request->getHeader('X-Request-Time');
        if ($header) {
            $requestTime = $header->getFieldValue();
            $data['request_time'] = str_replace('ms','',$requestTime);
        }
        $header = $request->getHeader('X-Request-Depth');
        if ($header) {
            $requestTime = $header->getFieldValue();
            $data['request_depth'] = $requestTime;
        }

        if (!$request->isGet()) {
            $post = json_decode($request->getContent(), true, 100);
            unset($post['password']);
            $data['data'] = $post;
        }

        return $data;
    }

    public function __construct($config = [])
    {
        $this->enabled = $config['enabled'] ?? false;
        $this->serviceName = $config['service_name'];
        $this->apiKey = $config['api_key'];
        $this->kharonDir = $config['kharon_dir'];
        $this->hermesLog = $config['hermes_log'];
    }

    public function setSourceRequest($request)
    {
        $this->sourceRequest = $request;
    }

    public function attach(Client $hermes)
    {
        if ($this->enabled == false) {
            return;
        }

        $kharonDir = $this->kharonDir;
        $apiKey = $this->apiKey;
        $sourceRequest = $this->sourceRequest;
        $serviceName = $this->serviceName;

        $em = $hermes->getEventManager();
        $em->attach('request.pre', function (Event $e) use ($sourceRequest) {
            if ($sourceRequest == null) {
                return;
            }
            /* @var \Hermes\Api\Client $hermes */
            $hermes = $e->getTarget();

            $hermes->importRequestId($sourceRequest);
            $hermes->incrementRequestDepth($sourceRequest);
        }, 100);


        $em->attach('request.post', function (Event $e) use (
            $sourceRequest,
            $serviceName,
            $kharonDir,
            $apiKey
        ) {
            if ($sourceRequest == null) {
                return;
            }
            /* @var \Hermes\Api\Client $hermes */
            $hermes = $e->getTarget();
            $request = $hermes->getZendClient()->getRequest();

            if ($sourceRequest instanceof \Zend\Http\Request) {
                $sourceUri = $this->sourceRequest->getUriString();
            } elseif ($sourceRequest instanceof \Psr\Http\Message\RequestInterface) {
                $sourceUri = $sourceRequest->getUri()->__toString();
            } else {
                $sourceUri = 'console';
            }

            $data = [
                'status' => 1,
                'source' => [
                    'service' => $serviceName,
                    'server' => $_SERVER['SERVER_ADDR'],
                    'uri' => $sourceUri,
                ],
                'destination' => [
                    'service' => $hermes->getServiceName(),
                    'server' => $request->getUri()->getHost(),
                    'uri' => $request->getUriString(),
                ],
                'http_code' => $hermes->getZendClient()->getResponse()->getStatusCode(),
            ];

            if (!empty($apiKey)) {
                $data['api_key'] = $apiKey;
            }

            $data = $this->prepareData($data, $request);

            $logFile = !empty($this->hermesLog) ? $this->hermesLog : $kharonDir . '/success-' . getmypid() . '-' . microtime(true) . '.kharon';
            file_put_contents($logFile, json_encode($data, null, 100) . PHP_EOL);
        }, 100);

        $em->attach('request.fail', function (Event $e) use (
                $sourceRequest,
                $kharonDir,
                $apiKey) {
            if ($sourceRequest == null) {
                return;
            }
            /* @var \Hermes\Api\Client $hermes */
            $hermes = $e->getTarget();
            $request = $hermes->getZendClient()->getRequest();

            if ($sourceRequest instanceof \Zend\Http\Request) {
                $sourceUri = $this->sourceRequest->getUriString();
            } elseif ($sourceRequest instanceof \Psr\Http\Message\RequestInterface) {
                $sourceUri = $sourceRequest->getUri()->__toString();
            } else {
                $sourceUri = 'console';
            }

            $data = [
                'status' => 0,
                'source' => [
                    'service' => $hermes->getServiceName(),
                    'server' => $_SERVER['SERVER_ADDR'],
                    'uri' => $sourceUri,
                ],
                'destination' => [
                    'service' => $hermes->getServiceName(),
                    'server' => $request->getUri()->getHost(),
                    'uri' => $request->getUriString(),
                ],
            ];

            if (!empty($apiKey)) {
                $data['api_key'] = $apiKey;
            }

            $exception = $e->getParams();
            $data['http_code'] = $exception->getCode();
            $data['error'] = $exception->getMessage();

            $data = $this->prepareData($data, $request);

            $logFile = !empty($this->hermesLog) ? $this->hermesLog : $kharonDir . '/failed-' . getmypid() . '-' . microtime(true) . '.kharon';
            file_put_contents($logFile, json_encode($data, null, 100) . PHP_EOL);
        }, 100);
    }
}
