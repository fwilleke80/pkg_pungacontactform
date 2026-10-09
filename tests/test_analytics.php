<?php
/**
 * @file
 * @brief Standalone Joomla event-contract regression test using narrow CMS stubs.
 */
namespace Joomla\Event
{
	/** @brief Minimal Event-compatible named argument container. */
	class Event
	{
		/** @var string */
		public string $name;
		/** @var array<string,mixed> */
		public array $arguments;

		/**
		 * @brief Stores arguments for the event dispatcher test double.
		 * @param string $name Joomla event name.
		 * @param array<string,mixed> $arguments Named event arguments.
		 */
		public function __construct(string $name, array $arguments = [])
		{
			$this->name = $name;
			$this->arguments = $arguments;
		}
	}
}

namespace Joomla\CMS\Log
{
	/** @brief Non-operational test logger. */
	class Log
	{
		public const WARNING = 4;
		/**
		 * @brief Suppress intentional test exception logging.
		 * @param string $message Log message.
		 * @param int $level Log level.
		 * @param string $category Log category.
		 * @return void
		 */
		public static function add(string $message, int $level, string $category): void
		{
		}
	}
}

namespace Joomla\CMS
{
	/** @brief CMS factory shim used only by these CLI tests. */
	class Factory
	{
		public static object $application;
		/** @brief Returns the fake Joomla application. */
		public static function getApplication(): object
		{
			return self::$application;
		}
	}
}

namespace
{
	\define('_JEXEC', 1);
	require_once __DIR__ . '/../component/site/src/Helper/AnalyticsHelper.php';

	use Joomla\CMS\Factory;
	use Joomla\Event\Event;
	use Punga\Component\Pungacontactform\Site\Helper\AnalyticsHelper;

	/** @brief Dummy dispatcher preserving event name and arguments. */
	class TestDispatcher
	{
		/** @var list<Event> */
		public array $events = [];
		public bool $failDomain = false;

		/**
		 * @brief Dispatches the event, simulating an optional broken listener.
		 * @param string $name Event name.
		 * @param Event $event Emitted event.
		 * @return Event
		 */
		public function dispatch(string $name, Event $event): Event
		{
			if ($this->failDomain && str_starts_with($name, 'onPungaContactForm'))
			{
				throw new \RuntimeException('Bad third-party listener');
			}
			$this->events[] = $event;
			return $event;
		}
	}

	/** @brief Fails early with an informative diagnostic. */
	function check(bool $condition, string $reason): void
	{
		if (!$condition)
		{
			throw new \RuntimeException($reason);
		}
	}

	$dispatcher = new TestDispatcher();
	Factory::$application = new class($dispatcher)
	{
		public function __construct(private TestDispatcher $dispatcher)
		{
		}
		public function getDispatcher(): TestDispatcher
		{
			return $this->dispatcher;
		}
	};

	AnalyticsHelper::record('sent', 'menu:17', true);
	AnalyticsHelper::record('rejected', 'module:5', false, 'content_filter', 12);
	AnalyticsHelper::record('rejected', 'module:5', true, 'honeypot');
	AnalyticsHelper::record('unknown', 'menu:1', true);
	check(count($dispatcher->events) === 6, 'Two Joomla events expected per valid record');
	check($dispatcher->events[0]->name === 'onPungaContactFormSent', 'Missing sent domain event');
	check($dispatcher->events[1]->name === 'onPungaAnalyticsRecord', 'Missing sent analytics event');
	check($dispatcher->events[1]->arguments['event_type'] === 'contact.sent', 'Incorrect success analytics ID');
	check($dispatcher->events[1]->arguments['component'] === 'com_pungacontactform', 'Incorrect component key');
	check($dispatcher->events[2]->name === 'onPungaContactFormRejected', 'Missing rejection domain event');
	check($dispatcher->events[3]->arguments['event_type'] === 'contact.rejected', 'Incorrect rejection analytics ID');
	check($dispatcher->events[2]->arguments['data']['reason'] === 'content_filter', 'Missing reason');
	check($dispatcher->events[2]->arguments['data']['spam_score'] === 12, 'Missing score');
	check($dispatcher->events[2]->arguments['data']['source'] === 'module', 'Missing source type');
	check($dispatcher->events[2]->arguments['data']['source_id'] === 5, 'Missing source ID');
	check($dispatcher->events[2]->arguments['data']['visitor_type'] === 'registered', 'Missing visitor type');
	check(!isset($dispatcher->events[4]->arguments['data']['spam_score']), 'Score should be absent outside content filter');
	check(!isset($dispatcher->events[0]->arguments['data']['reason']), 'Sent event must not report spam');
	foreach ($dispatcher->events as $event)
	{
		$serialized = json_encode($event->arguments);
		check($serialized !== false && !preg_match('/recipient|subject|body|email|message|ip_address|sender_name/i', $serialized),
			'Private correspondence field leaked into analytics');
	}

	$dispatcher->failDomain = true;
	AnalyticsHelper::record('sent', 'menu:1', false);
	check(count($dispatcher->events) === 7, 'Failing third-party listener must not prevent analytics bridge');
	check($dispatcher->events[6]->arguments['event_type'] === 'contact.sent', 'Analytics after listener failure');
	print "OK: 19 Joomla domain/analytics event contract and privacy checks\n";
}
