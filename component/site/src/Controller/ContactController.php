<?php
/**
 * @file
 * @brief Contact form POST handling and email delivery.
 */
namespace Punga\Component\Pungacontactform\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Punga\Component\Pungacontactform\Site\Helper\AnalyticsHelper;
use Punga\Component\Pungacontactform\Site\Helper\FormHelper;
use Punga\Component\Pungacontactform\Site\Helper\MessageStoreHelper;
use Punga\Component\Pungacontactform\Site\Helper\SenderCopyHelper;
use Punga\Component\Pungacontactform\Site\Helper\SpamHelper;
use Punga\Component\Pungacontactform\Site\Helper\ThrottleHelper;

/** @brief Accepts only session-issued POST contexts; sends plain-text mail. */
final class ContactController extends BaseController
{
    /**
     * @brief Handles one form submission and redirects to its original page.
     * @return void
     */
    public function send(): void
    {
        $app = Factory::getApplication();
        $app->getLanguage()->load('com_pungacontactform', JPATH_SITE);
        // Must check before accessing any stored form context.
        $this->checkToken('post');

        $contextId = (string) $this->input->post->get('context', '', 'cmd');
        $session = $app->getSession();
        $contexts = $session->get('pungacontactform.contexts', []);
        $context = is_array($contexts) && isset($contexts[$contextId]) ? $contexts[$contextId] : null;

        $fallback = Route::_('index.php?option=com_pungacontactform&view=contact', false);
        if (!is_array($context) || !isset($context['created'], $context['settings'], $context['source'], $context['return'])
            || time() - (int) $context['created'] > FormHelper::CONTEXT_TTL
            || (int) $context['created'] > time() + 60)
        {
            $this->setRedirect($fallback, Text::_('COM_PUNGACONTACTFORM_FORM_EXPIRED'), 'error');
            return;
        }

        // Single-use context: prevent refreshes and concurrent double-click POSTs.
        unset($contexts[$contextId]);
        $session->set('pungacontactform.contexts', $contexts);

        $return = (string) $context['return'];
        if (!str_starts_with($return, '/') || str_starts_with($return, '//') || preg_match('/[\r\n]/', $return))
        {
            $return = $fallback;
        }
        $settings = $context['settings'];
        $source = (string) $context['source'];
        $error = trim((string) ($settings['error'] ?? '')) ?: Text::_('COM_PUNGACONTACTFORM_ERROR_DEFAULT');
        $success = trim((string) ($settings['success'] ?? '')) ?: Text::_('COM_PUNGACONTACTFORM_SUCCESS_DEFAULT');
        $data = $this->input->post->get('pcf', [], 'array');
        $data = is_array($data) ? $data : [];

        $plain = static function (mixed $value): string
        {
            return is_string($value) ? trim($value) : '';
        };
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $plain($data['name'] ?? ''));
        $email = $plain($data['email'] ?? '');
        $subject = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $plain($data['subject'] ?? ''));
        $body = str_replace(["\r\n", "\r"], "\n", $plain($data['body'] ?? ''));
        $body = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $body);
        $copy = !empty($data['copy']);
        $isGuest = (bool) $app->getIdentity()->guest;
        $stored = [
            'name' => $name, 'email' => $email, 'subject' => $subject,
            'body' => $body, 'copy' => $copy,
        ];

        $failed = function () use ($app, $session, $return, $error, $stored, $source): void
        {
            $session->set('pungacontactform.previous.' . $source, $stored);
            // Joomla messages can contain HTML: convert custom messages to text.
            $this->setRedirect($return, FormHelper::escape($error), 'error');
        };

        // Enforce the quota before emitting statistics: one IP cannot generate
        // an unbounded stream of fabricated rejection events.
        try
        {
            if (!ThrottleHelper::allow((int) $settings['max_per_hour']))
            {
                $failed();
                return;
            }
        }
        catch (\Throwable)
        {
            $failed(); // Fail closed on database errors; not classified as spam.
            return;
        }

        if ($plain($data['website'] ?? '') !== '')
        {
            AnalyticsHelper::record('rejected', $source, $isGuest, 'honeypot');
            MessageStoreHelper::record('rejected', 'honeypot', $source, $isGuest);
            // Conceal honeypot detection from automated senders.
            $session->clear('pungacontactform.previous.' . $source);
            $this->setRedirect($return, FormHelper::escape($success), 'message');
            return;
        }
        if (time() - (int) $context['created'] < (int) $settings['min_fill_seconds'])
        {
            AnalyticsHelper::record('rejected', $source, $isGuest, 'too_fast');
            MessageStoreHelper::record('rejected', 'too_fast', $source, $isGuest);
            $failed();
            return;
        }
        if (!filter_var((string) $settings['recipient'], FILTER_VALIDATE_EMAIL)
            || $name === '' || mb_strlen($name) > 120
            || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254
            || $subject === '' || mb_strlen($subject) > 200
            || $body === '' || mb_strlen($body) > 20000)
        {
            AnalyticsHelper::record('rejected', $source, $isGuest, 'validation');
            MessageStoreHelper::record('rejected', 'validation', $source, $isGuest);
            $failed();
            return;
        }

        if (!FormHelper::verifyCaptcha((string) $context['captcha_plugin'], $data['captcha'] ?? ''))
        {
            AnalyticsHelper::record('rejected', $source, $isGuest, 'captcha');
            MessageStoreHelper::record('rejected', 'captcha', $source, $isGuest);
            $failed();
            return;
        }

        // Check the submitting user's actual Joomla identity. Menu/module state
        // alone cannot prove registration. CAPTCHA, quotas and validation above
        // remain active regardless of the selected content-filter mode.
        $mode = (string) $settings['spam_filter_mode'];
        $scan = $mode === 'all' || ($mode === 'guests' && $isGuest);
        $analysis = $scan
            ? SpamHelper::analyzeDetailed($subject, $body, (string) ($settings['spam_keywords'] ?? ''))
            : ['score'=>0, 'matches'=>[]];
        $analysis['threshold'] = (int) $settings['spam_threshold'];
        if ($scan && $analysis['score'] >= $analysis['threshold'])
        {
            AnalyticsHelper::record('rejected', $source, $isGuest, 'content_filter', $analysis['score']);
            MessageStoreHelper::record('rejected', 'content_filter', $source, $isGuest, $stored, $analysis);
            // Deliberately do not disclose spam-filter decisions to visitors.
            $session->clear('pungacontactform.previous.' . $source);
            $this->setRedirect($return, FormHelper::escape($success), 'message');
            return;
        }

        $mailBody = Text::sprintf('COM_PUNGACONTACTFORM_MAIL_FROM', $name)
            . "\n" . Text::sprintf('COM_PUNGACONTACTFORM_MAIL_EMAIL', $email)
            . "\n" . Text::sprintf('COM_PUNGACONTACTFORM_MAIL_SUBJECT', $subject)
            . "\n\n" . $body;
        try
        {
            // Joomla 6: Factory::getConfig() was removed. Resolve mailer and
            // application config inside the exception boundary, too.
            $factory = Factory::getContainer()->get(MailerFactoryInterface::class);
            $config = $app->getConfig();
            $from = (string) $config->get('mailfrom', '');
            $fromName = (string) $config->get('fromname', '');
            if (!filter_var($from, FILTER_VALIDATE_EMAIL))
            {
                throw new \RuntimeException('Global Joomla mail sender not configured');
            }
            $mailer = $factory->createMailer();
            $mailer->setSender($from, $fromName);
            $mailer->addRecipient((string) $settings['recipient']);
            $mailer->addReplyTo($email, $name);
            $mailer->setSubject($subject);
            $mailer->setBody($mailBody);
            // MailerInterface::send returns void and throws on failure.
            $mailer->send();
        }
        catch (\Throwable $exception)
        {
            // Never expose recipient, sender, message or SMTP credentials in
            // frontend output or the diagnostic log.
            Log::add('Punga Contact Form: mail transport failed (' . get_class($exception) . ').', Log::WARNING, 'com_pungacontactform');
            MessageStoreHelper::record('failed', 'mail_transport', $source, $isGuest, $stored, $analysis);
            $failed();
            return;
        }

        // Copies are permitted only with an explicitly configured public From
        // address distinct from both the current and the component recipient.
        // Never let Joomla's default (possibly private) From leak to visitors.
        $copyRequested = $settings['copy_mode'] === 'always'
            || ($settings['copy_mode'] === 'optional' && $copy);
        if ($copyRequested && SenderCopyHelper::available($settings))
        {
            try
            {
                SenderCopyHelper::send($factory, $settings, $email, $name, $subject, $mailBody);
            }
            catch (\Throwable)
            {
                // Main delivery succeeded; never expose mail configuration in logs.
                Log::add('Punga Contact Form: public sender copy failed.', Log::WARNING, 'com_pungacontactform');
            }
        }
        elseif ($copyRequested)
        {
            // Fail closed. The main contact message still reaches its recipient.
            Log::add('Punga Contact Form: sender copy suppressed (public sender missing or unsafe).', Log::WARNING, 'com_pungacontactform');
        }

        MessageStoreHelper::record('sent', '', $source, $isGuest, $stored, $analysis);
        AnalyticsHelper::record('sent', $source, $isGuest);
        $session->clear('pungacontactform.previous.' . $source);
        $this->setRedirect($return, FormHelper::escape($success), 'message');
    }
}
