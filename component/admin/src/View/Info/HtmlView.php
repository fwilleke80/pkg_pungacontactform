<?php
/**
 * @file
 * @brief Administrator submission overview, recent messages and installed version.
 */
namespace Punga\Component\Pungacontactform\Administrator\View\Info;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

/** @brief Administrator dashboard with lifetime counters and recent message history. */
final class HtmlView extends BaseHtmlView
{
    public array $counts = ['sent'=>0, 'rejected'=>0, 'failed'=>0];
    public int $archived = 0;
    public array $recentMessages = [];
    public string $version = '';

    /** @inheritdoc */
    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        if (!$app->getIdentity()->authorise('core.manage', 'com_pungacontactform'))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([$db->quoteName('status'), 'SUM(' . $db->quoteName('total') . ') AS ' . $db->quoteName('total')])
            ->from($db->quoteName('#__pungacontactform_counts'))
            ->group($db->quoteName('status'));
        $db->setQuery($query);
        foreach ($db->loadObjectList() ?: [] as $item)
        {
            if (array_key_exists($item->status, $this->counts))
            {
                $this->counts[$item->status] = (int) $item->total;
            }
        }

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__pungacontactform_messages'))
            ->where($db->quoteName('archived') . ' = 1');
        $db->setQuery($query);
        $this->archived = (int) $db->loadResult();

        // Always show a bounded, newest-first history. The list view provides
        // the full native Joomla search/filter/sort/archiving facilities.
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'created_at', 'sender_name', 'subject', 'status', 'reason', 'spam_score', 'spam_threshold', 'archived']))
            ->from($db->quoteName('#__pungacontactform_messages'))
            ->order($db->quoteName('created_at') . ' DESC')
            ->order($db->quoteName('id') . ' DESC');
        $db->setQuery($query, 0, 10);
        $this->recentMessages = $db->loadObjectList() ?: [];

        // Resolve the installed version rather than assuming the manifest's
        // source-tree version matches an already-installed extension.
        $query = $db->getQuery(true)
            ->select($db->quoteName('manifest_cache'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_pungacontactform'));
        $db->setQuery($query);
        $manifest = json_decode((string) $db->loadResult(), true);
        if (is_array($manifest) && isset($manifest['version']) && is_scalar($manifest['version']))
        {
            $this->version = (string) $manifest['version'];
        }

        ToolbarHelper::title(Text::_('COM_PUNGACONTACTFORM'), 'envelope');
        if ($app->getIdentity()->authorise('core.admin', 'com_pungacontactform'))
        {
            ToolbarHelper::preferences('com_pungacontactform');
        }
        parent::display($tpl);
    }
}
