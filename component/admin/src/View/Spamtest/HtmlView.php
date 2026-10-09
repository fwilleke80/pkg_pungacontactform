<?php
/** @file @brief Administrator-only, transient classifier test view. */
namespace Punga\Component\Pungacontactform\Administrator\View\Spamtest;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Punga\Component\Pungacontactform\Site\Helper\SpamHelper;

/** @brief Simulates classification without storing or dispatching mail. */
final class HtmlView extends BaseHtmlView
{
    public string $subject = '';
    public string $body = '';
    public ?array $result = null;
    public int $threshold = 8;

    /** @inheritdoc */
    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        if (!$app->getIdentity()->authorise('core.manage', 'com_pungacontactform'))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $params = ComponentHelper::getParams('com_pungacontactform');
        $this->threshold = max(4, min(20, (int) $params->get('spam_threshold', 8)));
        if ($app->getInput()->getMethod() === 'POST')
        {
            if (!Session::checkToken('post'))
            {
                throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
            }
            $this->subject = mb_substr((string) $app->getInput()->post->get('subject', '', 'string'), 0, 200);
            $this->body = mb_substr((string) $app->getInput()->post->get('body', '', 'string'), 0, 20000);
            $this->result = SpamHelper::analyzeDetailed($this->subject, $this->body, (string) $params->get('spam_keywords', ''));
        }
        ToolbarHelper::title(Text::_('COM_PUNGACONTACTFORM_SPAM_TEST'), 'shield');
        parent::display($tpl);
    }
}
