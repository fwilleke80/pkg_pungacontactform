<?php
/** @file @brief Safely escaped message details and stored scoring contributions. */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$item = $this->item;
$esc = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$matches = json_decode((string) $item->spam_details, true);
$matches = is_array($matches) ? array_slice($matches, 0, 100) : [];
?>
<p><a href="<?php echo Route::_('index.php?option=com_pungacontactform&view=messages'); ?>" class="btn btn-outline-secondary"><?php echo Text::_('COM_PUNGACONTACTFORM_BACK_MESSAGES'); ?></a></p>
<dl class="row">
    <?php foreach (['created_at'=>'COM_PUNGACONTACTFORM_DATE','status'=>'COM_PUNGACONTACTFORM_STATUS','reason'=>'COM_PUNGACONTACTFORM_REASON','source_kind'=>'COM_PUNGACONTACTFORM_SOURCE','source_id'=>'COM_PUNGACONTACTFORM_SOURCE_ID','sender_name'=>'COM_PUNGACONTACTFORM_SENDER','sender_email'=>'COM_PUNGACONTACTFORM_SENDER_EMAIL','subject'=>'COM_PUNGACONTACTFORM_SUBJECT','spam_score'=>'COM_PUNGACONTACTFORM_SCORE','spam_threshold'=>'COM_PUNGACONTACTFORM_THRESHOLD'] as $field => $label): ?>
    <dt class="col-sm-3"><?php echo Text::_($label); ?></dt><dd class="col-sm-9"><?php echo $esc($item->$field); ?></dd>
    <?php endforeach; ?>
</dl>
<h2><?php echo Text::_('COM_PUNGACONTACTFORM_BODY'); ?></h2>
<?php if (trim((string) $item->body) === ''): ?>
<p class="text-muted"><?php echo Text::_('COM_PUNGACONTACTFORM_BODY_NOT_RETAINED'); ?></p>
<?php else: ?>
<pre style="white-space:pre-wrap;overflow-wrap:anywhere"><?php echo $esc($item->body); ?></pre>
<?php endif; ?>
<h2><?php echo Text::_('COM_PUNGACONTACTFORM_SCORE_DETAILS'); ?></h2>
<table class="table table-striped"><thead><tr><th><?php echo Text::_('COM_PUNGACONTACTFORM_RULE'); ?></th><th><?php echo Text::_('COM_PUNGACONTACTFORM_SCORE'); ?></th></tr></thead>
<tbody><?php foreach ($matches as $match): if (!is_array($match)) continue; ?>
<tr><td><?php echo $esc($match['description'] ?? $match['id'] ?? ''); ?></td><td>+<?php echo (int) ($match['points'] ?? 0); ?></td></tr>
<?php endforeach; ?></tbody></table>
