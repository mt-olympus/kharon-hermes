<?php

namespace Kharon\Hermes;

use Zend\Http\PhpEnvironment\Request;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\Mvc\MvcEvent;

/**
 * @codeCoverageIgnore
 */
class Module implements AutoloaderProviderInterface
{
    public function onBootstrap(MvcEvent $e)
    {
        $serviceLocator = $e->getApplication()->getServiceManager();

        $config = $serviceLocator->get('Config');
        $enabled = isset($config['kharon']['enabled']) ? (bool) $config['kharon']['enabled'] : false;

        if ($enabled !== true) {
            return;
        }

        $collector = (new CollectorFactory())->createService($serviceLocator);
        $collector->setSourceRequest($serviceLocator->get('Request'));

        $hermesKey = isset($config['kharon']['hermes_key']) ? $config['kharon']['hermes_key'] : 'hermes';

        $hermes = $serviceLocator->get($hermesKey);
        $collector->attach($hermes);
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
