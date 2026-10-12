<?php
/**
 * @file
 * @brief Verify alignment defaults and component/menu/module override precedence.
 */
namespace Joomla\Registry
{
    /** @brief Minimal Registry replacement for isolated layout-settings tests. */
    final class Registry
    {
        /** @param array<string, mixed> $values Simulated component or instance parameters. */
        public function __construct(private array $values = [])
        {
        }

        /**
         * @param string $key Configuration key.
         * @param mixed $default Default value.
         * @return mixed Configured value or default.
         */
        public function get(string $key, mixed $default = null): mixed
        {
            return $this->values[$key] ?? $default;
        }
    }
}

namespace Joomla\CMS\Component
{
    use Joomla\Registry\Registry;

    /** @brief Stands in for Joomla component options. */
    final class ComponentHelper
    {
        public static Registry $params;

        /** @param string $extension Component identifier. @return Registry Component parameters. */
        public static function getParams(string $extension): Registry
        {
            assert($extension === 'com_pungacontactform');
            return self::$params;
        }
    }
}

namespace Joomla\CMS\Language
{
    /** @brief Supplies harmless default language strings for settings tests. */
    final class Text
    {
        /** @param string $key Translation key. @return string A stable test translation. */
        public static function _(string $key): string
        {
            return $key;
        }
    }
}

namespace FormAlignmentTest
{
    use Joomla\CMS\Component\ComponentHelper;
    use Joomla\Registry\Registry;
    use Punga\Component\Pungacontactform\Site\Helper\FormHelper;

    \define('_JEXEC', 1);
    require \dirname(__DIR__) . '/component/site/src/Helper/FormHelper.php';

    /**
     * @param array<string, mixed> $global Component settings.
     * @param array<string, mixed>|null $overrides Menu/module settings, or null.
     * @return string Sanitized alignment.
     */
    function effectiveAlignment(array $global, ?array $overrides): string
    {
        ComponentHelper::$params = new Registry($global);
        $settings = FormHelper::settings($overrides === null ? null : new Registry($overrides));
        return $settings['form_alignment'];
    }

    assert(effectiveAlignment([], null) === 'left');
    assert(effectiveAlignment(['form_alignment' => 'left'], []) === 'left');
    assert(effectiveAlignment(['form_alignment' => 'centered'], null) === 'centered');
    assert(effectiveAlignment(['form_alignment' => 'centered'], []) === 'centered');
    assert(effectiveAlignment(['form_alignment' => 'centered'], ['form_alignment' => 'inherit']) === 'centered');
    assert(effectiveAlignment(['form_alignment' => 'centered'], ['form_alignment' => '']) === 'centered');
    assert(effectiveAlignment(['form_alignment' => 'centered'], ['form_alignment' => 'left']) === 'left');
    assert(effectiveAlignment(['form_alignment' => 'left'], ['form_alignment' => 'centered']) === 'centered');
    assert(effectiveAlignment(['form_alignment' => 'invalid'], null) === 'left');
    assert(effectiveAlignment([], ['form_alignment' => 'invalid']) === 'left');

    echo "PASS: 10 form-alignment default, inheritance and override scenarios\n";
}
