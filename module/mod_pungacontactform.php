<?php
/** @file @brief Joomla site module bootstrap. */
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Punga\Component\Pungacontactform\Site\Helper\FormHelper;

$app = Factory::getApplication();
$app->bootComponent('com_pungacontactform');
$app->getLanguage()->load('com_pungacontactform', JPATH_SITE);
require ModuleHelper::getLayoutPath('mod_pungacontactform', $params->get('layout', 'default'));
