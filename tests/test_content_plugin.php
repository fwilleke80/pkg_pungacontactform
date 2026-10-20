<?php
/** @file @brief Isolated placeholder parser checks. */
namespace Joomla\CMS\Plugin
{
    class CMSPlugin
    {
    }
}
namespace Joomla\Event
{
    interface SubscriberInterface
    {
        public static function getSubscribedEvents(): array;
    }
}
namespace
{
    define('_JEXEC', 1);
    require __DIR__ . '/../plugins/content/pungacontactform/src/Extension/Pungacontactform.php';
    use Punga\Plugin\Content\Pungacontactform\Extension\Pungacontactform;

    $cases = [
        ['', []],
        ['module=123', ['module' => '123']],
        ['layout=centered intro=no', ['layout' => 'centered', 'intro' => 'no']],
        ['module=23 layout=left intro=yes', ['module' => '23', 'layout' => 'left', 'intro' => 'yes']],
        ['module=0', null],
        ['module=-3', null],
        ['module=1 module=2', null],
        ['layout=centered layout=left', null],
        ['recipient=private@example.com', null],
        ['captcha=off', null],
        ['spam_filter_mode=off', null],
        ['module=1;drop', null],
        ['module=123 layout=evil', null],
        ['intro=maybe', null],
        ['bogus=test', null],
    ];
    foreach ($cases as [$input, $expected])
    {
        $result = Pungacontactform::parseOptions($input);
        assert($result === $expected, 'Incorrect placeholder parsing for: ' . $input);
    }
    echo 'PASS: ' . count($cases) . ' safe content placeholder parsing scenarios' . PHP_EOL;
}
