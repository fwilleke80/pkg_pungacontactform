<?php
/**
 * @file
 * @brief Read-only spam catalog and classifier test entry inside Joomla Options.
 */
namespace Punga\Component\Pungacontactform\Administrator\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Punga\Component\Pungacontactform\Site\Helper\SpamHelper;

/** @brief Renders the actual rules used by SpamHelper, avoiding stale duplicates. */
final class SpamrulesField extends FormField
{
    protected $type = 'Spamrules';

    /** @inheritdoc */
    protected function getInput(): string
    {
        $esc = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $threshold = max(4, min(20, (int) ComponentHelper::getParams('com_pungacontactform')->get('spam_threshold', 8)));
        $html = '<div class="alert alert-info">' . Text::sprintf('COM_PUNGACONTACTFORM_RULES_EXPLANATION', $threshold) . '</div>';
        $html .= '<div class="table-responsive"><table class="table table-striped">';
        $html .= '<thead><tr><th>' . Text::_('COM_PUNGACONTACTFORM_CATEGORY') . '</th><th>' . Text::_('COM_PUNGACONTACTFORM_RULE') . '</th><th>' . Text::_('COM_PUNGACONTACTFORM_SCORE') . '</th></tr></thead><tbody>';
        foreach (SpamHelper::builtInRules() as $rule)
        {
            $html .= '<tr><td>' . $esc($rule['category']) . '</td><td>' . $esc($rule['description']) . '</td><td>+' . (int) $rule['points'] . '</td></tr>';
        }
        $html .= '<tr><td>' . Text::_('COM_PUNGACONTACTFORM_CUSTOM') . '</td><td>' . Text::_('COM_PUNGACONTACTFORM_CUSTOM_RULE') . '</td><td>+20</td></tr>';
        $html .= '<tr><td>' . Text::_('COM_PUNGACONTACTFORM_HEURISTICS') . '</td><td>' . Text::_('COM_PUNGACONTACTFORM_LINK_RULES') . '</td><td>+3–12</td></tr>';
        $html .= '<tr><td>' . Text::_('COM_PUNGACONTACTFORM_HEURISTICS') . '</td><td>' . Text::_('COM_PUNGACONTACTFORM_GIBBERISH_RULES') . '</td><td>+9–10</td></tr>';
        $html .= '</tbody></table></div>';
        $html .= '<p><a class="btn btn-outline-secondary" href="' . Route::_('index.php?option=com_pungacontactform&view=spamtest') . '">' . Text::_('COM_PUNGACONTACTFORM_SPAM_TEST') . '</a></p>';
        return $html;
    }
}
