<?php
/**
 * @file
 * @brief Independent regression for message retention and atomic daily counters.
 */
namespace
{
    \define('_JEXEC', 1);
    if (!function_exists('mb_substr'))
    {
        /** @brief ASCII-compatible fallback for an isolated PHP test runner. */
        function mb_substr(string $text, int $start, int $length): string
        {
            return substr($text, $start, $length);
        }
    }

    /** @brief Lightweight fake Joomla parameters. */
    final class FakeParams
    {
        /** @param array<string,int> $settings */
        public function __construct(public array $settings)
        {
        }

        /** @return mixed */
        public function get(string $key, mixed $default = null): mixed
        {
            return $this->settings[$key] ?? $default;
        }
    }

    /** @brief Captures portable Joomla query-builder grouping. */
    final class FakeQuery
    {
        public string $table = '';
        public array $groups = [];

        public function select(string|array $value): self
        {
            return $this;
        }

        public function from(string $table): self
        {
            $this->table = $table;
            return $this;
        }

        public function group(string $expression): self
        {
            $this->groups[] = $expression;
            return $this;
        }

        public function delete(string $table): self
        {
            $this->table = $table;
            return $this;
        }

        public function where(string $expression): self
        {
            return $this;
        }
    }
}

namespace Joomla\Database
{
    /** @brief Interface-only fixture for the production database signature. */
    interface DatabaseInterface
    {
    }
}

namespace
{
    /** @brief Emulates unique-key upserts on either supported SQL database family. */
    final class FakeDb implements \Joomla\Database\DatabaseInterface
    {
        /** @var array<int,object> */
        public array $rows = [];
        /** @var array<string,int> */
        public array $counts = [];
        public int $counterUpdates = 0;
        /** @var array<int,string> */
        public array $sql = [];
        public bool $failCounter = false;
        private FakeQuery|string|null $query = null;
        private ?array $transactionSnapshot = null;
        public int $rollbacks = 0;
        public int $commits = 0;

        public function __construct(public string $serverType = 'mysql')
        {
        }

        public function transactionStart(): void
        {
            if ($this->transactionSnapshot !== null)
            {
                throw new \RuntimeException('Unexpected nested transaction');
            }
            $this->transactionSnapshot = ['rows'=>$this->rows, 'counts'=>$this->counts];
        }

        public function transactionCommit(): void
        {
            $this->transactionSnapshot = null;
            $this->commits++;
        }

        public function transactionRollback(): void
        {
            if ($this->transactionSnapshot === null)
            {
                throw new \RuntimeException('No active transaction');
            }
            $this->rows = $this->transactionSnapshot['rows'];
            $this->counts = $this->transactionSnapshot['counts'];
            $this->transactionSnapshot = null;
            $this->rollbacks++;
        }

        public function getServerType(): string
        {
            return $this->serverType;
        }

        public function insertObject(string $table, object $row): void
        {
            if (str_contains($table, 'messages'))
            {
                $this->rows[] = $row;
            }
            else
            {
                throw new \RuntimeException('Unexpected non-atomic insert');
            }
        }

        public function getQuery(bool $new): FakeQuery
        {
            return new FakeQuery();
        }

        public function quoteName(string|array $name): string|array
        {
            if (is_array($name))
            {
                return array_map(fn (string $value): string => $this->quoteName($value), $name);
            }
            $quote = in_array($this->serverType, ['mysql', 'mariadb'], true) ? '`' : '"';
            return $quote . $name . $quote;
        }

        public function quote(string $value): string
        {
            return "'" . str_replace("'", "''", $value) . "'";
        }

        public function setQuery(FakeQuery|string $query): self
        {
            $this->query = $query;
            return $this;
        }

        /** @return array<int,object> */
        public function loadObjectList(): array
        {
            if (!$this->query instanceof FakeQuery)
            {
                throw new \RuntimeException('Expected Joomla builder SELECT');
            }
            if (str_contains($this->query->table, 'messages'))
            {
                $grouped = [];
                foreach ($this->rows as $row)
                {
                    $date = substr($row->created_at, 0, 10);
                    $key = $date . ':' . $row->status;
                    $grouped[$key] = ($grouped[$key] ?? 0) + 1;
                }
                $out = [];
                foreach ($grouped as $key => $value)
                {
                    [$date, $status] = explode(':', $key);
                    $out[] = (object) ['day'=>$date, 'status'=>$status, 'total'=>$value];
                }
                return $out;
            }
            if (str_contains($this->query->table, 'counts'))
            {
                $out = [];
                foreach ($this->counts as $key => $total)
                {
                    [$date, $status] = explode(':', $key);
                    $out[] = (object) ['day'=>$date, 'status'=>$status, 'total'=>$total];
                }
                return $out;
            }
            throw new \RuntimeException('Unexpected SELECT table');
        }

        public function execute(): void
        {
            if (!is_string($this->query))
            {
                throw new \RuntimeException('Expected an atomic upsert statement');
            }
            $sql = $this->query;
            $this->sql[] = $sql;
            if ($this->failCounter)
            {
                throw new \RuntimeException('Simulated counter persistence failure');
            }
            $isMysql = in_array($this->serverType, ['mysql', 'mariadb'], true);
            if ($isMysql && !str_contains($sql, 'ON DUPLICATE KEY UPDATE'))
            {
                throw new \RuntimeException('MySQL upsert missing');
            }
            if (!$isMysql && !str_contains($sql, 'ON CONFLICT'))
            {
                throw new \RuntimeException('PostgreSQL upsert missing');
            }
            if (!preg_match("/VALUES \\(\\s*'([0-9-]+)',\\s*'(sent|rejected|failed)',\\s*([0-9]+)\\s*\\)/", $sql, $match))
            {
                throw new \RuntimeException('Malformed upsert: ' . $sql);
            }
            $key = $match[1] . ':' . $match[2];
            $value = (int) $match[3];
            if (!isset($this->counts[$key]))
            {
                $this->counts[$key] = $value;
            }
            elseif (str_contains($sql, 'GREATEST('))
            {
                $this->counts[$key] = max($this->counts[$key], $value);
            }
            else
            {
                $this->counts[$key] += $value;
            }
            $this->counterUpdates++;
        }
    }

    final class FakeSession
    {
        public function get(string $name, mixed $default = null): mixed
        {
            return time(); // Suppress cleanup for deterministic tests.
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
        public static array $warnings = [];

        public static function add(string $message, int $severity, string $category): void
        {
            self::$warnings[] = $message;
        }
    }
}

namespace
{
    require __DIR__ . '/../component/site/src/Helper/CounterHelper.php';
    require __DIR__ . '/../component/site/src/Helper/MessageStoreHelper.php';

    use Joomla\CMS\Component\ComponentHelper;
    use Joomla\CMS\Factory;
    use Joomla\CMS\Log\Log;
    use Punga\Component\Pungacontactform\Site\Helper\CounterHelper;
    use Punga\Component\Pungacontactform\Site\Helper\MessageStoreHelper;

    /** @brief Abort the test with a clear assertion message. */
    function check(bool $ok, string $message): void
    {
        if (!$ok)
        {
            throw new \RuntimeException($message);
        }
    }

    ComponentHelper::$params = new FakeParams(['store_sent'=>0, 'store_rejected'=>1, 'store_failed'=>1, 'retention_days'=>30]);
    $data = ['name'=>'A Sender', 'email'=>'sender@example.org', 'subject'=>'A Subject', 'body'=>'Personal body'];

    foreach (['mysql', 'mariadb', 'postgresql'] as $driver)
    {
        ComponentHelper::$params->settings = ['store_sent'=>0, 'store_rejected'=>1, 'store_failed'=>1, 'retention_days'=>30];
        $db = new FakeDb($driver);
        Factory::$container = new FakeContainer($db);
        MessageStoreHelper::record('sent', '', 'menu:42', true, $data, ['score'=>0, 'threshold'=>8, 'matches'=>[]]);
        check(count($db->rows) === 1 && $db->rows[0]->subject === 'A Subject', 'First history record not persisted');
        check($db->rows[0]->body === '' && $db->rows[0]->source_id === 42, 'First record privacy/source regression');
        check($db->counts[gmdate('Y-m-d') . ':sent'] === 1, 'First ever submission counter not created');

        ComponentHelper::$params->settings['store_sent'] = 1;
        MessageStoreHelper::record('sent', '', 'module:5', false, $data);
        check($db->rows[1]->body === 'Personal body', 'Sender body should be retained when opted in');
        check($db->counts[gmdate('Y-m-d') . ':sent'] === 2, 'Repeated sent counter did not increment');

        MessageStoreHelper::record('rejected', 'content_filter', 'menu:42', true, $data, ['score'=>12, 'threshold'=>8, 'matches'=>[['id'=>'link_only']]]);
        check(str_contains($db->rows[2]->spam_details, 'link_only'), 'Spam reasons missing');
        check($db->counts[gmdate('Y-m-d') . ':rejected'] === 1, 'First rejected count incorrect');
        ComponentHelper::$params->settings['store_rejected'] = 0;
        MessageStoreHelper::record('rejected', 'content_filter', 'menu:42', true, $data);
        check($db->rows[3]->body === '' && $db->rows[3]->subject === 'A Subject', 'Rejected body switch affected metadata');
        MessageStoreHelper::record('rejected', 'captcha', 'module:5', true);
        check($db->rows[4]->sender_email === '', 'Captcha failure must not store invalid input');
        ComponentHelper::$params->settings['store_failed'] = 0;
        MessageStoreHelper::record('failed', 'mail_transport', 'menu:42', true, $data);
        check($db->rows[5]->body === '', 'Failed body switch violated');
        check(count($db->rows) === 6 && $db->counterUpdates === 6 && $db->commits === 6, 'Expected six atomic history/counter transactions');
        check($db->counts[gmdate('Y-m-d') . ':rejected'] === 3, 'Rejected total incorrect');

        // Concurrent submissions in production execute the same UNIQUE-key
        // upsert atomically. Simulate many interleaved invocations to the
        // database statement rather than a prior SELECT/UPDATE/INSERT sequence.
        for ($i = 0; $i < 40; $i++)
        {
            CounterHelper::increment($db, '2026-10-19', 'sent');
        }
        check($db->counts['2026-10-19:sent'] === 40, 'Concurrent-style increments were lost');

        // Reconcile missing counter data from retained historical messages.
        unset($db->counts[gmdate('Y-m-d') . ':sent']);
        check(CounterHelper::reconcileRetainedHistory($db) === 1, 'Missing daily count was not detected');
        check($db->counts[gmdate('Y-m-d') . ':sent'] === 2, 'Reconciliation did not restore sent total');
        check(CounterHelper::reconcileRetainedHistory($db) === 0, 'Reconciliation is not idempotent');

        // Retention/deletion must never decrease a lifetime total.
        $db->counts[gmdate('Y-m-d') . ':sent'] = 20;
        array_pop($db->rows);
        check(CounterHelper::reconcileRetainedHistory($db) === 0, 'Reconciliation reduced a historical count');
        check($db->counts[gmdate('Y-m-d') . ':sent'] === 20, 'Historical counter was lowered');
        echo "OK: {$driver} atomic first/repeated/concurrent-style counters and reconciliation\n";
    }

    // Diagnostics must never print submitted PII, nor turn successful mail
    // submission into an error if the independent statistics write fails.
    $db = new FakeDb('mysql');
    $db->failCounter = true;
    Factory::$container = new FakeContainer($db);
    MessageStoreHelper::record('sent', '', 'menu:42', true, $data);
    check(count($db->rows) === 1 && $db->rollbacks === 1, 'Counter failure must roll back and fall back to exactly one history record');
    check(count(Log::$warnings) === 1, 'Counter failure was not recorded for diagnostics');
    check(!str_contains(Log::$warnings[0], $data['email']) && !str_contains(Log::$warnings[0], $data['body']),
        'Counter error log leaked submission details');

    try
    {
        CounterHelper::increment(new FakeDb('sqlite'), '2026-10-19', 'sent');
        throw new \RuntimeException('Unsupported database accepted');
    }
    catch (\RuntimeException $e)
    {
        check(str_contains($e->getMessage(), 'Unsupported'), 'Unexpected unsupported driver outcome');
    }
    echo "OK: 6 storage scenarios, first inserts, safe repair, failure logging and backend SQL variants\n";
}
