<?php
/** @file @brief Privacy-preserving, persistent IP-based submission rate limiting. */
namespace Punga\Component\Pungacontactform\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * @brief Uses a one-way HMAC of the remote IP and hourly database buckets.
 *
 * The hash salt comes from Joomla's secret. No plaintext IP is persisted.
 */
final class ThrottleHelper
{
    /**
     * @brief Counts an attempt atomically and enforces the site-wide IP quota.
     * @param int $maxPerHour Allowed attempted submissions per IP and hour.
     * @return bool True if another attempted submission is permitted.
     * @throws \Throwable Database failure (the caller must fail closed).
     */
    public static function allow(int $maxPerHour): bool
    {
        $app = Factory::getApplication();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!filter_var($ip, FILTER_VALIDATE_IP))
        {
            $ip = 'unknown';
        }
        $hash = hash_hmac('sha256', $ip, (string) $app->get('secret'));
        $bucket = (int) floor(time() / 3600);
        $table = $db->quoteName('#__pungacontactform_limits');

        // A primary key makes concurrent attempts on this IP/hour share a row.
        // An unsuccessful INSERT means the row already exists (or the database
        // failed). Attempt UPDATE; any remaining failure is propagated.
        $insert = $db->getQuery(true)
            ->insert($table)
            ->columns([$db->quoteName('ip_hash'), $db->quoteName('hour_bucket'), $db->quoteName('attempts')])
            ->values($db->quote($hash) . ', ' . $bucket . ', 1');
        try
        {
            $db->setQuery($insert)->execute();
        }
        catch (\Throwable)
        {
            $update = $db->getQuery(true)
                ->update($table)
                ->set($db->quoteName('attempts') . ' = ' . $db->quoteName('attempts') . ' + 1')
                ->where($db->quoteName('ip_hash') . ' = ' . $db->quote($hash))
                ->where($db->quoteName('hour_bucket') . ' = ' . $bucket);
            $db->setQuery($update)->execute();
            if ($db->getAffectedRows() !== 1)
            {
                throw new \RuntimeException('Contact form throttle table unavailable');
            }
        }

        $select = $db->getQuery(true)
            ->select($db->quoteName('attempts'))
            ->from($table)
            ->where($db->quoteName('ip_hash') . ' = ' . $db->quote($hash))
            ->where($db->quoteName('hour_bucket') . ' = ' . $bucket);
        $attempts = (int) $db->setQuery($select)->loadResult();

        if (random_int(1, 64) === 1)
        {
            $delete = $db->getQuery(true)->delete($table)
                ->where($db->quoteName('hour_bucket') . ' < ' . ($bucket - 48));
            $db->setQuery($delete)->execute();
        }
        return $attempts <= max(1, $maxPerHour);
    }
}
