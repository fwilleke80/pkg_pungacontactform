<?php
/**
 * @file
 * @brief Shared accessible form template, used by the page and site module.
 * @var string $contextId
 * @var array<string,mixed> $settings
 */
\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Punga\Component\Pungacontactform\Site\Helper\FormHelper;
?>
<div class="punga-contact-form">
    <?php if (trim((string) $settings['intro']) !== '') : ?>
        <div class="pcf-intro"><?php echo FormHelper::escape((string) $settings['intro']); ?></div>
    <?php endif; ?>
    <form action="<?php echo FormHelper::escape($action); ?>" method="post" accept-charset="UTF-8">
        <div class="pcf-field">
            <label for="<?php echo $formId; ?>-name"><?php echo Text::_('COM_PUNGACONTACTFORM_NAME'); ?></label>
            <input type="text" class="form-control" id="<?php echo $formId; ?>-name" name="pcf[name]" value="<?php echo FormHelper::escape($name); ?>" maxlength="120" autocomplete="name" required>
        </div>
        <div class="pcf-field">
            <label for="<?php echo $formId; ?>-email"><?php echo Text::_('COM_PUNGACONTACTFORM_EMAIL'); ?></label>
            <input type="email" class="form-control" id="<?php echo $formId; ?>-email" name="pcf[email]" value="<?php echo FormHelper::escape($email); ?>" maxlength="254" autocomplete="email" required>
        </div>
        <div class="pcf-field">
            <label for="<?php echo $formId; ?>-subject"><?php echo Text::_('COM_PUNGACONTACTFORM_SUBJECT'); ?></label>
            <input type="text" class="form-control" id="<?php echo $formId; ?>-subject" name="pcf[subject]" value="<?php echo FormHelper::escape($subject); ?>" maxlength="200" required>
        </div>
        <div class="pcf-field">
            <label for="<?php echo $formId; ?>-message"><?php echo Text::_('COM_PUNGACONTACTFORM_MESSAGE'); ?></label>
            <textarea class="form-control" id="<?php echo $formId; ?>-message" name="pcf[body]" maxlength="20000" rows="7" required><?php echo FormHelper::escape($body); ?></textarea>
        </div>
        <?php if ($settings['copy_mode'] === 'optional' && \Punga\Component\Pungacontactform\Site\Helper\SenderCopyHelper::available($settings)) : ?>
            <div class="pcf-field pcf-copy">
                <input type="checkbox" id="<?php echo $formId; ?>-copy" name="pcf[copy]" value="1"<?php echo $wantCopy ? ' checked' : ''; ?>>
                <label for="<?php echo $formId; ?>-copy"><?php echo Text::_('COM_PUNGACONTACTFORM_COPY_CHECKBOX'); ?></label>
            </div>
        <?php endif; ?>
        <?php if ($captchaHtml !== '') : ?>
            <div class="pcf-field pcf-captcha"><?php echo $captchaHtml; // Joomla's configured captcha plugin supplies the markup. ?></div>
        <?php endif; ?>
        <div class="pcf-trap" aria-hidden="true">
            <label for="<?php echo $formId; ?>-website"><?php echo Text::_('COM_PUNGACONTACTFORM_HONEYPOT'); ?></label>
            <input type="text" id="<?php echo $formId; ?>-website" name="pcf[website]" value="" tabindex="-1" autocomplete="off">
        </div>
        <input type="hidden" name="context" value="<?php echo $contextId; ?>">
        <?php echo HTMLHelper::_('form.token'); ?>
        <div class="pcf-actions">
            <button type="submit" class="btn btn-primary"><?php echo Text::_('COM_PUNGACONTACTFORM_SEND'); ?></button>
            <?php if ($isMenu) : ?>
                <a class="btn btn-outline-secondary" href="<?php echo FormHelper::escape($cancelUrl); ?>"><?php echo Text::_('JCANCEL'); ?></a>
            <?php else : ?>
                <button type="reset" class="btn btn-outline-secondary"><?php echo Text::_('JCANCEL'); ?></button>
            <?php endif; ?>
        </div>
    </form>
</div>
