<?php
/** @file @brief Administrator setup screen. */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
?>
<div class="container-main">
    <h1><?php echo Text::_('COM_PUNGACONTACTFORM'); ?></h1>
    <p><?php echo Text::_('COM_PUNGACONTACTFORM_ADMIN_GUIDANCE'); ?></p>
    <p><a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_config&view=component&component=com_pungacontactform'); ?>"><?php echo Text::_('COM_PUNGACONTACTFORM_OPEN_OPTIONS'); ?></a></p>
</div>
