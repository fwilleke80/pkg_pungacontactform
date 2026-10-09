<?php
/** @file @brief Searchable and paginated administrator message history. */
namespace Punga\Component\Pungacontactform\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;

/** @brief Uses Joomla's built-in pagination, filters and ordering. */
final class MessagesModel extends ListModel
{
    /** @var array<string,string> Supported order fields. */
    protected $filter_fields = ['id', 'created_at', 'status', 'subject', 'sender_name', 'spam_score', 'archived'];

    /** @inheritdoc */
    protected function populateState($ordering = 'created_at', $direction = 'DESC'): void
    {
        $app = \Joomla\CMS\Factory::getApplication();
        $this->setState('filter.search', trim((string) $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string')));
        $this->setState('filter.status', (string) $app->getUserStateFromRequest($this->context . '.filter.status', 'filter_status', '', 'cmd'));
        $this->setState('filter.archived', (string) $app->getUserStateFromRequest($this->context . '.filter.archived', 'filter_archived', '', 'cmd'));
        parent::populateState($ordering, $direction);
    }

    /** @inheritdoc */
    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)->select('*')->from($db->quoteName('#__pungacontactform_messages'));
        $search = (string) $this->getState('filter.search');
        if ($search !== '')
        {
            if (preg_match('/^id:(\d+)$/i', $search, $matches))
            {
                $query->where($db->quoteName('id') . ' = ' . (int) $matches[1]);
            }
            else
            {
                $like = $db->quote('%' . $db->escape($search, true) . '%', false);
                $query->where('(' . implode(' OR ', array_map(static fn (string $column): string => $db->quoteName($column) . ' LIKE ' . $like, ['sender_name', 'sender_email', 'subject'])) . ')');
            }
        }
        $status = (string) $this->getState('filter.status');
        if (in_array($status, ['sent', 'rejected', 'failed'], true))
        {
            $query->where($db->quoteName('status') . ' = ' . $db->quote($status));
        }
        $archived = (string) $this->getState('filter.archived');
        if ($archived === '0' || $archived === '1')
        {
            $query->where($db->quoteName('archived') . ' = ' . (int) $archived);
        }
        $order = (string) $this->getState('list.ordering', 'created_at');
        $direction = strtoupper((string) $this->getState('list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        if (!in_array($order, $this->filter_fields, true))
        {
            $order = 'created_at';
        }
        return $query->order($db->quoteName($order) . ' ' . $direction)->order($db->quoteName('id') . ' DESC');
    }
}
