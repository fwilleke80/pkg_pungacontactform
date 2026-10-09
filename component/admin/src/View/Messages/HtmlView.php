<?php
/** @file @brief Administrator sortable message list. */
namespace Punga\Component\Pungacontactform\Administrator\View\Messages;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/** @brief Search and moderation entry point, restricted to component managers. */
final class HtmlView extends BaseHtmlView
{
    public array $items = [];
    public $pagination;
    public $filterForm;
    public $activeFilters;
    public $state;

    /** @inheritdoc */
    public function display($tpl = null): void
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.manage', 'com_pungacontactform'))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $model = $this->getModel();
        $this->items = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->filterForm = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();
        $this->state = $model->getState();
        ToolbarHelper::title(Text::_('COM_PUNGACONTACTFORM_MESSAGES'), 'envelope');
        if ($user->authorise('core.edit.state', 'com_pungacontactform'))
        {
            ToolbarHelper::custom('messages.archive', 'archive', '', 'JTOOLBAR_ARCHIVE', true);
            ToolbarHelper::custom('messages.unarchive', 'unarchive', '', 'JTOOLBAR_UNARCHIVE', true);
        }
        if ($user->authorise('core.delete', 'com_pungacontactform'))
        {
            ToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE', 'messages.delete');
        }
        if ($user->authorise('core.admin', 'com_pungacontactform'))
        {
            ToolbarHelper::preferences('com_pungacontactform');
        }
        parent::display($tpl);
    }
}
