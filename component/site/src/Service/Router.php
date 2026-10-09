<?php
/**
 * @file
 * @brief Joomla frontend SEF router for the contact form menu item.
 */
namespace Punga\Component\Pungacontactform\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;

/**
 * @brief Maps a menu item for the only frontend view to its bare alias.
 *
 * The Joomla standard rules remove `view=contact` when the requested view
 * matches the selected menu item and resolve the same view on incoming URLs.
 * No custom URL segments, IDs or database lookups are required.
 */
final class Router extends RouterView
{
    /**
     * @brief Registers the contact view and Joomla's standard SEF rules.
     * @param SiteApplication $app Frontend application.
     * @param AbstractMenu $menu Site menu containing the contact menu item.
     */
    public function __construct(SiteApplication $app, AbstractMenu $menu)
    {
        $this->registerView(new RouterViewConfiguration('contact'));

        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }
}