<?php
namespace Kharon\Hermes;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class CollectorMiddlewareFactory implements FactoryInterface
{

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $collector = $container->get(Collector::class);

        return new CollectorMiddleware($collector);
    }

    /**
     * {@inheritDoc}
     * @see \Zend\ServiceManager\FactoryInterface::createService()
     */
    public function createService(ServiceLocatorInterface $serviceLocator) {
        return $this->__invoke($serviceLocator, CollectorMiddleware::class);
    }

}
