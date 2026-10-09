<?php
/**
 * @file
 * @brief Privacy-conscious Joomla domain and Punga Analytics event dispatch.
 */
namespace Punga\Component\Pungacontactform\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Event\Event;

/**
 * @brief Emits contact-form outcomes without leaking correspondence or recipients.
 *
 * The domain event is for third-party listeners. The generic analytics bridge
 * matches the Punga Analytics custom-event integration used by other extensions.
 * Listener failures must never change a mail delivery result or user response.
 */
final class AnalyticsHelper
{
	/**
	 * @brief Record a successfully delivered or rejected form submission.
	 * @param string $outcome Either 'sent' or 'rejected'.
	 * @param string $sourceKey Session-bound source, e.g. 'menu:15' or 'module:8'.
	 * @param bool $isGuest Actual Joomla guest status.
	 * @param string $reason Stable rejection reason or empty for successful delivery.
	 * @param int $score Spam score for content-filter rejections only.
	 * @return void
	 */
	public static function record(
		string $outcome,
		string $sourceKey,
		bool $isGuest,
		string $reason = '',
		int $score = 0
	): void
	{
		if (!in_array($outcome, ['sent', 'rejected'], true))
		{
			return;
		}

		[$kind, $identifier] = array_pad(explode(':', $sourceKey, 2), 2, '');
		$kind = in_array($kind, ['menu', 'module'], true) ? $kind : 'unknown';
		$sourceId = ctype_digit($identifier) ? (int) $identifier : 0;
		$reason = $outcome === 'rejected' && in_array($reason, [
			'content_filter', 'honeypot', 'too_fast', 'validation', 'captcha',
		], true) ? $reason : '';

		$data = [
			'source' => $kind,
			'source_id' => $sourceId,
			'visitor_type' => $isGuest ? 'guest' : 'registered',
			'timestamp_utc' => gmdate('Y-m-d\TH:i:s\Z'),
		];

		if ($outcome === 'rejected')
		{
			$data['reason'] = $reason;
			if ($reason === 'content_filter')
			{
				$data['spam_score'] = max(0, $score);
			}
		}

		$app = Factory::getApplication();
		$domainName = $outcome === 'sent' ? 'onPungaContactFormSent' : 'onPungaContactFormRejected';
		$eventType = 'contact.' . $outcome;

		try
		{
			$app->getDispatcher()->dispatch($domainName, new Event($domainName, ['data' => $data]));
		}
		catch (\Throwable)
		{
			// A broken optional listener must not prevent analytics or change delivery.
			self::logFailure('domain');
		}

		try
		{
			$app->getDispatcher()->dispatch('onPungaAnalyticsRecord', new Event('onPungaAnalyticsRecord', [
				'event_type' => $eventType,
				'component' => 'com_pungacontactform',
				'view' => 'contact',
				'item_id' => $sourceId,
				'data' => $data,
			]));
		}
		catch (\Throwable)
		{
			self::logFailure('analytics');
		}
	}

	/**
	 * @brief Logs only an integration type, without submitted content or identities.
	 * @param string $kind Type of event dispatch failure.
	 * @return void
	 */
	private static function logFailure(string $kind): void
	{
		try
		{
			Log::add('Punga Contact Form: ' . $kind . ' event listener failed.', Log::WARNING, 'com_pungacontactform');
		}
		catch (\Throwable)
		{
			// Logging must not cause failed requests either.
		}
	}
}
