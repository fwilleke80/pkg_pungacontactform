<?php
/**
 * @file
 * @brief Joomla contact menu page with optional native page heading.
 */
\defined('_JEXEC') or die;
?>
<div class="punga-contact-page<?php echo $this->centerPageHeading ? ' pcf-layout-centered' : ''; ?>">
    <?php if ($this->showPageHeading) : ?>
        <div class="page-header pcf-page-heading">
            <h1><?php echo $this->escape($this->pageHeading); ?></h1>
        </div>
    <?php endif; ?>
    <?php echo $this->formHtml; ?>
</div>
