<?php
/**
 * @file
 * @brief The standalone contact page view and Joomla page-title integration.
 */
namespace Punga\Component\Pungacontactform\Site\View\Contact;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Punga\Component\Pungacontactform\Site\Helper\FormHelper;

/** @brief Renders a menu-linked contact form with Joomla's native page display options. */
final class HtmlView extends BaseHtmlView
{
    /** @var string Rendered form markup. */
    public string $formHtml = '';

    /** @var bool Whether the menu item requests a visible page heading. */
    public bool $showPageHeading = false;

    /** @var string Plain-text page heading, escaped by the layout. */
    public string $pageHeading = '';

    /** @var bool Align the page heading with a centered form column. */
    public bool $centerPageHeading = false;

    /**
     * @brief Prepares the standard Joomla page title, heading, and contact form.
     * @param string|null $tpl Optional layout suffix.
     * @return void
     */
    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $active = $app->getMenu()->getActive();
        $contactMenu = $active && $active->component === 'com_pungacontactform' ? $active : null;
        $overrides = $contactMenu ? $contactMenu->getParams() : null;

        // SiteApplication::getParams merges the standard menu Page Display
        // settings, including the Show Page Heading and Browser Page Title.
        $params = $app->getParams('com_pungacontactform');
        $fallbackTitle = $contactMenu
            ? (string) $contactMenu->title
            : Text::_('COM_PUNGACONTACTFORM_MENU_TITLE');
        $title = trim((string) $params->get('page_title', ''));
        if ($title === '')
        {
            $title = $fallbackTitle;
        }

        $this->showPageHeading = (bool) $params->get('show_page_heading', false);
        $this->pageHeading = trim((string) $params->get('page_heading', ''));
        if ($this->pageHeading === '')
        {
            $this->pageHeading = $title;
        }

        // This inherited Joomla method applies the Global Configuration
        // "Site Name in Page Titles" position and localized separator.
        $this->setDocumentTitle($title);

        $this->centerPageHeading = FormHelper::settings($overrides)['form_alignment'] === 'centered';
        $this->formHtml = FormHelper::render($overrides, 'menu', $contactMenu ? (int) $contactMenu->id : 0);
        parent::display($tpl);
    }
}
