<?php
/** @file @brief Private submission history, counters and bounded retention. */
namespace Punga\Component\Pungacontactform\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;

/** @brief Stores administrative diagnostics; never writes recipients to frontend output. */
final class MessageStoreHelper
{
    /**
     * @brief Persist one classified outcome and increment an independent counter.
     * @param string $status Sent, rejected, or failed.
     * @param string $reason Stable reason code.
     * @param string $source Session-bound source identifier.
     * @param bool $isGuest Authenticated Joomla user state.
     * @param array<string,mixed> $data Validated message fields; retained conditionally.
     * @param array<string,mixed> $analysis Spam details, if applicable.
     * @return void
     */
    public static function record(string $status, string $reason, string $source, bool $isGuest, array $data = [], array $analysis = []): void
    {
        if (!in_array($status, ['sent', 'rejected', 'failed'], true))
        {
            return;
        }
        try
        {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $params = ComponentHelper::getParams('com_pungacontactform');
            // Always retain minimal submission metadata for the dashboard and
            // the message list. The switches control BODY retention only.
            // A disabled switch must never make a message disappear from history.
            $storeBody = ($status === 'sent' && (bool) $params->get('store_sent', 0))
                || ($status === 'rejected' && $reason === 'content_filter' && (bool) $params->get('store_rejected', 1))
                || ($status === 'failed' && (bool) $params->get('store_failed', 1));
            [$kind, $id] = array_pad(explode(':', $source, 2), 2, '');
            $sourceKind = in_array($kind, ['menu', 'module'], true) ? $kind : 'unknown';
            $sourceId = ctype_digit($id) ? (int) $id : 0;
            // For invalid/CAPTCHA/honeypot submissions, callers pass no sender
            // data; no unvalidated free text is retained in the audit history.
            $row = (object) [
                'created_at' => gmdate('Y-m-d H:i:s'),
                'status' => $status,
                'reason' => mb_substr($reason, 0, 32),
                'source_kind' => $sourceKind,
                'source_id' => $sourceId,
                'is_guest' => $isGuest ? 1 : 0,
                'sender_name' => (string) ($data['name'] ?? ''),
                'sender_email' => (string) ($data['email'] ?? ''),
                'subject' => (string) ($data['subject'] ?? ''),
                'body' => $storeBody ? (string) ($data['body'] ?? '') : '',
                'spam_score' => (int) ($analysis['score'] ?? 0),
                'spam_threshold' => (int) ($analysis['threshold'] ?? 0),
                'spam_details' => json_encode($analysis['matches'] ?? [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]',
                'archived' => 0,
            ];
            // Store the history row and update its counter in the same
            // transaction, so Dashboard reconciliation never observes a
            // committed history record before its corresponding count.
            $transactionOpen = false;
            try
            {
                $db->transactionStart();
                $transactionOpen = true;
                $db->insertObject('#__pungacontactform_messages', $row);
                CounterHelper::increment($db, gmdate('Y-m-d'), $status);
                $db->transactionCommit();
                $transactionOpen = false;
            }
            catch (\Throwable $exception)
            {
                $safeToRetryHistory = !$transactionOpen;
                if ($transactionOpen)
                {
                    try
                    {
                        $db->transactionRollback();
                        $safeToRetryHistory = true;
                    }
                    catch (\Throwable)
                    {
                        // Do not retry when commit/rollback state is unknown.
                        $safeToRetryHistory = false;
                    }
                }

                // If counter persistence failed, retain the history record
                // alone where safe. Admin reconciliation will repair the
                // missing counter when the database problem is resolved.
                if ($safeToRetryHistory)
                {
                    try
                    {
                        $db->insertObject('#__pungacontactform_messages', $row);
                    }
                    catch (\Throwable)
                    {
                        // The warning below covers both failed writes.
                    }
                }
                Log::add('Punga Contact Form: atomic history/counter transaction failed ('
                    . get_class($exception) . ', code ' . self::safeErrorCode($exception)
                    . ').', Log::WARNING, 'com_pungacontactform');
            }
            // Opportunistic daily cleanup; no personal data kept past retention
            // merely because the site lacks a configured scheduled task.
            $session = Factory::getApplication()->getSession();
            $last = (int) $session->get('pungacontactform.cleanup', 0);
            if (time() - $last > 86400)
            {
                $session->set('pungacontactform.cleanup', time());
                self::purgeExpired();
            }
        }
        catch (\Throwable $exception)
        {
            // Do not expose message text, sender or recipient in diagnostics.
            // A successful mail transmission must not be reclassified as failed.
            Log::add('Punga Contact Form: submission history/counter persistence failed (' . get_class($exception) . ', code ' . (int) $exception->getCode() . ').', Log::WARNING, 'com_pungacontactform');
        }
    }

    /**
     * @brief Remove potentially private characters from a database error code.
     * @param \Throwable $error Caught persistence failure.
     * @return string Safe error identifier, never message or SQL query text.
     */
    private static function safeErrorCode(\Throwable $error): string
    {
        return substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $error->getCode()) ?: 'unknown', 0, 20);
    }

    /** @brief Purge message history and retained bodies past the configured retention window. */
    public static function purgeExpired(): void
    {
        $days = max(1, min(365, (int) ComponentHelper::getParams('com_pungacontactform')->get('retention_days', 30)));
        $cutoff = gmdate('Y-m-d H:i:s', time() - 86400 * $days);
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)->delete($db->quoteName('#__pungacontactform_messages'))
            ->where($db->quoteName('created_at') . ' < ' . $db->quote($cutoff));
        $db->setQuery($query)->execute();
    }
}
