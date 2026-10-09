<?php
/**
 * @file
 * @brief PHP CLI tests for deterministic spam scoring and user-group bypass.
 */
\define('_JEXEC', 1);

require_once __DIR__ . '/../component/site/src/Helper/SpamHelper.php';

use Punga\Component\Pungacontactform\Site\Helper\SpamHelper;

/**
 * @brief Fails the test process if a condition is false.
 * @param bool $condition Tested condition.
 * @param string $label Diagnostic name.
 * @return void
 */
function requireCondition(bool $condition, string $label): void
{
	if (!$condition)
	{
		throw new \RuntimeException('FAILED: ' . $label);
	}
}

$guestOnly = [
	'spam_filter_mode' => 'guests',
	'spam_threshold' => 8,
	'spam_keywords' => '',
];
$plain = 'I would like to ask about the next meeting, and I would appreciate a reply.';
$linkInContext = 'Please have a look at https://www.joomla.org/documentation, because I have a question about this page.';
$normalGerman = 'Guten Tag, ich hätte gern Informationen zur nächsten Veranstaltung im Haus.';
$aloneLink = 'https://click-this-example.org/promo';

requireCondition(SpamHelper::analyze('Question', $plain) === 0, 'Ordinary message');
requireCondition(SpamHelper::analyze('Question', $linkInContext) < 8, 'Ordinary message with URL');
requireCondition(SpamHelper::analyze('Anfrage', $normalGerman) < 8, 'Ordinary German message');
requireCondition(SpamHelper::analyze('Look', $aloneLink) >= 8, 'Only HTTPS link');
requireCondition(SpamHelper::analyze('Look', 'promotion.xyz') >= 8, 'Only bare domain');
requireCondition(SpamHelper::analyze('Look', 'See: www.example.com') >= 8, 'Very short text and link');
requireCondition(SpamHelper::analyze('Offer', 'BUY VIAGRA TODAY') >= 8, 'High-confidence spam keyword');
requireCondition(SpamHelper::analyze('SEO', 'We sell paid backlinks and guest posts.') >= 8, 'Backlink campaign');
requireCondition(SpamHelper::analyze('What?', 'bbbbbbbbbbbbbbbbbbbbbbbbbb') >= 8, 'Repeated random character');
requireCondition(SpamHelper::analyze('What?', 'bcdfghjklmnp qrstvwxyzbcdfg') >= 8, 'Unpronounceable tokens');
requireCondition(SpamHelper::analyze('Test', 'The library is next to the old casino.') < 8, 'No broad keyword false-positive');
requireCondition(SpamHelper::analyze('Test', 'Please send the paper', "Send the paper\ncontact me") === 20, 'Custom phrase exact match');
requireCondition(SpamHelper::analyze('Test', 'Please send the paperclip', 'the paper') === 0, 'Custom phrase word boundaries');
requireCondition(SpamHelper::analyze('Test', 'Please send the paper', 'the (paper)') === 0, 'Custom phrase is not a regex');
requireCondition(SpamHelper::shouldDiscard($guestOnly, true, 'Look', $aloneLink), 'Guest rejected in guests-only mode');
requireCondition(!SpamHelper::shouldDiscard($guestOnly, false, 'Look', $aloneLink), 'Registered user exempt from content filter');
requireCondition(!SpamHelper::shouldDiscard(['spam_filter_mode' => 'off'], true, 'Look', $aloneLink), 'Off disables for guests');
requireCondition(!SpamHelper::shouldDiscard(['spam_filter_mode' => 'off'], false, 'Look', $aloneLink), 'Off disables for registered');
requireCondition(SpamHelper::shouldDiscard(['spam_filter_mode' => 'all'], false, 'Look', $aloneLink), 'All includes registered');
requireCondition(!SpamHelper::shouldDiscard(['spam_filter_mode' => 'all', 'spam_threshold' => 20], false, 'Look', $aloneLink), 'Higher threshold is less strict');
requireCondition(SpamHelper::shouldDiscard(['spam_filter_mode' => 'all', 'spam_keywords' => 'totally-made-up-keyword'], false, 'Hello', 'Please send me totally-made-up-keyword'), 'Custom keyword forces discard');

print "OK: 21 spam heuristic and guest/registered configuration checks\n";
