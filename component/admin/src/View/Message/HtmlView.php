<?php
/** @file @brief Read-only, escaped single-message detail view. */
namespace Punga\Component\Pungacontactform\Administrator\View\Message;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

/** @brief Exposes stored submissions only to administrators with manage ACL. */
final class HtmlView extends BaseHtmlView
{
    public object $item;

    /** @inheritdoc */
    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        if (!$app->getIdentity()->authorise('core.manage', 'com_pungacontactform'))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $id = $app->getInput()->getInt('id');
        if ($id <= 0)
        {
            throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)->select('*')->from($db->quoteName('#__pungacontactform_messages'))
            ->where($db->quoteName('id') . ' = ' . $id);
        $db->setQuery($query);
        $this->item = $db->loadObject() ?: throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        ToolbarHelper::title(Text::_('COM_PUNGACONTACTFORM_MESSAGE') . ' #' . $id, 'envelope');
        parent::display($tpl);
    }
}
