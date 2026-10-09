<?php
/**
 * @file
 * @brief Configures sender copies without revealing the private contact recipient.
 */
namespace Punga\Component\Pungacontactform\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\Registry\Registry;

/**
 * @brief Protects the private contact recipient from outgoing sender-copy headers.
 */
final class SenderCopyHelper
{
    /**
     * @brief Returns a safe, explicitly configured public From address.
     *
     * Never silently fall back to Joomla's global mail sender, which may be the
     * same personal mailbox as the contact-form recipient. The site-wide and
     * effective menu/module recipient addresses are both protected.
     *
     * @param array<string, mixed> $settings Server-side contact form settings.
     * @return string Valid public sender, or an empty string if copies are unsafe.
     */
    public static function publicAddress(array $settings): string
    {
        $address = trim((string) ($settings['copy_sender_email'] ?? ''));

        if (!filter_var($address, FILTER_VALIDATE_EMAIL))
        {
            return '';
        }

        foreach (['recipient', 'default_recipient'] as $key)
        {
            $privateAddress = trim((string) ($settings[$key] ?? ''));

            if ($privateAddress !== '' && strcasecmp($address, $privateAddress) === 0)
            {
                return '';
            }
        }

        return $address;
    }

    /**
     * @brief Checks whether the optional copy checkbox may be shown.
     * @param array<string, mixed> $settings Server-side contact form settings.
     * @return bool True if a public copy sender is configured and copies are enabled.
     */
    public static function available(array $settings): bool
    {
        return ($settings['copy_mode'] ?? 'off') !== 'off' && self::publicAddress($settings) !== '';
    }

    /**
     * @brief Sends a separate mail containing only public outgoing sender details.
     *
     * A per-message factory configuration overrides Joomla's possibly private
     * global From address BEFORE the mailer is constructed. This is important
     * for the sender header, SMTP envelope/return path, and older Joomla 6 mailers.
     * Transport authentication continues to use Joomla's mail configuration.
     *
     * @param MailerFactoryInterface $factory Joomla mailer factory.
     * @param array<string, mixed> $settings Server-only effective form settings.
     * @param string $visitorEmail Destination for the copy.
     * @param string $visitorName Visitor display name.
     * @param string $subject Original subject.
     * @param string $body Original plain-text message.
     * @return void
     * @throws \RuntimeException If no safe public sender is configured.
     */
    public static function send(
        MailerFactoryInterface $factory,
        array $settings,
        string $visitorEmail,
        string $visitorName,
        string $subject,
        string $body
    ): void
    {
        $address = self::publicAddress($settings);

        if ($address === '')
        {
            throw new \RuntimeException('Public contact-form sender is not configured safely');
        }

        $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) ($settings['copy_sender_name'] ?? '')) ?? '');

        if ($name === '')
        {
            $name = 'Punga Contact Form';
        }

        // Apply sender details at factory construction, not only via setSender().
        // This also protects the SMTP envelope sender/Return-Path as far as the
        // Joomla mail transport permits (a relay may rewrite its own headers).
        $mailOptions = new Registry([
            'mailfrom' => $address,
            'fromname' => $name,
            'throw_exceptions' => true,
        ]);
        $mailer = $factory->createMailer($mailOptions);
        $mailer->setSender($address, $name);
        $mailer->addRecipient($visitorEmail, $visitorName);
        $mailer->setSubject(Text::sprintf('COM_PUNGACONTACTFORM_COPY_SUBJECT', $subject));
        $mailer->setBody($body);
        $mailer->send();
    }
}
