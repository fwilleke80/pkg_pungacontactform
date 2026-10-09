<?php
/** @file @brief Component frontend display controller. */
namespace Punga\Component\Pungacontactform\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

/** @brief Displays the contact form page. */
final class DisplayController extends BaseController
{
    /** @var string Default view. */
    protected $default_view = 'contact';
}
