<?php
/**
 * @file
 * @brief Joomla component extension that exposes the frontend router.
 */
namespace Punga\Component\Pungacontactform\Administrator\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\MVCComponent;

/**
 * @brief Supplies the Joomla 6 component router through the standard router factory.
 */
final class PungacontactformComponent extends MVCComponent implements RouterServiceInterface
{
    use RouterServiceTrait;
}