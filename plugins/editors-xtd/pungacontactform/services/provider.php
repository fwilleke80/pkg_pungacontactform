<?php
/** @file @brief Joomla 6 editor-button dependency registration. */
\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\CMS\Factory;
use Punga\Plugin\EditorsXtd\Pungacontactform\Extension\Pungacontactform;

return new class implements ServiceProviderInterface
{
    /** @inheritdoc */
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (Container $container): Pungacontactform
        {
            $plugin = new Pungacontactform(
                (array) PluginHelper::getPlugin('editors-xtd', 'pungacontactform')
            );
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        });
    }
};
