<?php
/** @file @brief Contact form module layout. */
\defined('_JEXEC') or die;

echo \Punga\Component\Pungacontactform\Site\Helper\FormHelper::render($params, 'module', (int) $module->id);
