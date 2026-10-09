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
    check(len(package.findall('./files/file')) == 2, 'Package needs two children')
    print(f'OK: {len(files)} XML manifests/configuration files')


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


def test_language() -> None:
    """Reject missing/duplicate translation keys and malformed INI assignments."""
    count = 0
    for path in sorted(ROOT.rglob('*.ini')):
        keys = set()
        for line in path.read_text(encoding='utf-8').splitlines():
            if not line.strip() or line.lstrip().startswith(';'):
                continue
            match = re.fullmatch(r'([A-Z][A-Z0-9_]*)="(.*)"', line)
            check(bool(match), f'Malformed INI line: {path}: {line}')
            key = match.group(1)
            check(key not in keys, f'Duplicate language key {path}: {key}')
            keys.add(key)
            count += 1
    print(f'OK: {count} translation strings across all languages')


def test_security_invariants() -> None:
    """Check structural properties (not a substitute for runtime testing)."""
    controller = (ROOT / 'component/site/src/Controller/ContactController.php').read_text()
    renderer = (ROOT / 'component/site/src/Helper/FormHelper.php').read_text()
    template = (ROOT / 'component/site/tmpl/contact/form.php').read_text()
    throttle = (ROOT / 'component/site/src/Helper/ThrottleHelper.php').read_text()
    check("checkToken('post')" in controller, 'Missing CSRF check')
    check('unset($contexts[$contextId])' in controller, 'Context must be single use')
    check("get('recipient_email'" not in template, 'Template cannot fetch server-side address')
    check('settings[\'recipient\']' not in template, 'Template must not output recipient')
    check('FormHelper::escape($name)' in template, 'HTML output must be escaped')
    check('FormHelper::escape($body)' in template, 'Message must be HTML-escaped')
    check('pungacontactform.contexts' in renderer, 'Missing server-side context')
    check('hash_hmac' in throttle, 'Rate-limit key must be one-way')
    check('SpamHelper::shouldDiscard' in controller, 'Content scoring must run before mail transport')
    check(controller.index('SpamHelper::shouldDiscard') < controller.index('->createMailer()'), 'Content scoring must precede delivery')
    check("$app->getIdentity()->guest" in controller, 'Registration status must come from Joomla identity')
    check("$session->clear('pungacontactform.previous.' . $source)" in controller, 'Silently discarded submissions must clear their form state')
    check('AnalyticsHelper::record' in controller, 'Outcome events must be emitted')
    check(controller.index("AnalyticsHelper::record('sent'") > controller.index('->send()'), 'Success event must follow recipient delivery')
    check('onPungaAnalyticsRecord' in (ROOT / 'component/site/src/Helper/AnalyticsHelper.php').read_text(), 'Missing generic Analytics event')
    check('Captcha' in renderer or 'captcha' in renderer, 'Missing native CAPTCHA integration')
    check('type="hidden" name="context"' in template, 'Missing context identifier')
    print('OK: structural privacy and security guards present')


if __name__ == '__main__':
    test_all_xml()
    test_php()
    test_spam()
    test_analytics()
    test_language()
    test_security_invariants()
    print('PASS: all static tests')
