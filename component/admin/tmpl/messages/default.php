<?php
/** @file @brief Standard administrator list and bulk moderation. */
\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('bootstrap.tooltip');
$ordering = (string) $this->state->get('list.ordering', 'created_at');
$direction = (string) $this->state->get('list.direction', 'DESC');
$esc = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<p><a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_pungacontactform'); ?>"><?php echo Text::_('COM_PUNGACONTACTFORM_BACK_DASHBOARD'); ?></a></p>
<form action="<?php echo Route::_('index.php?option=com_pungacontactform&view=messages'); ?>" method="post" name="adminForm" id="adminForm">
    <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
    <div class="table-responsive">
        <table class="table table-striped" id="messageList">
            <caption class="visually-hidden"><?php echo Text::_('COM_PUNGACONTACTFORM_MESSAGES'); ?></caption>
            <thead><tr>
                <th class="w-1"><?php echo HTMLHelper::_('grid.checkall'); ?></th>
                <th><?php echo HTMLHelper::_('searchtools.sort', 'JDATE', 'created_at', $direction, $ordering); ?></th>
                <th><?php echo HTMLHelper::_('searchtools.sort', 'COM_PUNGACONTACTFORM_SUBJECT', 'subject', $direction, $ordering); ?></th>
                <th><?php echo HTMLHelper::_('searchtools.sort', 'COM_PUNGACONTACTFORM_SENDER', 'sender_name', $direction, $ordering); ?></th>
                <th><?php echo HTMLHelper::_('searchtools.sort', 'COM_PUNGACONTACTFORM_STATUS', 'status', $direction, $ordering); ?></th>
                <th><?php echo HTMLHelper::_('searchtools.sort', 'COM_PUNGACONTACTFORM_SCORE', 'spam_score', $direction, $ordering); ?></th>
                <th><?php echo Text::_('COM_PUNGACONTACTFORM_ARCHIVED'); ?></th>
                <th><?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'id', $direction, $ordering); ?></th>
            </tr></thead>
            <tbody>
                <?php foreach ($this->items as $i => $item): ?>
                <tr>
                    <td><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id); ?></td>
                    <td><?php echo $esc($item->created_at); ?> UTC</td>
                    <td><a href="<?php echo Route::_('index.php?option=com_pungacontactform&view=message&id=' . (int) $item->id); ?>"><?php echo $esc($item->subject ?: Text::_('COM_PUNGACONTACTFORM_NO_SUBJECT')); ?></a></td>
                    <td><?php echo $esc($item->sender_name); ?></td>
                    <td><?php echo Text::_('COM_PUNGACONTACTFORM_STATUS_' . strtoupper($item->status)); ?><?php echo $item->reason ? ' (' . $esc($item->reason) . ')' : ''; ?></td>
                    <td><?php echo (int) $item->spam_threshold > 0 ? (int) $item->spam_score . ' / ' . (int) $item->spam_threshold : '—'; ?></td>
                    <td><?php echo $item->archived ? Text::_('JYES') : Text::_('JNO'); ?></td>
                    <td><?php echo (int) $item->id; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php echo $this->pagination->getListFooter(); ?>
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="boxchecked" value="0" />
    <input type="hidden" name="filter_order" value="<?php echo $esc($ordering); ?>" />
    <input type="hidden" name="filter_order_Dir" value="<?php echo $esc($direction); ?>" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
