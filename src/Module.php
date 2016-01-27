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
            $data['request_time'] = $requestTime;
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
        $kharonDir = isset($config['kharon']['agent_dir']) ? $config['kharon']['agent_dir'] : 'data/kharon';
        $kharonDir .= '/requests';
        $logFile = $kharonDir . '/request-' . getmypid() . '-' . microtime(true) . '.kharon';

        $e->getApplication()->getEventManager()->attach(\Zend\Mvc\MvcEvent::EVENT_DISPATCH,
            function (MvcEvent $e) use (
                $serviceLocator,
                $serviceName,
                $logFile) {
            if (!$e->getRequest() instanceof Request) {
                return;
            }
            $request = $e->getRequest();

            $data = [
                'status' => 'success',
                'destination' => [
                    'service' => $serviceName,
                    'server' => $request->getUri()->getHost(),
                    'uri' => $request->getUriString(),
                ],
                'source' => [
                    'server' => $_SERVER['REMOTE_ADDR'],
                    'service' => $request->getHeader('X-Request-Name') ? $request->getHeader('X-Request-Name')->getFieldValue() : '',
                    'uri' => '',
                ],
            ];

            $data = $this->prepareData($data, $request);

            file_put_contents($logFile, json_encode($data, null, 100));
        }, 100);

        $hermes = $serviceLocator->get('hermes');
        $em = $hermes->getEventManager();
        $em->attach('request.post', function (Event $e) use (
                $serviceLocator,
                $serviceName,
                $logFile) {

            /* @var \Hermes\Api\Client $hermes */
            $hermes = $e->getTarget();
            $request = $hermes->getZendClient()->getRequest();

            $data = [
                'status' => 'success',
                'source' => [
                    'service' => $serviceName,
                    'server' => $_SERVER['SERVER_ADDR'],
                    'uri' => $_SERVER['REQUEST_URI'],
                ],
                'destination' => [
                    'service' => $hermes->getServiceName(),
                    'server' => $request->getUri()->getHost(),
                    'uri' => $request->getUriString(),
                ],
                'http_code' => $hermes->getZendClient()->getResponse()->getStatusCode(),
            ];

            $data = $this->prepareData($data, $request);

            file_put_contents($logFile, json_encode($data, null, 100));
        }, 100);

        $em->attach('request.fail', function (Event $e) use (
                $serviceLocator,
                $serviceName,
                $logFile) {
            /* @var \Hermes\Api\Client $hermes */
            $hermes = $e->getTarget();
            $request = $hermes->getZendClient()->getRequest();

            $config = $serviceLocator->get('Config');
            $serviceName = isset($config['hermes']['service_name']) ? $config['hermes']['service_name'] : '';

            $data = [
                'status' => 'failed',
                'source' => [
                    'service' => $serviceName,
                    'server' => $_SERVER['SERVER_ADDR'],
                    'uri' => $_SERVER['REQUEST_URI'],
                ],
                'destination' => [
                    'service' => $hermes->getServiceName(),
                    'server' => $request->getUri()->getHost(),
                    'uri' => $request->getUriString(),
                ],
            ];

            $exception = $e->getParams();
            $data['http_code'] = $exception->getCode();
            $data['error'] = $exception->getMessage();

            $data = $this->prepareData($data, $request);

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
