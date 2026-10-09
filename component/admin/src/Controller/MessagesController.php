<?php
/** @file @brief CSRF-checked bulk archive and delete operations. */
namespace Punga\Component\Pungacontactform\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;

/** @brief Mutates only selected, integer-validated messages. */
final class MessagesController extends BaseController
{
    /** @brief Archive the selected message records. */
    public function archive(): void
    {
        $this->change('archive');
    }

    /** @brief Unarchive the selected message records. */
    public function unarchive(): void
    {
        $this->change('unarchive');
    }

    /** @brief Permanently delete selected message records. */
    public function delete(): void
    {
        $this->change('delete');
    }

    /** @brief Perform authorized and CSRF-checked bulk action. */
    private function change(string $action): void
    {
        $this->checkToken('post');
        $app = Factory::getApplication();
        $permission = $action === 'delete' ? 'core.delete' : 'core.edit.state';
        if (!$app->getIdentity()->authorise('core.manage', 'com_pungacontactform')
            || !$app->getIdentity()->authorise($permission, 'com_pungacontactform'))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $ids = $this->input->post->get('cid', [], 'array');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids), static fn (int $id): bool => $id > 0)));
        if ($ids)
        {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true);
            if ($action === 'delete')
            {
                $query->delete($db->quoteName('#__pungacontactform_messages'));
            }
            else
            {
                $query->update($db->quoteName('#__pungacontactform_messages'))
                    ->set($db->quoteName('archived') . ' = ' . ($action === 'archive' ? '1' : '0'));
            }
            $query->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');
            $db->setQuery($query)->execute();
        }
        $this->setRedirect(Route::_('index.php?option=com_pungacontactform&view=messages', false), Text::_('COM_PUNGACONTACTFORM_UPDATED'));
    }
}
