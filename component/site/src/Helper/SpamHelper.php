<?php
/**
 * @file
 * @brief Conservative, deterministic content heuristics for unsolicited messages.
 */
namespace Punga\Component\Pungacontactform\Site\Helper;

\defined('_JEXEC') or die;

/**
 * @brief Scores suspicious messages without remote services or storing message text.
 *
 * The heuristic filter complements CAPTCHA and rate limiting. It does not claim
 * to recognize arbitrary nonsense or guarantee detection of all spam.
 */
final class SpamHelper
{
	/**
	 * @brief Decides whether content should be silently discarded for this visitor.
	 * @param array<string,mixed> $settings Effective server-side settings.
	 * @param bool $isGuest Whether Joomla considers the submitting identity a guest.
	 * @param string $subject Validated subject.
	 * @param string $body Validated body.
	 * @return bool True if the content must not be emailed.
	 */
	public static function shouldDiscard(array $settings, bool $isGuest, string $subject, string $body): bool
	{
		$mode = (string) ($settings['spam_filter_mode'] ?? 'guests');

		if ($mode === 'off' || ($mode === 'guests' && !$isGuest))
		{
			return false;
		}

		// Reject unknown modes rather than accidentally disabling the filter.
		$threshold = max(4, min(20, (int) ($settings['spam_threshold'] ?? 8)));
		$score = self::analyze($subject, $body, (string) ($settings['spam_keywords'] ?? ''));

		return $score >= $threshold;
	}

	/**
	 * @brief Computes a spam score from body/subject text.
	 * @param string $subject Plain-text subject.
	 * @param string $body Plain-text message.
	 * @param string $extraKeywords Admin-defined literal phrases, one per line.
	 * @return int Higher values are more suspicious; score is not a probability.
	 */
	public static function analyze(string $subject, string $body, string $extraKeywords = ''): int
	{
		// Do not inspect or fetch the link destinations: matching is strictly local.
		$linkPattern = '~(?<![\p{L}\p{N}@])(?:https?://|www\.)[^\s<>"\']+|(?<![\p{L}\p{N}@])(?:[\p{L}\p{N}-]+\.)+(?:com|org|net|de|io|info|ru|xyz|shop|top|online|site|biz|co)(?:/[^\s<>"\']*)?~iu';
		$links = [];
		$linkCount = preg_match_all($linkPattern, $body, $links);
		$linkCount = $linkCount === false ? 0 : $linkCount;
		$score = 0;

		if ($linkCount > 0)
		{
			$withoutLinks = preg_replace($linkPattern, ' ', $body);
			$withoutLinks = is_string($withoutLinks) ? $withoutLinks : $body;
			$remainingLetters = preg_replace('/[^\p{L}\p{N}]/u', '', $withoutLinks);
			$remainingLength = is_string($remainingLetters) ? self::unicodeLength($remainingLetters) : self::unicodeLength(trim($withoutLinks));

			if ($remainingLength < 10)
			{
				$score += 12; // URL alone, or just a negligible prompt such as "see:".
			}
			elseif ($remainingLength < 30)
			{
				$score += 3;
			}

			if ($linkCount >= 3)
			{
				$score += 5;
			}
			elseif ($linkCount === 2)
			{
				$score += 3;
			}
		}

		$combined = $subject . "\n" . $body;
		$normalized = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $combined);
		$normalized = is_string($normalized) ? $normalized : $combined;

		// Deliberately specific phrases only: an ordinary mention of "SEO" or
		// "casino" does not automatically discard an otherwise legitimate email.
		$strong = [
			'/\b(?:viagra|cialis|levitra|kamagra)\b/iu',
			'/\b(?:buy\s+(?:followers|backlinks)|sell\s+backlinks|guest\s+post(?:ing)?|paid\s+backlinks?)\b/iu',
			'/\b(?:online\s+casino|casino\s+bonus|free\s+spins|slot\s+machine\s+bonus)\b/iu',
			'/\b(?:guaranteed\s+(?:returns?|profits?|income)|double\s+your\s+(?:money|bitcoin))\b/iu',
			'/\b(?:escort\s+service|adult\s+dating|porn\s+site)\b/iu',
			'/\b(?:seo\s+backlinks?|backlink\s+packages?|rank\s+(?:on|in)\s+google)\b/iu',
			'/\b(?:suchmaschinenranking\s+verbessern|backlinks?\s+kaufen|garantierte\s+rendite)\b/iu',
		];

		foreach ($strong as $pattern)
		{
			if (preg_match($pattern, $normalized) === 1)
			{
				$score += 9;
				break;
			}
		}

		$secondary = [
			'/\b(?:link\s+building|search\s+engine\s+optimization|seo\s+services?|boost\s+your\s+traffic)\b/iu',
			'/\b(?:crypto\s+investment|invest\s+in\s+crypto|earn\s+money\s+fast)\b/iu',
			'/\b(?:affiliate\s+marketing|limited\s+time\s+offer)\b/iu',
		];

		foreach ($secondary as $pattern)
		{
			if (preg_match($pattern, $normalized) === 1)
			{
				$score += 4;
			}
		}

		// Administrator-entered literal phrases are explicit rejection rules,
		// unlike the softer built-in hints. Cap work and escape regex metacharacters.
		$keywords = array_slice(preg_split('/\R/u', $extraKeywords) ?: [], 0, 50);

		foreach ($keywords as $keyword)
		{
			$keyword = trim($keyword);

			if (self::unicodeLength($keyword) < 4 || self::unicodeLength($keyword) > 80)
			{
				continue;
			}

			$pattern = '~(?<![\p{L}\p{N}])' . preg_quote($keyword, '~') . '(?![\p{L}\p{N}])~iu';

			if (preg_match($pattern, $normalized) === 1)
			{
				$score += 20;
				break;
			}
		}

		// Catch obvious machine-generated noise; don't penalize short messages
		// merely because they contain uncommon words or lack a dictionary match.
		$noLinks = preg_replace($linkPattern, ' ', $body);
		$noLinks = is_string($noLinks) ? $noLinks : $body;

		if (preg_match('/([\p{L}\p{N}])\1{11,}/iu', $noLinks) === 1
			|| preg_match('/([a-z]{2,6})\1{4,}/iu', $noLinks) === 1)
		{
			$score += 10;
		}

		if (preg_match_all('/\b[bcdfghjklmnpqrstvwxyz]{9,}\b/iu', $noLinks, $matches) >= 2)
		{
			$score += 9;
		}

		if (preg_match('/\b[a-z]{45,}\b/iu', $noLinks) === 1)
		{
			$score += 9;
		}

		return $score;
	}

	/**
	 * @brief Counts Unicode code points without requiring an optional PHP extension.
	 * @param string $text Valid UTF-8 text.
	 * @return int Character count.
	 */
	private static function unicodeLength(string $text): int
	{
		$count = preg_match_all('/./us', $text);
		return $count === false ? strlen($text) : $count;
	}
}

