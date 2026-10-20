<?php
/** @file @brief Isolated automatic reply validation and sender isolation. */
namespace Joomla\CMS\Mail
{
    interface MailerFactoryInterface
    {
        public function createMailer($options = null);
    }
}
namespace Joomla\Registry
{
    class Registry
    {
        /** @param array<string, mixed> $values Options. */
        public function __construct(public array $values = [])
        {
        }
    }
}
namespace Joomla\CMS
{
    class Factory
    {
        public static function getApplication(): object
        {
            return new class
            {
                public function get(string $key, string $default = ''): string
                {
                    return $key === 'sitename' ? 'My Site' : $default;
                }
            };
        }
    }
}
namespace Punga\Component\Pungacontactform\Site\Helper
{
    /** @brief Lightweight mbstring fallback for environments without the Joomla PHP requirements. */
    function mb_strlen(string $text): int
    {
        return strlen($text);
    }
    class SenderCopyHelper
    {
        /** @param array<string, mixed> $settings Server-side settings. */
        public static function publicAddress(array $settings): string
        {
            $public = (string) ($settings['copy_sender_email'] ?? '');
            return filter_var($public, FILTER_VALIDATE_EMAIL)
                && $public !== ($settings['recipient'] ?? '')
                && $public !== ($settings['default_recipient'] ?? '') ? $public : '';
        }
    }
}
namespace
{
    define('_JEXEC', 1);
    require __DIR__ . '/../component/site/src/Helper/AutomaticReplyHelper.php';

    use Joomla\CMS\Mail\MailerFactoryInterface;
    use Punga\Component\Pungacontactform\Site\Helper\AutomaticReplyHelper;

    class TestMailer
    {
        /** @var array<string,mixed> Recorded mail details. */
        public array $values = [];
        public function setSender(string $address, string $name): void
        {
            $this->values['from'] = $address;
        }
        public function addRecipient(string $address, string $name): void
        {
            $this->values['to'] = $address;
        }
        public function setSubject(string $subject): void
        {
            $this->values['subject'] = $subject;
        }
        public function setBody(string $body): void
        {
            $this->values['body'] = $body;
        }
        public function send(): void
        {
            $this->values['sent'] = true;
        }
    }
    class TestFactory implements MailerFactoryInterface
    {
        public ?TestMailer $mailer = null;
        public ?\Joomla\Registry\Registry $options = null;
        public function createMailer($options = null): TestMailer
        {
            $this->options = $options;
            $this->mailer = new TestMailer();
            return $this->mailer;
        }
    }
    $settings = [
        'recipient' => 'private@example.com',
        'default_recipient' => 'private@example.com',
        'copy_sender_email' => 'noreply@example.org',
        'copy_sender_name' => 'Public Site',
        'auto_reply_mode' => 'on',
        'auto_reply_subject' => 'Received: {subject}',
        'auto_reply_body' => 'Hello {name}, thank you for contacting {site_name}.',
    ];
    assert(AutomaticReplyHelper::available($settings));
    assert(AutomaticReplyHelper::expand('Hi {name}', 'Pat', 'Test', 'Site') === 'Hi Pat');
    $factory = new TestFactory();
    AutomaticReplyHelper::send($factory, $settings, 'visitor@example.net', 'Pat', 'Hello');
    assert($factory->options->values['mailfrom'] === 'noreply@example.org');
    assert($factory->mailer->values['from'] === 'noreply@example.org');
    assert($factory->mailer->values['to'] === 'visitor@example.net');
    assert($factory->mailer->values['subject'] === 'Received: Hello');
    assert($factory->mailer->values['body'] === 'Hello Pat, thank you for contacting My Site.');
    assert(!str_contains(implode(' ', $factory->mailer->values), 'private@example.com'));
    assert($factory->mailer->values['sent'] === true);
    $settings['copy_sender_email'] = 'private@example.com';
    assert(!AutomaticReplyHelper::available($settings));
    $settings['copy_sender_email'] = 'noreply@example.org';
    $settings['auto_reply_mode'] = 'off';
    assert(!AutomaticReplyHelper::available($settings));
    $settings['auto_reply_mode'] = 'on';
    $settings['auto_reply_body'] = '';
    assert(!AutomaticReplyHelper::available($settings));
    echo 'PASS: privacy-safe acknowledgements, placeholders, required configuration and disabled mode' . PHP_EOL;
}
