<?php
/**
 * @file
 * @brief Deterministic, explainable content spam classification.
 */
namespace Punga\Component\Pungacontactform\Site\Helper;

\defined('_JEXEC') or die;

/** @brief Pure scoring logic: no network access, side effects, or user data storage. */
final class SpamHelper
{
    /**
     * @brief Built-in human-readable classifications with their literal regexes.
     * @return array<int,array{id:string,category:string,description:string,points:int,pattern:string,exclusive:bool}>
     */
    public static function builtInRules(): array
    {
        return [
            ['id'=>'pharmaceuticals','category'=>'Strong phrases','description'=>'Viagra, Cialis, Levitra, Kamagra','points'=>9,'pattern'=>'/\b(?:viagra|cialis|levitra|kamagra)\b/iu','exclusive'=>true],
            ['id'=>'paid_links','category'=>'Strong phrases','description'=>'Buy followers or backlinks, sell backlinks, guest posting, paid backlinks','points'=>9,'pattern'=>'/\b(?:buy\s+(?:followers|backlinks)|sell\s+backlinks|guest\s+post(?:ing)?|paid\s+backlinks?)\b/iu','exclusive'=>true],
            ['id'=>'gambling','category'=>'Strong phrases','description'=>'Online casino, casino bonus, free spins, slot machine bonus','points'=>9,'pattern'=>'/\b(?:online\s+casino|casino\s+bonus|free\s+spins|slot\s+machine\s+bonus)\b/iu','exclusive'=>true],
            ['id'=>'financial_scams','category'=>'Strong phrases','description'=>'Guaranteed returns/profits/income, double your money/bitcoin','points'=>9,'pattern'=>'/\b(?:guaranteed\s+(?:returns?|profits?|income)|double\s+your\s+(?:money|bitcoin))\b/iu','exclusive'=>true],
            ['id'=>'adult_advertising','category'=>'Strong phrases','description'=>'Escort service, adult dating, porn site','points'=>9,'pattern'=>'/\b(?:escort\s+service|adult\s+dating|porn\s+site)\b/iu','exclusive'=>true],
            ['id'=>'seo_links','category'=>'Strong phrases','description'=>'SEO backlinks, backlink packages, rank on/in Google','points'=>9,'pattern'=>'/\b(?:seo\s+backlinks?|backlink\s+packages?|rank\s+(?:on|in)\s+google)\b/iu','exclusive'=>true],
            ['id'=>'german_promotions','category'=>'Strong phrases','description'=>'Suchmaschinenranking verbessern, Backlinks kaufen, garantierte Rendite','points'=>9,'pattern'=>'/\b(?:suchmaschinenranking\s+verbessern|backlinks?\s+kaufen|garantierte\s+rendite)\b/iu','exclusive'=>true],
            ['id'=>'seo_services','category'=>'Secondary phrases','description'=>'Link building, search engine optimization, SEO services, boost your traffic','points'=>4,'pattern'=>'/\b(?:link\s+building|search\s+engine\s+optimization|seo\s+services?|boost\s+your\s+traffic)\b/iu','exclusive'=>false],
            ['id'=>'crypto_promotions','category'=>'Secondary phrases','description'=>'Crypto investment, invest in crypto, earn money fast','points'=>4,'pattern'=>'/\b(?:crypto\s+investment|invest\s+in\s+crypto|earn\s+money\s+fast)\b/iu','exclusive'=>false],
            ['id'=>'marketing_phrases','category'=>'Secondary phrases','description'=>'Affiliate marketing, limited time offer','points'=>4,'pattern'=>'/\b(?:affiliate\s+marketing|limited\s+time\s+offer)\b/iu','exclusive'=>false],
        ];
    }

    /**
     * @brief Returns score and matched rule contributions for administrator review.
     * @param string $subject Plain-text subject.
     * @param string $body Plain-text body.
     * @param string $extraKeywords One literal phrase per line, up to 50.
     * @return array{score:int,matches:array<int,array{id:string,description:string,points:int}>}
     */
    public static function analyzeDetailed(string $subject, string $body, string $extraKeywords = ''): array
    {
        $matches = [];
        $add = static function (string $id, string $description, int $points) use (&$matches): void
        {
            $matches[] = ['id'=>$id, 'description'=>$description, 'points'=>$points];
        };

        $linkPattern = '~(?<![\p{L}\p{N}@])(?:https?://|www\.)[^\s<>"\']+|(?<![\p{L}\p{N}@])(?:[\p{L}\p{N}-]+\.)+(?:com|org|net|de|io|info|ru|xyz|shop|top|online|site|biz|co)(?:/[^\s<>"\']*)?~iu';
        $links = [];
        $linkCount = preg_match_all($linkPattern, $body, $links);
        $linkCount = $linkCount === false ? 0 : $linkCount;
        if ($linkCount > 0)
        {
            $withoutLinks = preg_replace($linkPattern, ' ', $body) ?? $body;
            $letters = preg_replace('/[^\p{L}\p{N}]/u', '', $withoutLinks) ?? trim($withoutLinks);
            $length = self::unicodeLength($letters);
            if ($length < 10)
            {
                $add('link_only', 'URL without meaningful surrounding text', 12);
            }
            elseif ($length < 30)
            {
                $add('link_sparse', 'Very little text besides a URL', 3);
            }
            if ($linkCount >= 3)
            {
                $add('many_links', 'Three or more URLs', 5);
            }
            elseif ($linkCount === 2)
            {
                $add('two_links', 'Two URLs', 3);
            }
        }

        $combined = $subject . "\n" . $body;
        $normalized = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $combined) ?? $combined;
        foreach (self::builtInRules() as $rule)
        {
            if ($rule['exclusive'] && preg_match($rule['pattern'], $normalized) === 1)
            {
                $add($rule['id'], $rule['description'], $rule['points']);
                if ($rule['exclusive'])
                {
                    // Existing 0.1.2 semantics: only the first strong rule scores.
                    break;
                }
            }
        }
        // Secondary phrases are independent, even after a strong match.
        foreach (self::builtInRules() as $rule)
        {
            if (!$rule['exclusive'] && preg_match($rule['pattern'], $normalized) === 1)
            {
                $add($rule['id'], $rule['description'], $rule['points']);
            }
        }

        foreach (array_slice(preg_split('/\R/u', $extraKeywords) ?: [], 0, 50) as $keyword)
        {
            $keyword = trim($keyword);
            if (self::unicodeLength($keyword) < 4 || self::unicodeLength($keyword) > 80)
            {
                continue;
            }
            $pattern = '~(?<![\p{L}\p{N}])' . preg_quote($keyword, '~') . '(?![\p{L}\p{N}])~iu';
            if (preg_match($pattern, $normalized) === 1)
            {
                // The configured phrase is intentionally visible to admins only.
                $add('custom_phrase', 'Custom blocked phrase: ' . $keyword, 20);
                break;
            }
        }

        $noLinks = preg_replace($linkPattern, ' ', $body) ?? $body;
        if (preg_match('/([\p{L}\p{N}])\1{11,}/iu', $noLinks) === 1
            || preg_match('/([a-z]{2,6})\1{4,}/iu', $noLinks) === 1)
        {
            $add('repetitive_text', 'Extremely repetitive characters or syllables', 10);
        }
        if (preg_match_all('/\b[bcdfghjklmnpqrstvwxyz]{9,}\b/iu', $noLinks) >= 2)
        {
            $add('consonant_words', 'Two long consonant-only words', 9);
        }
        if (preg_match('/\b[a-z]{45,}\b/iu', $noLinks) === 1)
        {
            $add('long_word', 'Unusually long unbroken word', 9);
        }
        return ['score'=>array_sum(array_column($matches, 'points')), 'matches'=>$matches];
    }

    /**
     * @brief Compute the score with the same public signature as 0.1.2.
     * @return int Score (not a probability).
     */
    public static function analyze(string $subject, string $body, string $extraKeywords = ''): int
    {
        return self::analyzeDetailed($subject, $body, $extraKeywords)['score'];
    }

    /**
     * @brief Check whether the score should cause rejection for this identity.
     * @param array<string,mixed> $settings Effective settings.
     * @return bool Whether to discard.
     */
    public static function shouldDiscard(array $settings, bool $isGuest, string $subject, string $body): bool
    {
        $mode = (string) ($settings['spam_filter_mode'] ?? 'guests');
        if ($mode === 'off' || ($mode === 'guests' && !$isGuest))
        {
            return false;
        }
        return self::analyze($subject, $body, (string) ($settings['spam_keywords'] ?? ''))
            >= max(4, min(20, (int) ($settings['spam_threshold'] ?? 8)));
    }

    /** @brief Unicode character count without an optional PHP extension. */
    private static function unicodeLength(string $value): int
    {
        $count = preg_match_all('/./us', $value);
        return $count === false ? strlen($value) : $count;
    }
}
