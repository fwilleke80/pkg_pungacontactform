<?php
/**
 * @file
 * @brief Joomla service-provider registration for Punga Contact Form.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Punga\Component\Pungacontactform\Administrator\Extension\PungacontactformComponent;

return new class implements ServiceProviderInterface
{
    /** @inheritdoc */
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('\\Punga\\Component\\Pungacontactform'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Punga\\Component\\Pungacontactform'));
        $container->registerServiceProvider(new RouterFactory('\\Punga\\Component\\Pungacontactform'));
        $container->set(ComponentInterface::class, static function (Container $container): PungacontactformComponent
        {
            $component = new PungacontactformComponent($container->get(ComponentDispatcherFactoryInterface::class));
            $component->setMVCFactory($container->get(MVCFactoryInterface::class));
            $component->setRouterFactory($container->get(RouterFactoryInterface::class));
            return $component;
        });
    }
};
