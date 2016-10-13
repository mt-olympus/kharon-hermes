<?php
namespace Kharon\Hermes;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class CollectorFactory implements FactoryInterface
{

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('config');

        $collectorConfig = [
            'service_name' => isset($config['hermes']['service_name']) ? $config['hermes']['service_name'] : '',
            'api_key' => isset($config['kharon']['api_key']) ? $config['kharon']['api_key'] : null,
            'kharon_dir' => isset($config['kharon']['agent_dir']) ? $config['kharon']['agent_dir'] : 'data/kharon',
        ];
        $collectorConfig['kharon_dir'] .= '/hermes';

        return new Collector($collectorConfig);
    }

    /**
     * {@inheritDoc}
     * @see \Zend\ServiceManager\FactoryInterface::createService()
     */
    public function createService(ServiceLocatorInterface $serviceLocator) {
        return $this->__invoke($serviceLocator, Collector::class);
    }

}
