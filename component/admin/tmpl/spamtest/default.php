<?php
/** @file @brief In-memory administrator spam analysis form. */
\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

?>
<p><a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_config&view=component&component=com_pungacontactform'); ?>"><?php echo Text::_('COM_PUNGACONTACTFORM_BACK_SPAM_OPTIONS'); ?></a></p>
<?php
$esc = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<p><?php echo Text::_('COM_PUNGACONTACTFORM_TEST_DISCLAIMER'); ?></p>
<form action="<?php echo Route::_('index.php?option=com_pungacontactform&view=spamtest'); ?>" method="post">
    <div class="mb-3"><label for="spam-subject" class="form-label"><?php echo Text::_('COM_PUNGACONTACTFORM_SUBJECT'); ?></label>
    <input id="spam-subject" class="form-control" type="text" maxlength="200" name="subject" value="<?php echo $esc($this->subject); ?>" /></div>
    <div class="mb-3"><label for="spam-body" class="form-label"><?php echo Text::_('COM_PUNGACONTACTFORM_BODY'); ?></label>
    <textarea id="spam-body" class="form-control" rows="7" maxlength="20000" name="body"><?php echo $esc($this->body); ?></textarea></div>
    <button type="submit" class="btn btn-primary"><?php echo Text::_('COM_PUNGACONTACTFORM_ANALYZE'); ?></button>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
<?php if ($this->result !== null): ?>
    <h2 class="mt-4"><?php echo Text::_('COM_PUNGACONTACTFORM_SCORE_DETAILS'); ?></h2>
    <p><strong><?php echo (int) $this->result['score']; ?> / <?php echo (int) $this->threshold; ?></strong> —
    <?php echo Text::_($this->result['score'] >= $this->threshold ? 'COM_PUNGACONTACTFORM_STATUS_REJECTED' : 'COM_PUNGACONTACTFORM_STATUS_SENT'); ?></p>
    <table class="table table-striped"><thead><tr><th><?php echo Text::_('COM_PUNGACONTACTFORM_RULE'); ?></th><th><?php echo Text::_('COM_PUNGACONTACTFORM_SCORE'); ?></th></tr></thead><tbody>
        <?php foreach ($this->result['matches'] as $rule): ?>
        <tr><td><?php echo $esc($rule['description']); ?></td><td>+<?php echo (int) $rule['points']; ?></td></tr>
        <?php endforeach; ?>
    </tbody></table>
<?php endif; ?>
