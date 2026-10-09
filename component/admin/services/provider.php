<?php
/**
 * @file
 * @brief Joomla service-provider registration for Punga Contact Form.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface
{
    /** @inheritdoc */
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('\\Punga\\Component\\Pungacontactform'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Punga\\Component\\Pungacontactform'));
        $container->set(ComponentInterface::class, static function (Container $container): MVCComponent
        {
            $component = new MVCComponent($container->get(ComponentDispatcherFactoryInterface::class));
            $component->setMVCFactory($container->get(MVCFactoryInterface::class));
            return $component;
        });
    }
};
