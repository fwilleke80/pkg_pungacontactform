<?php
/**
 * @file
 * @brief Optional, privacy-safe automatic acknowledgement emails.
 */
namespace Punga\Component\Pungacontactform\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\Registry\Registry;

/** @brief Builds plain-text acknowledgements with no private recipient metadata. */
final class AutomaticReplyHelper
{
    /**
     * @brief Expands the small public placeholder vocabulary.
     * @param string $template Configured plain-text subject or body.
     * @param string $name Submitted visitor name.
     * @param string $subject Original validated contact subject.
     * @param string $siteName Configured public site name.
     * @return string Expanded plain text.
     */
    public static function expand(string $template, string $name, string $subject, string $siteName): string
    {
        return strtr($template, [
            '{name}' => $name,
            '{subject}' => $subject,
            '{site_name}' => $siteName,
        ]);
    }

    /**
     * @brief Checks that an acknowledgement can be sent without leaking private addresses.
     * @param array<string, mixed> $settings Effective server-only settings.
     * @return bool True if all required configuration is present.
     */
    public static function available(array $settings): bool
    {
        return ($settings['auto_reply_mode'] ?? 'off') === 'on'
            && SenderCopyHelper::publicAddress($settings) !== ''
            && trim((string) ($settings['auto_reply_subject'] ?? '')) !== ''
            && trim((string) ($settings['auto_reply_body'] ?? '')) !== '';
    }

    /**
     * @brief Sends a separate acknowledgement using an explicitly public mail identity.
     * @param MailerFactoryInterface $factory Joomla transport factory.
     * @param array<string, mixed> $settings Effective server-only settings.
     * @param string $email Validated visitor address.
     * @param string $name Validated visitor display name.
     * @param string $subject Validated contact subject.
     * @return void
     * @throws \RuntimeException When the automatic reply cannot be safely generated.
     */
    public static function send(
        MailerFactoryInterface $factory,
        array $settings,
        string $email,
        string $name,
        string $subject
    ): void
    {
        if (!self::available($settings))
        {
            throw new \RuntimeException('Automatic reply is disabled or configured unsafely');
        }

        $siteName = (string) \Joomla\CMS\Factory::getApplication()->get('sitename', '');
        $heading = self::expand((string) $settings['auto_reply_subject'], $name, $subject, $siteName);
        // Subject is a header. Remove newlines even when a placeholder contains them.
        $heading = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $heading) ?? '');
        $message = self::expand((string) $settings['auto_reply_body'], $name, $subject, $siteName);
        if ($heading === '' || mb_strlen($heading) > 200 || trim($message) === '' || mb_strlen($message) > 20000)
        {
            throw new \RuntimeException('Automatic reply text is invalid');
        }

        // Do not inherit the Joomla site-wide From address. It may be private.
        $publicAddress = SenderCopyHelper::publicAddress($settings);
        $publicName = trim((string) ($settings['copy_sender_name'] ?? '')) ?: 'Punga Contact Form';
        $mailOptions = new Registry([
            'mailfrom' => $publicAddress,
            'fromname' => $publicName,
            'throw_exceptions' => true,
        ]);
        $mailer = $factory->createMailer($mailOptions);
        $mailer->setSender($publicAddress, $publicName);
        $mailer->addRecipient($email, $name);
        $mailer->setSubject($heading);
        $mailer->setBody($message);
        $mailer->send();
    }
}
