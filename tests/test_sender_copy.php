<?php
/**
 * @file
 * @brief Isolated sender-copy regression tests: a private mailbox must never leak.
 */
namespace Joomla\Registry
{
    class Registry
    {
        /** @var array<string, mixed> */
        public array $data;

        /**
         * @param array<string, mixed> $data Mocked Joomla factory overrides.
         */
        public function __construct(array $data = [])
        {
            $this->data = $data;
        }
    }
}

namespace Joomla\CMS\Language
{
    class Text
    {
        /**
         * @param string $key Translation key.
         * @param string $subject Original subject.
         * @return string Translated copy subject (mock).
         */
        public static function sprintf(string $key, string $subject): string
        {
            return 'Copy: ' . $subject;
        }
    }
}

namespace Joomla\CMS\Mail
{
    use Joomla\Registry\Registry;

    interface MailerFactoryInterface
    {
        /** @return object The mock mailer. */
        public function createMailer(?Registry $settings = null): object;
    }
}

namespace SenderCopyTest
{
    use Joomla\CMS\Mail\MailerFactoryInterface;
    use Joomla\Registry\Registry;
    use Punga\Component\Pungacontactform\Site\Helper\SenderCopyHelper;

    \define('_JEXEC', 1);
    require \dirname(__DIR__) . '/component/site/src/Helper/SenderCopyHelper.php';

    final class FakeMailer
    {
        public string $from = '';
        public string $fromName = '';
        /** @var array<array{string,string}> */
        public array $to = [];
        public string $subject = '';
        public string $body = '';
        public bool $sent = false;

        /** @param string $from Public sender address. @param string $name Public name. */
        public function setSender(string $from, string $name = ''): void
        {
            $this->from = $from;
            $this->fromName = $name;
        }

        /** @param string $email Recipient. @param string $name Recipient name. */
        public function addRecipient(string $email, string $name = ''): void
        {
            $this->to[] = [$email, $name];
        }

        /** @param string $subject Subject. */
        public function setSubject(string $subject): void
        {
            $this->subject = $subject;
        }

        /** @param string $body Body. */
        public function setBody(string $body): void
        {
            $this->body = $body;
        }

        /** @return void */
        public function send(): void
        {
            $this->sent = true;
        }
    }

    final class FakeFactory implements MailerFactoryInterface
    {
        /** @var list<Registry> */
        public array $options = [];
        /** @var list<FakeMailer> */
        public array $mailers = [];

        /** @return FakeMailer */
        public function createMailer(?Registry $settings = null): object
        {
            assert($settings !== null);
            $this->options[] = $settings;
            $mailer = new FakeMailer();
            $this->mailers[] = $mailer;
            return $mailer;
        }
    }

    $private = 'private-owner@example.invalid';
    $visitor = 'visitor@example.net';
    $settings = [
        'recipient' => $private,
        'default_recipient' => $private,
        'copy_mode' => 'optional',
        'copy_sender_email' => '',
        'copy_sender_name' => 'Contact form',
    ];
    assert(SenderCopyHelper::publicAddress($settings) === '');
    assert(!SenderCopyHelper::available($settings));

    $factory = new FakeFactory();
    try
    {
        SenderCopyHelper::send($factory, $settings, $visitor, 'Visitor', 'Test', 'Hello');
        assert(false, 'An unconfigured sender must be rejected');
    }
    catch (\RuntimeException)
    {
        assert(count($factory->mailers) === 0);
    }

    $settings['copy_sender_email'] = 'PRIVATE-owner@EXAMPLE.invalid';
    assert(SenderCopyHelper::publicAddress($settings) === '');
    assert(!SenderCopyHelper::available($settings));

    // Menu override must not permit the global private address to leak.
    $settings['recipient'] = 'different-private@example.invalid';
    assert(SenderCopyHelper::publicAddress($settings) === '');
    $settings['copy_sender_email'] = 'different-private@example.invalid';
    assert(SenderCopyHelper::publicAddress($settings) === '');

    $settings['copy_sender_email'] = 'contact@public.example';
    assert(SenderCopyHelper::publicAddress($settings) === 'contact@public.example');
    assert(SenderCopyHelper::available($settings));
    $settings['copy_mode'] = 'off';
    assert(!SenderCopyHelper::available($settings));
    $settings['copy_mode'] = 'optional';
    SenderCopyHelper::send($factory, $settings, $visitor, 'Visitor', 'Test subject', 'Hello world');
    assert(count($factory->mailers) === 1);
    $mailer = $factory->mailers[0];
    assert($mailer->sent);
    assert($mailer->from === 'contact@public.example');
    assert($mailer->fromName === 'Contact form');
    assert($mailer->to === [[$visitor, 'Visitor']]);
    assert($mailer->subject === 'Copy: Test subject');
    assert($mailer->body === 'Hello world');
    assert($factory->options[0]->data['mailfrom'] === 'contact@public.example');
    assert($factory->options[0]->data['fromname'] === 'Contact form');
    assert($factory->options[0]->data['throw_exceptions'] === true);
    assert(!str_contains(serialize([$factory->options[0]->data, $mailer]), $private));
    assert(!str_contains(serialize([$factory->options[0]->data, $mailer]), 'different-private@example.invalid'));

    $settings['copy_sender_name'] = '';
    SenderCopyHelper::send($factory, $settings, $visitor, 'Visitor', 'Test', 'Body');
    assert($factory->mailers[1]->fromName === 'Punga Contact Form');

    echo "PASS: public sender isolation, global/override privacy and copy transport configuration (13 cases)\n";
}
