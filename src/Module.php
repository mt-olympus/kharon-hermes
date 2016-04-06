<?php

namespace Kharon\Hermes;

use Hermes\Api\Client;
use Zend\EventManager\Event;
use Zend\Http\PhpEnvironment\Request;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\Mvc\MvcEvent;

/**
 * @codeCoverageIgnore
 */
class Module implements AutoloaderProviderInterface
{
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

    public function onBootstrap(MvcEvent $e)
    {
        $serviceLocator = $e->getApplication()->getServiceManager();
        $config = $serviceLocator->get('Config');
        $serviceName = isset($config['hermes']['service_name']) ? $config['hermes']['service_name'] : '';
        $apiKey = isset($config['kharon']['api_key']) ? $config['kharon']['api_key'] : null;
        $hermesKey = isset($config['kharon']['hermes_key']) ? $config['kharon']['hermes_key'] : 'hermes';
        $kharonDir = isset($config['kharon']['agent_dir']) ? $config['kharon']['agent_dir'] : 'data/kharon';
        $kharonDir .= '/hermes';

        $hermes = $serviceLocator->get($hermesKey);
        $em = $hermes->getEventManager();
        $em->attach('request.pre', function (Event $e) use (
            $serviceLocator,
            $serviceName) {

                $request = $serviceLocator->get('Request');

                /* @var \Hermes\Api\Client $hermes */
                $hermes = $e->getTarget();
                $hermes->importRequestId($request);
                $hermes->incrementRequestDepth($request);
        }, 100);

        $em->attach('request.post', function (Event $e) use (
                $serviceLocator,
                $serviceName,
                $kharonDir,
                $apiKey) {

            /* @var \Hermes\Api\Client $hermes */
            $hermes = $e->getTarget();
            $request = $hermes->getZendClient()->getRequest();

            $sourceRequest = $serviceLocator->get('Request');
            if ($sourceRequest instanceof \Zend\Http\Request) {
                $sourceUri = $sourceRequest->getUriString();
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

            $logFile = $kharonDir . '/success-' . getmypid() . '-' . microtime(true) . '.kharon';
            file_put_contents($logFile, json_encode($data, null, 100));
        }, 100);

        $em->attach('request.fail', function (Event $e) use (
                $serviceLocator,
                $serviceName,
                $kharonDir,
                $apiKey) {
            /* @var \Hermes\Api\Client $hermes */
            $hermes = $e->getTarget();
            $request = $hermes->getZendClient()->getRequest();

            $sourceRequest = $serviceLocator->get('Request');
            if ($sourceRequest instanceof \Zend\Http\Request) {
                $sourceUri = $sourceRequest->getUriString();
            } else {
                $sourceUri = 'console';
            }

            $data = [
                'status' => 0,
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
            ];

            if (!empty($apiKey)) {
                $data['api_key'] = $apiKey;
            }

            $exception = $e->getParams();
            $data['http_code'] = $exception->getCode();
            $data['error'] = $exception->getMessage();

            $data = $this->prepareData($data, $request);

            $logFile = $kharonDir . '/failed-' . getmypid() . '-' . microtime(true) . '.kharon';
            file_put_contents($logFile, json_encode($data, null, 100));
        }, 100);
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array('namespaces' => array(
                __NAMESPACE__ => __DIR__ . '/',
            )),
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }
}
