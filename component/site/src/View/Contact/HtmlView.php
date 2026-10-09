<?php
/** @file @brief The standalone contact page view. */
namespace Punga\Component\Pungacontactform\Site\View\Contact;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Punga\Component\Pungacontactform\Site\Helper\FormHelper;

/** @brief Renders a menu-linked contact form. */
final class HtmlView extends BaseHtmlView
{
    /** @var string Rendered form markup. */
    public string $formHtml = '';

    /** @inheritdoc */
    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $active = $app->getMenu()->getActive();
        $overrides = $active && ($active->component === 'com_pungacontactform')
            ? $active->getParams()
            : null;
        $this->formHtml = FormHelper::render($overrides, 'menu', $active ? (int) $active->id : 0);
        parent::display($tpl);
    }
}
