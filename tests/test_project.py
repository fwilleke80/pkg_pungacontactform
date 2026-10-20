#!/usr/bin/env python3
"""Static manifest, translation and basic security regression tests."""
from __future__ import annotations

from pathlib import Path
import re
import subprocess
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]


def check(thing: bool, message: str) -> None:
    """Raise with a descriptive assertion message."""
    assert thing, message


def test_all_xml() -> None:
    """Validate all XML, including Joomla installer manifests and SQL references."""
    files = sorted(ROOT.rglob('*.xml'))
    check(len(files) >= 5, 'Missing XML manifest files')
    for path in files:
        ET.parse(path)
    manifest = ET.parse(ROOT / 'component/pungacontactform.xml')
    paths = [ROOT / 'component/admin' / str(el.text) for el in manifest.findall('.//install/sql/file')]
    paths += [ROOT / 'component/admin' / str(el.text) for el in manifest.findall('.//uninstall/sql/file')]
    for path in paths:
        check(path.exists(), f'Missing installer SQL: {path}')
    menu = ROOT / 'component/site/tmpl/contact/default.xml'
    check(menu.is_file(), 'Frontend menu metadata missing')
    package = ET.parse(ROOT / 'package/pkg_pungacontactform.xml')
    check(len(package.findall('./files/file')) == 4, 'Package needs four children')
    print(f'OK: {len(files)} XML manifests/configuration files')


def test_retention_switchers() -> None:
    """Ensure Joomla switcher state, labels, saved values and defaults agree."""
    config = ET.parse(ROOT / 'component/admin/config.xml')
    expected_defaults = {
        'store_sent': '0',
        'store_rejected': '1',
        'store_failed': '1',
    }
    storage = config.find('./fieldset[@name="storage"]')
    check(storage is not None, 'Message history and retention options missing')

    for field_name, default in expected_defaults.items():
        field = storage.find(f'./field[@name="{field_name}"]')
        check(field is not None, f'Missing retention switcher: {field_name}')
        check(field.get('layout') == 'joomla.form.field.radio.switcher',
              f'{field_name} must use the standard Joomla switcher')
        check(field.get('default') == default, f'{field_name} default changed unexpectedly')
        actual = [(option.get('value'), option.text) for option in field.findall('option')]
        check(actual == [('0', 'JNO'), ('1', 'JYES')],
              f'{field_name}: Joomla requires No/off first, Yes/on second; found {actual}')

    print('OK: 3 retention switchers use No/0 (off), Yes/1 (on), preserving defaults')


def test_php() -> None:
    """Lint every PHP source with the installed PHP interpreter."""
    paths = sorted(ROOT.rglob('*.php'))
    check(len(paths) > 6, 'Missing PHP sources')
    for path in paths:
        result = subprocess.run(['php', '-l', str(path)], text=True, capture_output=True, check=False)
        check(result.returncode == 0, f'PHP lint failed on {path}: {result.stdout}{result.stderr}')
    print(f'OK: {len(paths)} PHP files lint clean')


def test_spam() -> None:
    """Run the content classifier against known spam and legitimate samples."""
    result = subprocess.run(
        ['php', str(ROOT / 'tests/test_spam.php')],
        text=True, capture_output=True, check=False,
    )
    check(result.returncode == 0, f'Spam tests failed: {result.stdout}{result.stderr}')
    print(result.stdout.strip())


def test_analytics() -> None:
    """Exercise Joomla-event payloads and verify no private fields leak."""
    result = subprocess.run(
        ['php', str(ROOT / 'tests/test_analytics.php')],
        text=True, capture_output=True, check=False,
    )
    check(result.returncode == 0, f'Analytics tests failed: {result.stdout}{result.stderr}')
    print(result.stdout.strip())


def test_message_store() -> None:
    """Exercise message history/body storage independently of a Joomla database."""
    result = subprocess.run(
        ['php', '-d', 'zend.assertions=1', '-d', 'assert.exception=1', str(ROOT / 'tests/test_message_store.php')],
        text=True, capture_output=True, check=False,
    )
    check(result.returncode == 0, f'Message-store tests failed: {result.stdout}{result.stderr}')
    print(result.stdout.strip())


def test_counter_accounting() -> None:
    """Validate atomic counter integration and recovery presentation."""
    store = (ROOT / 'component/site/src/Helper/MessageStoreHelper.php').read_text()
    helper = (ROOT / 'component/site/src/Helper/CounterHelper.php').read_text()
    dashboard = (ROOT / 'component/admin/src/View/Info/HtmlView.php').read_text()
    check('CounterHelper::increment($db' in store, 'Submission store not using atomic counters')
    check('transactionStart()' in store and 'transactionCommit()' in store and 'transactionRollback()' in store,
          'Atomic message/counter transaction missing')
    check('getAffectedRows()' not in store, 'Old unsafe UPDATE/INSERT/retry counter path remains')
    check('ON DUPLICATE KEY UPDATE' in helper and 'ON CONFLICT' in helper,
          'Both supported SQL-family atomic upsert variants must exist')
    check('GREATEST(' in helper, 'Reconciliation must never lower cumulative totals')
    check('CounterHelper::reconcileRetainedHistory($db)' in dashboard,
          'Admin dashboard does not repair historical discrepancies')
    check('COM_PUNGACONTACTFORM_COUNTERS_RECONCILIATION_FAILED' in dashboard,
          'Admin must be notified when reconciliation fails')
    for lang, status_label in [('en-GB', 'Sent'), ('de-DE', 'Gesendet')]:
        strings = (ROOT / f'component/admin/language/{lang}/com_pungacontactform.ini').read_text()
        check(f'COM_PUNGACONTACTFORM_STATUS_SENT="{status_label}"' in strings,
              f'Incorrect translated sent status for {lang}')
    check((ROOT / 'docs/STATISTICS.md').is_file(), 'Missing statistic recovery documentation')
    print('OK: atomic counter write, repair path, administrator diagnostics and status labels')


def test_sender_copy() -> None:
    """Verify visitor copies never inherit private outgoing sender details."""
    result = subprocess.run(
        ['php', '-d', 'zend.assertions=1', '-d', 'assert.exception=1', str(ROOT / 'tests/test_sender_copy.php')],
        text=True, capture_output=True, check=False,
    )
    check(result.returncode == 0, f'Sender-copy tests failed: {result.stdout}{result.stderr}')
    print(result.stdout.strip())


def test_page_display() -> None:
    """Exercise contact-menu page headings and Joomla browser-title integration."""
    result = subprocess.run(
        ['php', '-d', 'zend.assertions=1', '-d', 'assert.exception=1', str(ROOT / 'tests/test_page_display.php')],
        text=True, capture_output=True, check=False,
    )
    check(result.returncode == 0, f'Page display tests failed: {result.stdout}{result.stderr}')
    print(result.stdout.strip())


def test_language() -> None:
    """Reject missing/duplicate translation keys and malformed INI assignments."""
    count = 0
    for path in sorted(ROOT.rglob('*.ini')):
        keys = set()
        for line in path.read_text(encoding='utf-8').splitlines():
            if not line.strip() or line.lstrip().startswith(';'):
                continue
            match = re.fullmatch(r'([A-Z][A-Z0-9_-]*)="(.*)"', line)
            check(bool(match), f'Malformed INI line: {path}: {line}')
            key = match.group(1)
            check(key not in keys, f'Duplicate language key {path}: {key}')
            keys.add(key)
            count += 1
    print(f'OK: {count} translation strings across all languages')


def test_form_alignment() -> None:
    """Check global/menu/module fields, inherited values, rendering and safe CSS."""
    expected_global = [('left', 'COM_PUNGACONTACTFORM_FORM_ALIGNMENT_LEFT'),
                       ('centered', 'COM_PUNGACONTACTFORM_FORM_ALIGNMENT_CENTERED')]
    expected_override = [('inherit', 'COM_PUNGACONTACTFORM_INHERIT')] + expected_global
    definitions = [
        ('component/admin/config.xml', './fieldset[@name="general"]', 'left', expected_global),
        ('component/site/tmpl/contact/default.xml', './fields/fieldset[@name="basic"]', 'inherit', expected_override),
        ('module/mod_pungacontactform.xml', './config/fields/fieldset[@name="basic"]', 'inherit', expected_override),
    ]
    for filename, parent_path, default, expected in definitions:
        form = ET.parse(ROOT / filename)
        field = form.find(f'{parent_path}/field[@name="form_alignment"]')
        check(field is not None, f'Alignment setting missing: {filename}')
        check(field.get('type') == 'list' and field.get('default') == default,
              f'Incorrect alignment defaults in {filename}')
        check([(e.get('value'), e.text) for e in field.findall('option')] == expected,
              f'Alignment selections incorrect in {filename}')
    renderer = (ROOT / 'component/site/src/Helper/FormHelper.php').read_text()
    template = (ROOT / 'component/site/tmpl/contact/form.php').read_text()
    styles = (ROOT / 'component/media/css/form.css').read_text()
    check("$alignment = $get('form_alignment', 'left');" in renderer,
          'Alignment must inherit global settings using the standard resolver')
    check("in_array($alignment, ['left', 'centered'], true)" in renderer,
          'Alignment must be whitelisted before rendering into markup')
    check("$settings['form_alignment'] === 'centered'" in template and 'pcf-layout-centered' in template,
          'Both form frontends must render the same alignment class')
    check('.punga-contact-form.pcf-layout-centered {margin-inline:auto;}' in styles,
          'Centered mode must apply horizontal automatic margins only')
    check('max-width:38rem' in styles and 'width:100%' in styles,
          'Centered mode must preserve responsive form sizing')
    for lang in ('de-DE', 'en-GB'):
        for scope in ('component/admin/language', 'component/site/language', 'module/language'):
            langfile = (f'{scope}/{lang}/' + ('mod_pungacontactform.ini' if scope.startswith('module') else 'com_pungacontactform.ini'))
            content = (ROOT / langfile).read_text()
            check('COM_PUNGACONTACTFORM_FORM_ALIGNMENT_CENTERED=' in content,
                  f'Missing alignment labels in {langfile}')
        sys = (ROOT / f'component/admin/language/{lang}/com_pungacontactform.sys.ini').read_text()
        check('COM_PUNGACONTACTFORM_FORM_ALIGNMENT=' in sys,
              f'Menu editor must have an alignment label in {lang} .sys.ini')
    result = subprocess.run(['php', '-d', 'zend.assertions=1', '-d', 'assert.exception=1',
                             str(ROOT / 'tests/test_form_alignment.php')],
                            text=True, capture_output=True, check=False)
    check(result.returncode == 0, f'Form alignment tests failed: {result.stdout}{result.stderr}')
    print('OK: three alignment controls, shared responsive CSS, English/German labels')
    print(result.stdout.strip())


def test_security_invariants() -> None:
    """Check structural properties (not a substitute for runtime testing)."""
    controller = (ROOT / 'component/site/src/Controller/ContactController.php').read_text()
    renderer = (ROOT / 'component/site/src/Helper/FormHelper.php').read_text()
    template = (ROOT / 'component/site/tmpl/contact/form.php').read_text()
    throttle = (ROOT / 'component/site/src/Helper/ThrottleHelper.php').read_text()
    check("checkToken('post')" in controller, 'Missing CSRF check')
    check('unset($contexts[$contextId])' in controller, 'Context must be single use')
    check("get('recipient_email'" not in template, 'Template cannot fetch server-side address')
    check('SenderCopyHelper::available($settings)' in template, 'Optional copy checkbox must require safe sender')
    copy_helper = (ROOT / 'component/site/src/Helper/SenderCopyHelper.php').read_text()
    check('new Registry([' in copy_helper and "'mailfrom' => $address" in copy_helper, 'Copy must override global sender at mailer construction')
    check('SenderCopyHelper::send($factory' in controller, 'Controller must use isolated copy helper')
    check('copy_sender_email' in (ROOT / 'component/admin/config.xml').read_text(), 'Public copy sender setting missing')
    check('settings[\'recipient\']' not in template, 'Template must not output recipient')
    check('FormHelper::escape($name)' in template, 'HTML output must be escaped')
    check('FormHelper::escape($body)' in template, 'Message must be HTML-escaped')
    check('pungacontactform.contexts' in renderer, 'Missing server-side context')
    check('hash_hmac' in throttle, 'Rate-limit key must be one-way')
    check('SpamHelper::analyzeDetailed' in controller, 'Content scoring must run before mail transport')
    check(controller.index('SpamHelper::analyzeDetailed') < controller.index('->createMailer()'), 'Content scoring must precede delivery')
    check("$app->getIdentity()->guest" in controller, 'Registration status must come from Joomla identity')
    check("$session->clear('pungacontactform.previous.' . $source)" in controller, 'Silently discarded submissions must clear their form state')
    check('AnalyticsHelper::record' in controller, 'Outcome events must be emitted')
    check(controller.index("AnalyticsHelper::record('sent'") > controller.index('->send()'), 'Success event must follow recipient delivery')
    check('onPungaAnalyticsRecord' in (ROOT / 'component/site/src/Helper/AnalyticsHelper.php').read_text(), 'Missing generic Analytics event')
    check('Captcha' in renderer or 'captcha' in renderer, 'Missing native CAPTCHA integration')
    check('type="hidden" name="context"' in template, 'Missing context identifier')
    messages = (ROOT / 'component/admin/src/Controller/MessagesController.php').read_text()
    config = (ROOT / 'component/admin/config.xml').read_text()
    store = (ROOT / 'component/site/src/Helper/MessageStoreHelper.php').read_text()
    dashboard = (ROOT / 'component/admin/src/View/Info/HtmlView.php').read_text()
    check("checkToken('post')" in messages, 'Moderation must require CSRF token')
    check('core.delete' in messages and 'core.edit.state' in messages, 'Moderation must enforce ACL')
    check('<inlinehelp button="show" />' in config, 'Missing Joomla inline help')
    check('store_rejected' in store and 'retention_days' in store, 'Storage policy missing')
    check('recentMessages' in dashboard and 'manifest_cache' in dashboard, 'Dashboard history/version missing')
    dashboard_template = (ROOT / 'component/admin/tmpl/info/default.php').read_text()
    check('recentMessages as $message' in dashboard_template, 'Dashboard table missing')
    check('COM_PUNGACONTACTFORM_DASHBOARD_INFO' not in dashboard_template,
          'Redundant Dashboard explanation should not be displayed')
    check('COM_PUNGACONTACTFORM_HISTORY_NOTE' not in dashboard_template,
          'Redundant Dashboard history note should not be displayed')
    check('SpamHelper::builtInRules()' not in dashboard, 'Do not put spam catalog on Dashboard')
    spam_field = (ROOT / 'component/admin/src/Field/SpamrulesField.php').read_text()
    check('SpamHelper::builtInRules()' in spam_field, 'Rules must be shown in existing Spam Protection options')
    check('view=spamtest' in spam_field, 'Spam classifier tester must be reachable from options')
    check('name="security"' in config and 'type="spamrules"' in config, 'Use the existing Spam Protection fieldset')
    check('COM_PUNGACONTACTFORM_CONFIGURATION' in (ROOT / 'component/admin/language/en-GB/com_pungacontactform.ini').read_text(), 'Configuration title translation missing')
    check("$db->insertObject('#__pungacontactform_messages', $row);" in store, 'Every submission must be stored as history metadata')
    check("'body' => $storeBody ?" in store, 'Body retention must be independent of history metadata')
    check((ROOT / 'CHANGELOG.md').is_file(), 'Missing changelog')
    check((ROOT / 'docs/MAIL_DELIVERY.md').is_file(), 'Missing developer documentation')
    check(len((ROOT / 'README.md').read_text().splitlines()) < 30, 'Root README must stay concise')
    for lang in ('de-DE', 'en-GB'):
        sys = (ROOT / f'component/admin/language/{lang}/com_pungacontactform.sys.ini').read_text()
        check('COM_PUNGACONTACTFORM_OVERRIDE_SETTINGS=' in sys, 'Menu tab translation missing')
    for lang in ('de-DE', 'en-GB'):
        for extension in ('ini', 'sys.ini'):
            translation = (ROOT / f'component/admin/language/{lang}/com_pungacontactform.{extension}').read_text()
            check('COM_PUNGACONTACTFORM_CONFIGURATION="Punga Contact Form"' in translation, 'Options title translation missing')
    for schema in ('mysql', 'postgresql'):
        check((ROOT / f'component/admin/sql/updates/{schema}/0.1.3.sql').is_file(), 'Upgrade migration missing')
    print('OK: structural privacy and security guards present')


def test_sef_router() -> None:
    """Check all three Joomla router integration points and the only site view."""
    provider = (ROOT / 'component/admin/services/provider.php').read_text()
    extension = (ROOT / 'component/admin/src/Extension/PungacontactformComponent.php').read_text()
    router = (ROOT / 'component/site/src/Service/Router.php').read_text()

    check("new RouterFactory('\\\\Punga\\\\Component\\\\Pungacontactform')" in provider,
          'Component router factory must be registered with the correct namespace')
    check('setRouterFactory($container->get(RouterFactoryInterface::class))' in provider,
          'The component must receive Joomla\'s router factory')
    check('new PungacontactformComponent(' in provider,
          'The router-capable component must be bootstrapped')
    check('implements RouterServiceInterface' in extension and 'use RouterServiceTrait;' in extension,
          'Component must provide Joomla RouterServiceInterface')
    check('final class Router extends RouterView' in router,
          'Frontend router must extend the Joomla RouterView class')
    check("registerView(new RouterViewConfiguration('contact'))" in router,
          'The contact view must be registered without an item ID')
    check('setKey(' not in router and 'setParent(' not in router,
          'Contact view should have no slug or child URL segments')

    rules = ['MenuRules', 'StandardRules', 'NomenuRules']
    positions = [router.index(f'$this->attachRule(new {rule}($this))') for rule in rules]
    check(positions == sorted(positions), 'Joomla routing rules must retain their standard order')

    menu_view = ROOT / 'component/site/tmpl/contact/default.xml'
    check(menu_view.is_file(), 'Existing contact menu-item view must remain installable')
    check("$action = Route::_('index.php?option=com_pungacontactform&task=contact.send', false);" in
          (ROOT / 'component/site/src/Helper/FormHelper.php').read_text(),
          'The existing POST task must keep its Joomla route')
    check("$return = (string) $context['return'];" in
          (ROOT / 'component/site/src/Controller/ContactController.php').read_text(),
          'POST redirect should use the source page rather than a hard-coded path')
    print('OK: Joomla SEF router, menu view, POST and redirect wiring')


def test_content_and_acknowledgements() -> None:
    """Exercise new plugin parsers, separate public replies and package membership."""
    manifest = ET.parse(ROOT / 'package/pkg_pungacontactform.xml')
    children = manifest.findall('./files/file')
    assert len(children) == 4
    assert [(c.get('type'), c.get('id'), c.get('group')) for c in children[-2:]] == [
        ('plugin', 'pungacontactform', 'content'),
        ('plugin', 'pungacontactform', 'editors-xtd'),
    ]
    for group in ('content', 'editors-xtd'):
        manifest_file = ROOT / 'plugins' / group / 'pungacontactform/pungacontactform.xml'
        plugin_xml = ET.parse(manifest_file)
        folder = plugin_xml.find('./files/folder[@plugin="pungacontactform"]')
        check(folder is not None and folder.text == 'services',
              f'{group} plugin must use Joomla 6 DI plugin folder marker')
        check(plugin_xml.find('./namespace') is not None, 'Plugin namespace missing')
        provider = (manifest_file.parent / 'services/provider.php').read_text()
        check('setApplication(Factory::getApplication())' in provider,
              'Joomla 6 plugins must be initialized with a CMS application')
    content_source = (ROOT / 'plugins/content/pungacontactform/src/Extension/Pungacontactform.php').read_text()
    check('onContentPrepare' in content_source and 'parseOptions' in content_source,
          'Content plugin rendering or parser missing')
    check('ModuleHelper::getModuleById' in content_source and "'embed'" in content_source,
          'Content plugin must reuse published accessible module settings')
    button = (ROOT / 'plugins/editors-xtd/pungacontactform/src/Extension/Pungacontactform.php').read_text()
    js = (ROOT / 'plugins/editors-xtd/pungacontactform/media/js/editor.js').read_text()
    check('onEditorButtonsSetup' in button and 'insert-pungacontactform' in button,
          'Joomla editor button event or action is missing')
    check('JoomlaEditorButton.registerAction' in js and 'editor.replaceSelection' in js,
          'Editor must insert via Joomla editors API')
    controller = (ROOT / 'component/site/src/Controller/ContactController.php').read_text()
    check('AutomaticReplyHelper::send' in controller and 'SenderCopyHelper::send' in controller,
          'Auto-acknowledgements must not replace sender copies')
    check(controller.index('AutomaticReplyHelper::send') > controller.index('$mailer->send();'),
          'Automatic acknowledgement must occur after main mail dispatch')
    for filename in ('tests/test_content_plugin.php', 'tests/test_auto_reply.php'):
        result = subprocess.run(['php', '-d', 'zend.assertions=1', '-d', 'assert.exception=1', str(ROOT / filename)],
                                capture_output=True, text=True, check=False)
        check(result.returncode == 0, f'{filename} failed: {result.stdout}{result.stderr}')
        print(result.stdout.strip())
    check((ROOT / 'docs/EMBEDDING_AND_REPLIES.md').exists(), 'Detailed plugin/reply documentation missing')
    print('OK: four-child Joomla package, plugin DI, editor API, mail gating, and plugin tests')


if __name__ == '__main__':
    test_all_xml()
    test_retention_switchers()
    test_php()
    test_spam()
    test_analytics()
    test_message_store()
    test_counter_accounting()
    test_sender_copy()
    test_form_alignment()
    test_page_display()
    test_language()
    test_security_invariants()
    test_sef_router()
    test_content_and_acknowledgements()
    print('PASS: all static tests')
