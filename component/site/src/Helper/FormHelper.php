<?php
/**
 * @file
 * @brief Shared rendering, configuration and security helpers for both frontends.
 */
namespace Punga\Component\Pungacontactform\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * @brief Manages the two contact-form frontends without placing private settings in HTML.
 *
 * Settings and return paths are bound to single-use random context IDs in the
 * server session. Neither the destination email nor the server-side settings
 * are transferred to the browser.
 */
final class FormHelper
{
    /** @var int Lifetime of a form context in seconds. */
    public const CONTEXT_TTL = 1800;

    /**
     * @brief Escapes arbitrary text for safe HTML output.
     * @param string $value Value to escape.
     * @return string Escaped HTML.
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /**
     * @brief Returns the effective settings for a form instance.
     * @param Registry|null $overrides Menu or module parameter overrides.
     * @return array<string, mixed> Private, validated settings.
     */
    public static function settings(?Registry $overrides): array
    {
        $global = ComponentHelper::getParams('com_pungacontactform');
        $get = static function (string $key, string $default = '') use ($global, $overrides): string
        {
            $globalValue = (string) $global->get($key, $default);
            $overrideValue = $overrides ? (string) $overrides->get($key, '') : '';
            return $overrideValue !== '' && $overrideValue !== 'inherit' ? $overrideValue : $globalValue;
        };

        $copyMode = $get('copy_mode', 'optional');
        $captchaMode = $get('captcha_mode', 'auto');
        $spamMode = $get('spam_filter_mode', 'guests');

        return [
            'recipient' => trim($get('recipient_email')),
            'default_recipient' => trim((string) $global->get('recipient_email', '')),
            // Explicit public identity only: never inherit Joomla's global From for copies.
            'copy_sender_email' => trim((string) $global->get('copy_sender_email', '')),
            'copy_sender_name' => (string) $global->get('copy_sender_name', ''),
            'copy_mode' => in_array($copyMode, ['off', 'optional', 'always'], true) ? $copyMode : 'optional',
            'captcha_mode' => in_array($captchaMode, ['auto', 'required', 'off'], true) ? $captchaMode : 'auto',
            'spam_filter_mode' => in_array($spamMode, ['off', 'guests', 'all'], true) ? $spamMode : 'guests',
            'spam_threshold' => max(4, min(20, (int) $global->get('spam_threshold', 8))),
            'spam_keywords' => (string) $global->get('spam_keywords', ''),
            'intro' => $get('intro_text'),
            'success' => $get('success_message', Text::_('COM_PUNGACONTACTFORM_SUCCESS_DEFAULT')),
            'error' => $get('error_message', Text::_('COM_PUNGACONTACTFORM_ERROR_DEFAULT')),
            'min_fill_seconds' => max(1, min(30, (int) $global->get('min_fill_seconds', 3))),
            'max_per_hour' => max(1, min(50, (int) $global->get('max_per_hour', 10))),
        ];
    }

    /**
     * @brief Reads the active Joomla CAPTCHA plugin name.
     * @param array<string, mixed> $settings Effective settings.
     * @return string Empty when CAPTCHA is disabled or not configured.
     */
    public static function captchaPlugin(array $settings): string
    {
        if ($settings['captcha_mode'] === 'off')
        {
            return '';
        }

        $plugin = (string) Factory::getApplication()->get('captcha', '');
        return $plugin === '0' ? '' : $plugin;
    }

    /**
     * @brief Creates Joomla's native CAPTCHA field, using the selected plugin.
     * @param string $plugin Plugin name from Joomla Global Configuration.
     * @return Form Joomla form with a CAPTCHA field.
     */
    private static function captchaForm(string $plugin): Form
    {
        $xml = '<form><field name="captcha" type="captcha" validate="captcha"'
            . ' plugin="' . htmlspecialchars($plugin, ENT_QUOTES | ENT_XML1, 'UTF-8') . '"'
            . ' namespace="pungacontactform" label="COM_PUNGACONTACTFORM_CAPTCHA_LABEL" /></form>';

        $form = new Form('pungacontactform.captcha', ['control' => 'pcf']);
        $form->load($xml);
        return $form;
    }

    /**
     * @brief Verifies a CAPTCHA response against Joomla's validation rules.
     * @param string $plugin The configured CAPTCHA plugin.
     * @param mixed $answer Field answer (may be null for invisible providers).
     * @return bool True if the CAPTCHA plugin accepts the answer.
     */
    public static function verifyCaptcha(string $plugin, mixed $answer): bool
    {
        if ($plugin === '')
        {
            return true;
        }

        try
        {
            $form = self::captchaForm($plugin);
            return $form->validate(['captcha' => is_string($answer) ? $answer : '']);
        }
        catch (\Throwable)
        {
            return false;
        }
    }

    /**
     * @brief Renders a contact form and stores a private, expiring server-side context.
     * @param Registry|null $overrides Menu or module parameters.
     * @param string $source Either menu or module.
     * @param int $sourceId Unique module or menu identifier.
     * @return string Safe form HTML.
     */
    public static function render(?Registry $overrides, string $source, int $sourceId = 0): string
    {
        $app = Factory::getApplication();
        $app->getLanguage()->load('com_pungacontactform', JPATH_SITE);
        $app->setHeader('Cache-Control', 'private, no-store, max-age=0', true);
        HTMLHelper::_('stylesheet', 'media/com_pungacontactform/css/form.css', ['version' => 'auto']);

        $settings = self::settings($overrides);
        $plugin = self::captchaPlugin($settings);
        if (!filter_var($settings['recipient'], FILTER_VALIDATE_EMAIL)
            || ($settings['captcha_mode'] === 'required' && $plugin === ''))
        {
            return '<p class="alert alert-warning">' . Text::_('COM_PUNGACONTACTFORM_UNAVAILABLE') . '</p>';
        }

        $captchaHtml = '';
        if ($plugin !== '')
        {
            try
            {
                $captchaForm = self::captchaForm($plugin);
                $captchaField = $captchaForm->getField('captcha');
                if (!$captchaField || $captchaField->hidden)
                {
                    return '<p class="alert alert-warning">' . Text::_('COM_PUNGACONTACTFORM_UNAVAILABLE') . '</p>';
                }
                $captchaHtml = $captchaField->renderField();
            }
            catch (\Throwable)
            {
                return '<p class="alert alert-warning">' . Text::_('COM_PUNGACONTACTFORM_UNAVAILABLE') . '</p>';
            }
        }

        $session = $app->getSession();
        $contexts = $session->get('pungacontactform.contexts', []);
        if (!is_array($contexts))
        {
            $contexts = [];
        }
        $now = time();
        $contexts = array_filter($contexts, static function ($context) use ($now): bool
        {
            return is_array($context) && isset($context['created']) && $context['created'] >= $now - self::CONTEXT_TTL;
        });
        // Bound session storage even if a bot repeatedly loads a page.
        $contexts = array_slice($contexts, -31, null, true);
        $contextId = bin2hex(random_bytes(20));
        $sourceKey = $source . ':' . $sourceId;
        $returnPath = Uri::getInstance()->toString(['path', 'query']);
        if (!str_starts_with($returnPath, '/') || str_starts_with($returnPath, '//'))
        {
            $returnPath = Uri::root(true) . '/index.php';
        }
        $contexts[$contextId] = [
            'created' => $now,
            'source' => $sourceKey,
            'return' => $returnPath,
            'settings' => $settings,
            'captcha_plugin' => $plugin,
        ];
        $session->set('pungacontactform.contexts', $contexts);

        $previous = $session->get('pungacontactform.previous.' . $sourceKey, []);
        $session->clear('pungacontactform.previous.' . $sourceKey);
        $previous = is_array($previous) ? $previous : [];
        $user = $app->getIdentity();
        $name = (string) ($previous['name'] ?? (!$user->guest ? $user->name : ''));
        $email = (string) ($previous['email'] ?? (!$user->guest ? $user->email : ''));
        $subject = (string) ($previous['subject'] ?? '');
        $body = (string) ($previous['body'] ?? '');
        $wantCopy = !empty($previous['copy']);
        $isMenu = $source === 'menu';
        $action = Route::_('index.php?option=com_pungacontactform&task=contact.send', false);
        $cancelUrl = Route::_('index.php', false);
        $formId = 'pcf-' . $contextId;

        ob_start();
        require \dirname(__DIR__, 2) . '/tmpl/contact/form.php';
        return (string) ob_get_clean();
    }
}
