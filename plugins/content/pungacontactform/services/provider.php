<?php
/** @file @brief Joomla 6 content-plugin dependency registration. */
\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\CMS\Factory;
use Punga\Plugin\Content\Pungacontactform\Extension\Pungacontactform;

return new class implements ServiceProviderInterface
{
    /** @inheritdoc */
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (Container $container): Pungacontactform
        {
            $plugin = new Pungacontactform(
                (array) PluginHelper::getPlugin('content', 'pungacontactform')
            );
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        });
    }
};
