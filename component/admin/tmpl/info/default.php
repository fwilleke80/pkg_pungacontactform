<?php
/** @file @brief Submission dashboard with statistics and a recent message table. */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$esc = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="container-main">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
        <?php if ($this->version !== ''): ?>
        <span class="text-muted small"><?php echo Text::sprintf('COM_PUNGACONTACTFORM_INSTALLED_VERSION', $esc($this->version)); ?></span>
        <?php endif; ?>
    </div>
    <div class="row g-3 my-3">
        <?php foreach (['sent', 'rejected', 'failed'] as $status): ?>
        <div class="col-12 col-md-6 col-xl-3"><div class="card h-100"><div class="card-body">
            <h2 class="h6"><?php echo Text::_('COM_PUNGACONTACTFORM_STATUS_' . strtoupper($status)); ?></h2>
            <p class="h2 mb-0"><?php echo number_format((int) $this->counts[$status]); ?></p>
        </div></div></div>
        <?php endforeach; ?>
        <div class="col-12 col-md-6 col-xl-3"><div class="card h-100"><div class="card-body">
            <h2 class="h6"><?php echo Text::_('COM_PUNGACONTACTFORM_ARCHIVED'); ?></h2>
            <p class="h2 mb-0"><?php echo number_format($this->archived); ?></p>
        </div></div></div>
    </div>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h4 mb-0"><?php echo Text::_('COM_PUNGACONTACTFORM_RECENT_MESSAGES'); ?></h2>
        <a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_pungacontactform&view=messages'); ?>"><?php echo Text::_('COM_PUNGACONTACTFORM_ALL_MESSAGES'); ?></a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <caption class="visually-hidden"><?php echo Text::_('COM_PUNGACONTACTFORM_RECENT_MESSAGES'); ?></caption>
            <thead><tr>
                <th scope="col"><?php echo Text::_('COM_PUNGACONTACTFORM_DATE'); ?></th>
                <th scope="col"><?php echo Text::_('COM_PUNGACONTACTFORM_SENDER'); ?></th>
                <th scope="col"><?php echo Text::_('COM_PUNGACONTACTFORM_SUBJECT'); ?></th>
                <th scope="col"><?php echo Text::_('COM_PUNGACONTACTFORM_STATUS'); ?></th>
                <th scope="col"><?php echo Text::_('COM_PUNGACONTACTFORM_SCORE'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($this->recentMessages as $message): ?>
                <tr>
                    <td class="text-nowrap"><?php echo $esc($message->created_at); ?> UTC</td>
                    <td><?php echo $esc($message->sender_name); ?></td>
                    <td><a href="<?php echo Route::_('index.php?option=com_pungacontactform&view=message&id=' . (int) $message->id); ?>"><?php echo $esc($message->subject ?: Text::_('COM_PUNGACONTACTFORM_NO_SUBJECT')); ?></a></td>
                    <td><?php echo Text::_('COM_PUNGACONTACTFORM_STATUS_' . strtoupper($message->status)); ?><?php echo $message->reason ? ' (' . $esc($message->reason) . ')' : ''; ?><?php echo (int) $message->archived === 1 ? ' · ' . Text::_('COM_PUNGACONTACTFORM_ARCHIVED') : ''; ?></td>
                    <td><?php echo (int) $message->spam_threshold > 0 ? (int) $message->spam_score . ' / ' . (int) $message->spam_threshold : '—'; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$this->recentMessages): ?>
                <tr><td colspan="5" class="text-muted"><?php echo Text::_('COM_PUNGACONTACTFORM_NO_MESSAGES'); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
