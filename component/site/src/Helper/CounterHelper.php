<?php
/**
 * @file
 * @brief Atomic, database-independent-invocation daily submission accounting.
 */
namespace Punga\Component\Pungacontactform\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

/**
 * @brief Maintains lifetime daily counters, independently of message retention.
 *
 * Joomla supports MySQL/MariaDB and PostgreSQL. Each database has its own
 * atomic upsert syntax. Counter updates must never use SELECT followed by
 * INSERT/UPDATE, which is unsafe when simultaneous submissions arrive.
 */
final class CounterHelper
{
    /**
     * @brief Atomically increment a daily result exactly once per call.
     * @param DatabaseInterface $db Joomla's configured database connection.
     * @param string $date UTC calendar date (YYYY-MM-DD).
     * @param string $status One of sent, rejected or failed.
     * @return void
     */
    public static function increment(DatabaseInterface $db, string $date, string $status): void
    {
        self::upsert($db, $date, $status, 1, false);
    }

    /**
     * @brief Ensure a daily total is at least the count of retained messages.
     * @param DatabaseInterface $db Joomla's configured database connection.
     * @param string $date UTC calendar date (YYYY-MM-DD).
     * @param string $status One of sent, rejected or failed.
     * @param int $minimum Number of persisted message records for that day.
     * @return void
     */
    public static function ensureMinimum(DatabaseInterface $db, string $date, string $status, int $minimum): void
    {
        if ($minimum <= 0)
        {
            return;
        }

        self::upsert($db, $date, $status, $minimum, true);
    }

    /**
     * @brief Repair undercounted days using records still within retention.
     * @param DatabaseInterface $db Joomla's configured database connection.
     * @return int Number of daily totals requiring reconciliation.
     *
     * Deleted/expired message records cannot be recovered. Never decrease
     * historical totals: a counter may predate the retained message history.
     */
    public static function reconcileRetainedHistory(DatabaseInterface $db): int
    {
        $dateExpression = 'DATE(' . $db->quoteName('created_at') . ')';
        $query = $db->getQuery(true)
            ->select([
                $dateExpression . ' AS ' . $db->quoteName('day'),
                $db->quoteName('status'),
                'COUNT(*) AS ' . $db->quoteName('total'),
            ])
            ->from($db->quoteName('#__pungacontactform_messages'))
            ->group($dateExpression)
            ->group($db->quoteName('status'));
        $db->setQuery($query);
        $history = $db->loadObjectList() ?: [];

        $query = $db->getQuery(true)
            ->select($db->quoteName(['day', 'status', 'total']))
            ->from($db->quoteName('#__pungacontactform_counts'));
        $db->setQuery($query);
        $existing = [];
        foreach ($db->loadObjectList() ?: [] as $row)
        {
            $existing[(string) $row->day . ':' . (string) $row->status] = (int) $row->total;
        }

        $repairs = 0;
        foreach ($history as $row)
        {
            $date = (string) $row->day;
            $status = (string) $row->status;
            $minimum = (int) $row->total;
            // Skip invalid/unknown data rather than embedding it in SQL.
            if (!self::valid($date, $status))
            {
                continue;
            }
            if ($minimum > ($existing[$date . ':' . $status] ?? 0))
            {
                self::ensureMinimum($db, $date, $status, $minimum);
                $repairs++;
            }
        }
        return $repairs;
    }

    /**
     * @brief Execute one backend-native atomic INSERT/UPDATE statement.
     * @param DatabaseInterface $db Database connection.
     * @param string $date UTC day.
     * @param string $status Event status.
     * @param int $value Increment or minimum total.
     * @param bool $minimumMode True: MAX(current, value); false: current + value.
     * @return void
     */
    private static function upsert(DatabaseInterface $db, string $date, string $status, int $value, bool $minimumMode): void
    {
        if (!self::valid($date, $status) || $value < 1)
        {
            throw new \InvalidArgumentException('Invalid contact form counter values');
        }

        $table = $db->quoteName('#__pungacontactform_counts');
        $day = $db->quoteName('day');
        $statusColumn = $db->quoteName('status');
        $total = $db->quoteName('total');
        $sql = 'INSERT INTO ' . $table . ' (' . $day . ', ' . $statusColumn . ', ' . $total . ') VALUES ('
            . $db->quote($date) . ', ' . $db->quote($status) . ', ' . $value . ')';

        $driver = strtolower($db->getServerType());
        if ($driver === 'mysql' || $driver === 'mariadb')
        {
            $expression = $minimumMode
                ? 'GREATEST(' . $total . ', ' . $value . ')'
                : $total . ' + ' . $value;
            $sql .= ' ON DUPLICATE KEY UPDATE ' . $total . ' = ' . $expression;
        }
        elseif ($driver === 'postgresql' || $driver === 'pgsql')
        {
            $expression = $minimumMode
                ? 'GREATEST(' . $total . ', ' . $value . ')'
                : $total . ' + ' . $value;
            $sql .= ' ON CONFLICT (' . $day . ', ' . $statusColumn . ') DO UPDATE SET ' . $total . ' = ' . $expression;
        }
        else
        {
            throw new \RuntimeException('Unsupported Joomla database family for contact statistics');
        }
        $db->setQuery($sql)->execute();
    }

    /**
     * @brief Reject SQL input not originating from recognized outcomes/dates.
     * @param string $date UTC day in ISO format.
     * @param string $status Counter category.
     * @return bool
     */
    private static function valid(string $date, string $status): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date))
        {
            return false;
        }
        $parts = explode('-', $date);
        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])
            && in_array($status, ['sent', 'rejected', 'failed'], true);
    }
}
