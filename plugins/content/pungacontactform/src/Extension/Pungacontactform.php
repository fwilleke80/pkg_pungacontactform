<?php
/**
 * @file
 * @brief Safe contact form placeholder expansion in Joomla content.
 */
namespace Punga\Plugin\Content\Pungacontactform\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Punga\Component\Pungacontactform\Site\Helper\FormHelper;

/** @brief Renders forms from `{contactform ...}` within server-prepared content. */
final class Pungacontactform extends CMSPlugin implements SubscriberInterface
{
    /** @var bool Load translations through Joomla. */
    protected $autoloadLanguage = true;

    /**
     * @brief Registers the supported Joomla 6 content event.
     * @return array<string, string> Event-handler map.
     */
    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'prepare'];
    }

    /**
     * @brief Parses only named, allowlisted placeholder options.
     * @param string $arguments Text inside the contact-form placeholder.
     * @return array<string, string>|null Parsed options, or null on malformed input.
     */
    public static function parseOptions(string $arguments): ?array
    {
        $arguments = trim($arguments);
        if ($arguments === '')
        {
            return [];
        }
        $options = [];
        foreach (preg_split('/\s+/', $arguments) ?: [] as $pair)
        {
            if (!preg_match('/^(module|layout|intro)=([A-Za-z0-9_-]+)$/iD', $pair, $parts))
            {
                return null;
            }
            $key = strtolower($parts[1]);
            if (array_key_exists($key, $options))
            {
                return null;
            }
            $options[$key] = strtolower($parts[2]);
        }
        if (isset($options['module']) && (!ctype_digit($options['module']) || (int) $options['module'] < 1))
        {
            return null;
        }
        if (isset($options['layout']) && !in_array($options['layout'], ['left', 'centered'], true))
        {
            return null;
        }
        if (isset($options['intro']) && !in_array($options['intro'], ['yes', 'no'], true))
        {
            return null;
        }
        return $options;
    }

    /**
     * @brief Replace each placeholder with a separately secured form instance.
     * @param ContentPrepareEvent $event Joomla's reference-backed content event.
     * @return void
     */
    public function prepare(ContentPrepareEvent $event): void
    {
        $app = $this->getApplication();
        if (!$app->isClient('site'))
        {
            return;
        }

        $item = $event->getItem();
        if (!is_object($item) || !isset($item->text) || !is_string($item->text)
            || stripos($item->text, '{contactform') === false)
        {
            return;
        }

        $app->bootComponent('com_pungacontactform');
        $app->getLanguage()->load('com_pungacontactform', JPATH_SITE);
        $context = (string) $event->getContext();
        $itemId = (int) ($item->id ?? 0);
        $counter = 0;
        $item->text = preg_replace_callback('/\{contactform(?:\s+([^{}]*))?\}/i',
            function (array $match) use ($context, $itemId, &$counter): string
            {
                ++$counter;
                $options = self::parseOptions((string) ($match[1] ?? ''));
                if ($options === null)
                {
                    return $match[0];
                }

                $overrides = new Registry();
                if (isset($options['module']))
                {
                    // getModuleById only exposes published modules available
                    // to this visitor and assigned to this page/menu context.
                    $module = ModuleHelper::getModuleById($options['module']);
                    if (!is_object($module) || ($module->module ?? '') !== 'mod_pungacontactform'
                        || (int) ($module->id ?? 0) !== (int) $options['module'])
                    {
                        return '';
                    }
                    $overrides = new Registry((string) ($module->params ?? '{}'));
                }
                if (isset($options['layout']))
                {
                    $overrides->set('form_alignment', $options['layout']);
                }
                if (($options['intro'] ?? '') === 'no')
                {
                    $overrides->set('pcf_hide_intro', '1');
                }

                // Use a distinct stable form source per occurrence, even when
                // several embeds reference the same configured module.
                $key = $context . ':' . $itemId . ':' . $counter;
                $sourceId = crc32($key) & 0x7fffffff;
                return FormHelper::render($overrides, 'embed', $sourceId);
            }, $item->text) ?? $item->text;
    }
}
