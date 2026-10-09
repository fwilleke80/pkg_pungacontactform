<?php
/**
 * @file
 * @brief CLI regression for independent metadata storage and message-body retention.
 */
namespace
{
    \define('_JEXEC', 1);
    if (!function_exists('mb_substr'))
    {
        /** @brief ASCII-compatible fallback solely for this CLI test fixture. */
        function mb_substr(string $value, int $start, int $length): string
        {
            return substr($value, $start, $length);
        }
    }

    final class FakeParams
    {
        /** @param array<string,int> $settings */
        public function __construct(public array $settings)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->settings[$key] ?? $default;
        }
    }

    final class FakeQuery
    {
        public function update(string $table): self
        {
            return $this;
        }

        public function delete(string $table): self
        {
            return $this;
        }

        public function set(string $expression): self
        {
            return $this;
        }

        public function where(string $expression): self
        {
            return $this;
        }
    }

    final class FakeDb
    {
        /** @var array<int,object> */
        public array $rows = [];
        public int $counterUpdates = 0;

        public function insertObject(string $table, object $row): void
        {
            if (str_contains($table, 'messages'))
            {
                $this->rows[] = $row;
            }
        }

        public function getQuery(bool $new): FakeQuery
        {
            return new FakeQuery();
        }

        public function quoteName(string $name): string
        {
            return $name;
        }

        public function quote(string $value): string
        {
            return "'" . $value . "'";
        }

        public function setQuery(FakeQuery $query): self
        {
            return $this;
        }

        public function execute(): void
        {
            $this->counterUpdates++;
        }

        public function getAffectedRows(): int
        {
            return 1;
        }
    }

    final class FakeSession
    {
        public function get(string $name, mixed $default = null): mixed
        {
            return time();
        }
    }

    final class FakeApp
    {
        public function getSession(): FakeSession
        {
            return new FakeSession();
        }
    }

    final class FakeContainer
    {
        public function __construct(public FakeDb $db)
        {
        }

        public function get(string $name): FakeDb
        {
            return $this->db;
        }
    }
}

namespace Joomla\CMS\Component
{
    final class ComponentHelper
    {
        public static \FakeParams $params;

        public static function getParams(string $component): \FakeParams
        {
            return self::$params;
        }
    }
}

namespace Joomla\CMS
{
    final class Factory
    {
        public static \FakeContainer $container;

        public static function getContainer(): \FakeContainer
        {
            return self::$container;
        }

        public static function getApplication(): \FakeApp
        {
            return new \FakeApp();
        }
    }
}

namespace Joomla\CMS\Log
{
    final class Log
    {
        public const WARNING = 2;

        public static function add(string $message, int $severity, string $category): void
        {
            throw new \RuntimeException('Unexpected persistence failure: ' . $message);
        }
    }
}

namespace
{
    require __DIR__ . '/../component/site/src/Helper/MessageStoreHelper.php';

    use Joomla\CMS\Component\ComponentHelper;
    use Joomla\CMS\Factory;
    use Punga\Component\Pungacontactform\Site\Helper\MessageStoreHelper;

    ComponentHelper::$params = new FakeParams(['store_sent'=>0, 'store_rejected'=>1, 'store_failed'=>1, 'retention_days'=>30]);
    Factory::$container = new FakeContainer(new FakeDb());

    $data = ['name'=>'A Sender', 'email'=>'sender@example.org', 'subject'=>'A Subject', 'body'=>'Personal body'];
    MessageStoreHelper::record('sent', '', 'menu:42', true, $data, ['score'=>0, 'threshold'=>8, 'matches'=>[]]);
    $rows = Factory::$container->db->rows;
    \assert(count($rows) === 1 && $rows[0]->subject === 'A Subject');
    \assert($rows[0]->body === '' && $rows[0]->sender_email === 'sender@example.org');
    \assert($rows[0]->source_kind === 'menu' && $rows[0]->source_id === 42);

    ComponentHelper::$params->settings['store_sent'] = 1;
    MessageStoreHelper::record('sent', '', 'module:5', false, $data);
    \assert(Factory::$container->db->rows[1]->body === 'Personal body');

    MessageStoreHelper::record('rejected', 'content_filter', 'menu:42', true, $data, ['score'=>12, 'threshold'=>8, 'matches'=>[['id'=>'link_only', 'description'=>'URL', 'points'=>12]]]);
    \assert(Factory::$container->db->rows[2]->body === 'Personal body');
    \assert(str_contains(Factory::$container->db->rows[2]->spam_details, 'link_only'));

    ComponentHelper::$params->settings['store_rejected'] = 0;
    MessageStoreHelper::record('rejected', 'content_filter', 'menu:42', true, $data);
    \assert(Factory::$container->db->rows[3]->body === '');
    \assert(Factory::$container->db->rows[3]->subject === 'A Subject');

    MessageStoreHelper::record('rejected', 'captcha', 'module:5', true);
    \assert(Factory::$container->db->rows[4]->body === '');
    \assert(Factory::$container->db->rows[4]->sender_email === '');

    ComponentHelper::$params->settings['store_failed'] = 0;
    MessageStoreHelper::record('failed', 'mail_transport', 'menu:42', true, $data);
    \assert(Factory::$container->db->rows[5]->body === '');
    \assert(Factory::$container->db->rows[5]->sender_name === 'A Sender');

    if (count(Factory::$container->db->rows) !== 6 || Factory::$container->db->counterUpdates !== 6)
    {
        throw new \RuntimeException('Expected six history records and counter increments.');
    }
    echo "OK: 6 metadata/body-retention and counter regression scenarios\n";
}
