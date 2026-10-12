<?php
/**
 * @file
 * @brief Test Joomla menu page heading and browser title behavior in isolation.
 */
namespace Joomla\Registry
{
    /** @brief Minimal Joomla Registry substitute. */
    final class Registry
    {
        /** @param array<string, mixed> $values Simulated Joomla parameters. */
        public function __construct(private array $values = [])
        {
        }

        /** @param string $name Parameter name. @param mixed $default Fallback. @return mixed Parameter value. */
        public function get(string $name, mixed $default = null): mixed
        {
            return $this->values[$name] ?? $default;
        }
    }
}

namespace Joomla\CMS\Language
{
    /** @brief Stand-in for Joomla's language catalog. */
    final class Text
    {
        /** @param string $name Key. @return string Localized fallback title. */
        public static function _(string $name): string
        {
            assert($name === 'COM_PUNGACONTACTFORM_MENU_TITLE');
            return 'Contact form';
        }
    }
}

namespace Joomla\CMS\Factory
{
}

namespace Joomla\CMS
{
    /** @brief Stand-in for Joomla's app factory. */
    final class Factory
    {
        public static object $app;

        /** @return object Joomla application stand-in. */
        public static function getApplication(): object
        {
            return self::$app;
        }
    }
}

namespace Joomla\CMS\MVC\View
{
    use Joomla\CMS\Factory;

    /** @brief Stand-in for the Joomla 6 HtmlView base class and document-title routine. */
    class HtmlView
    {
        public string $documentTitle = '';

        /**
         * @brief Simulates Joomla's built-in Global Configuration title composition.
         * @param string $title Menu/browser title.
         * @return void
         */
        public function setDocumentTitle($title): void
        {
            $app = Factory::getApplication();
            $site = $app->siteName;
            $setting = $app->siteNamePosition;
            $this->documentTitle = $title === '' ? $site
                : ($setting === 1 ? "$site - $title" : ($setting === 2 ? "$title - $site" : $title));
        }

        /** @param string $value Untrusted text. @return string Escaped HTML. */
        public function escape($value): string
        {
            return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        /** @param string|null $tpl Layout suffix. @return void */
        public function display($tpl = null): void
        {
            require \dirname(__DIR__, 1) . '/component/site/tmpl/contact/default.php';
        }
    }
}

namespace Punga\Component\Pungacontactform\Site\Helper
{
    use Joomla\Registry\Registry;

    /** @brief Isolates the view tests from mail/CAPTCHA/context behavior. */
    final class FormHelper
    {
        /** @param Registry|null $overrides Menu parameters. @return array<string, string> Alignment setting. */
        public static function settings(?Registry $overrides): array
        {
            return ['form_alignment' => (string) ($overrides?->get('form_alignment', 'left') ?? 'left')];
        }

        /** @param Registry|null $overrides Parameters. @param string $source Origin. @param int $sourceId Origin ID. @return string HTML stub. */
        public static function render(?Registry $overrides, string $source, int $sourceId): string
        {
            assert($source === 'menu');
            return '<div class="punga-contact-form" data-menu-id="' . $sourceId . '">FORM CONTENT</div>';
        }
    }
}

namespace PageDisplayTest
{
    use Joomla\CMS\Factory;
    use Joomla\Registry\Registry;
    use Punga\Component\Pungacontactform\Site\View\Contact\HtmlView;

    /** @brief A minimal menu item. */
    final class MenuItem
    {
        public string $component = 'com_pungacontactform';
        public int $id = 25;
        public string $title = 'Contact';

        /** @param array<string,mixed> $options Menu settings. */
        public function __construct(private array $options = [])
        {
        }

        /** @return Registry Menu overrides. */
        public function getParams(): Registry
        {
            return new Registry($this->options);
        }
    }

    /**
     * @brief Render the contact view under simulated Joomla menu/title settings.
     * @param array<string,mixed> $params Joomla merged component/menu params.
     * @param int $siteTitleMode Site-name prefix/suffix choice.
     * @param array<string,mixed> $overrides Instance form settings.
     * @param bool $hasMenu Whether a contact menu is active.
     * @return array{string,string,HtmlView} Browser title, HTML, view.
     */
    function render(array $params, int $siteTitleMode = 2, array $overrides = [], bool $hasMenu = true): array
    {
        $menu = $hasMenu ? new MenuItem($overrides) : null;
        Factory::$app = new class($params, $menu, $siteTitleMode)
        {
            public string $siteName = 'A Thousand Farts';

            /** @param array<string,mixed> $params Merger result. @param MenuItem|null $menu Menu entry. @param int $siteNamePosition Joomla title mode. */
            public function __construct(private array $params, private ?MenuItem $menu, public int $siteNamePosition)
            {
            }

            /** @return object Menu API. */
            public function getMenu(): object
            {
                return new class($this->menu)
                {
                    /** @param MenuItem|null $active Active menu item. */
                    public function __construct(private ?MenuItem $active)
                    {
                    }

                    /** @return MenuItem|null Active entry. */
                    public function getActive(): ?MenuItem
                    {
                        return $this->active;
                    }
                };
            }

            /** @param string $component Component ID. @return Registry Merged menu settings. */
            public function getParams(string $component): Registry
            {
                assert($component === 'com_pungacontactform');
                return new Registry($this->params);
            }
        };
        $view = new HtmlView();
        ob_start();
        $view->display();
        return [$view->documentTitle, (string) ob_get_clean(), $view];
    }

    \define('_JEXEC', 1);
    require \dirname(__DIR__) . '/component/site/src/View/Contact/HtmlView.php';

    [$title, $html] = render(['page_title' => 'Contact', 'page_heading' => 'Contact', 'show_page_heading' => 1]);
    assert($title === 'Contact - A Thousand Farts');
    assert(str_contains($html, '<h1>Contact</h1>'));
    assert(strpos($html, '<h1>') < strpos($html, 'FORM CONTENT'));
    assert(substr_count($html, '<h1>') === 1);

    [$title, $html] = render(['page_title' => 'Email Us', 'page_heading' => 'Contact', 'show_page_heading' => 1], 1);
    assert($title === 'A Thousand Farts - Email Us');
    assert(str_contains($html, '<h1>Contact</h1>'));

    [$title, $html] = render(['page_title' => 'Email Us', 'page_heading' => 'Contact', 'show_page_heading' => 0]);
    assert($title === 'Email Us - A Thousand Farts');
    assert(!str_contains($html, '<h1>'));

    [$title, $html] = render(['page_title' => '', 'page_heading' => '', 'show_page_heading' => 1]);
    assert($title === 'Contact - A Thousand Farts');
    assert(str_contains($html, '<h1>Contact</h1>'));

    [$title, $html] = render(['page_title' => 'Email Us', 'page_heading' => '', 'show_page_heading' => 1], 0);
    assert($title === 'Email Us');
    assert(str_contains($html, '<h1>Email Us</h1>'));

    [$title, $html] = render(['page_title' => 'Contact', 'page_heading' => '<script>alert(1)</script> & Contact', 'show_page_heading' => 1]);
    assert(str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt; &amp; Contact'));
    assert(!str_contains($html, '<script>'));

    [$title, $html] = render(['page_title' => 'Contact', 'page_heading' => 'Contact', 'show_page_heading' => 1], 2, ['form_alignment' => 'centered']);
    assert(str_contains($html, 'punga-contact-page pcf-layout-centered'));
    assert(str_contains($html, '<h1>Contact</h1>'));

    [$title, $html] = render(['page_title' => '', 'page_heading' => '', 'show_page_heading' => 1], 2, [], false);
    assert($title === 'Contact form - A Thousand Farts');
    assert(str_contains($html, '<h1>Contact form</h1>'));
    assert(str_contains($html, 'data-menu-id="0"'));

    echo "PASS: 8 page-title, heading visibility/fallback, escaping, and alignment scenarios\n";
}
