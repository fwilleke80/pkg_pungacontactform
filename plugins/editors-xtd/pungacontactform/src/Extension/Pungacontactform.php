<?php
/**
 * @file
 * @brief Joomla editor button for inserting contact-form placeholders.
 */
namespace Punga\Plugin\EditorsXtd\Pungacontactform\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Editor\Button\Button;
use Joomla\CMS\Event\Editor\EditorButtonsSetupEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\SubscriberInterface;

/** @brief Adds a contact form placeholder builder to supported Joomla editors. */
final class Pungacontactform extends CMSPlugin implements SubscriberInterface
{
    /** @var bool Load plugin translations automatically. */
    protected $autoloadLanguage = true;

    /**
     * @brief Joomla 6 editor button event subscription.
     * @return array<string, string> Subscriber methods.
     */
    public static function getSubscribedEvents(): array
    {
        return ['onEditorButtonsSetup' => 'setup'];
    }

    /**
     * @brief Registers the editor button and its native editor API action.
     * @param EditorButtonsSetupEvent $event Joomla editor initialization.
     * @return void
     */
    public function setup(EditorButtonsSetupEvent $event): void
    {
        if (in_array($this->_name, $event->getDisabledButtons(), true))
        {
            return;
        }

        $app = $this->getApplication();
        if (!$app->isClient('administrator') && !$app->isClient('site'))
        {
            return;
        }
        $this->loadLanguage();

        $modules = [];
        try
        {
            $db = $app->getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select([$db->quoteName('id'), $db->quoteName('title'), $db->quoteName('access')])
                ->from($db->quoteName('#__modules'))
                ->where($db->quoteName('module') . ' = ' . $db->quote('mod_pungacontactform'))
                ->where($db->quoteName('client_id') . ' = 0')
                ->where($db->quoteName('published') . ' = 1')
                ->order($db->quoteName('title') . ' ASC');
            $rows = $db->setQuery($query)->loadObjectList() ?: [];
            $levels = $app->getIdentity()->getAuthorisedViewLevels();
            foreach ($rows as $row)
            {
                if (in_array((int) $row->access, $levels, true))
                {
                    $modules[] = ['id' => (int) $row->id, 'title' => (string) $row->title];
                }
            }
        }
        catch (\Throwable)
        {
            // The default placeholder remains usable without configured modules.
        }

        $document = $app->getDocument();
        $wa = $document->getWebAssetManager();
        if (!$wa->assetExists('script', 'editor-button.pungacontactform'))
        {
            $wa->registerScript(
                'editor-button.pungacontactform',
                'plg_editors-xtd_pungacontactform/js/editor.js',
                [],
                ['type' => 'module'],
                ['editors']
            );
        }
        $document->addScriptOptions('plg_editors-xtd_pungacontactform', [
            'modules' => $modules,
            'strings' => [
                'title' => Text::_('PLG_EDITORS-XTD_PUNGACONTACTFORM_BUTTON'),
                'source' => Text::_('PLG_EDITORS-XTD_PUNGACONTACTFORM_MODULE'),
                'default' => Text::_('PLG_EDITORS-XTD_PUNGACONTACTFORM_DEFAULT'),
                'layout' => Text::_('PLG_EDITORS-XTD_PUNGACONTACTFORM_LAYOUT'),
                'inherit' => Text::_('PLG_EDITORS-XTD_PUNGACONTACTFORM_INHERIT'),
                'left' => Text::_('PLG_EDITORS-XTD_PUNGACONTACTFORM_LEFT'),
                'centered' => Text::_('PLG_EDITORS-XTD_PUNGACONTACTFORM_CENTERED'),
                'intro' => Text::_('PLG_EDITORS-XTD_PUNGACONTACTFORM_INTRO'),
                'yes' => Text::_('JYES'),
                'no' => Text::_('JNO'),
                'insert' => Text::_('PLG_EDITORS-XTD_PUNGACONTACTFORM_INSERT'),
                'cancel' => Text::_('JCANCEL'),
            ],
        ]);

        $event->getButtonsRegistry()->add(new Button('pungacontactform', [
            'action' => 'insert-pungacontactform',
            'text' => Text::_('PLG_EDITORS-XTD_PUNGACONTACTFORM_BUTTON'),
            'icon' => 'envelope',
            'name' => $this->_type . '_' . $this->_name,
        ]));
    }
}
