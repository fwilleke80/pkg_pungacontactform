#!/usr/bin/env python3
"""Exercise editor insertion dialog in Chromium with a lightweight Joomla API fixture."""
from __future__ import annotations

from pathlib import Path

from playwright.sync_api import sync_playwright

ROOT: Path = Path(__file__).resolve().parents[1]
SCRIPT: str = (ROOT / "plugins/editors-xtd/pungacontactform/media/js/editor.js").read_text(encoding="utf-8")

HTML: str = """<!doctype html>
<html><head>
<meta charset="utf-8">
<script>
window.registeredActions = {};
window.lastInsertion = null;
window.Joomla = {getOptions: (key) => key === 'plg_editors-xtd_pungacontactform' ? ({
    modules: [{id: 123, title: 'Inquiries'}],
    strings: {title: 'Contact form', source: 'Configuration', default: 'Component defaults',
              layout: 'Layout', inherit: 'Inherit', left: 'Left', centered: 'Centered',
              intro: 'Introduction', yes: 'Yes', no: 'No', insert: 'Insert form', cancel: 'Cancel'}
}) : ({})};
window.editor = {replaceSelection: (value) => {window.lastInsertion = value;}};
</script>

</head><body><button id="xtd" type="button" onclick="window.registeredActions['insert-pungacontactform'](window.editor)">Contact Form</button></body></html>"""



def test_editor_dialog() -> None:
    """Check that an editor click opens a dialog and inserts the exact placeholder."""
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True, executable_path="/usr/bin/chromium", args=["--no-sandbox"])
        try:
            page = browser.new_page()
            errors: list[str] = []
            page.on("pageerror", lambda error: errors.append(str(error)))

            page.set_content(HTML)
            # Test the actual action implementation with the Joomla editor-api
            # import replaced by a controlled mock to avoid network dependencies.
            script = SCRIPT.replace(
                "import { JoomlaEditorButton } from 'editor-api';",
                "const JoomlaEditorButton = {registerAction: (name, callback) => {window.registeredActions[name] = callback;}};",
                1,
            )
            page.add_script_tag(type="module", content=script)
            page.wait_for_function("Boolean(window.registeredActions['insert-pungacontactform'])")
            page.locator("#xtd").click()
            dialog = page.locator("dialog[open]")
            assert dialog.count() == 1, "Click must open the modal dialog"
            dialog.locator("select").nth(0).select_option("123")
            dialog.locator("select").nth(1).select_option("centered")
            dialog.locator("select").nth(2).select_option("no")
            dialog.get_by_role("button", name="Insert form").click()
            assert page.evaluate("window.lastInsertion") == "{contactform module=123 layout=centered intro=no}"
            page.locator("dialog").wait_for(state="detached")

            page.locator("#xtd").click()
            assert page.locator("dialog[open]").count() == 1
            page.get_by_role("button", name="Cancel").click()
            page.locator("dialog").wait_for(state="detached")
            assert page.evaluate("window.lastInsertion") == "{contactform module=123 layout=centered intro=no}", "Cancel must not insert text"

            page.locator("#xtd").click()
            page.get_by_role("button", name="Insert form").click()
            assert page.evaluate("window.lastInsertion") == "{contactform}", "Defaults must produce the shortest placeholder"
            assert not errors, f"Browser errors: {errors}"
            print("OK: Chromium editor click, modal settings, insertion, cancellation and defaults")
        finally:
            browser.close()


if __name__ == "__main__":
    test_editor_dialog()



def test_editor_asset_loading() -> None:
    """@brief Validate Joomla asset mapping and execute the unmodified ES module."""
    import base64
    import re

    php_path: Path = ROOT / "plugins/editors-xtd/pungacontactform/src/Extension/Pungacontactform.php"
    php: str = php_path.read_text(encoding="utf-8")
    match = re.search(r"'editor-button\.pungacontactform',\s*'([^']+)'", php)
    assert match is not None, "Editor script URI must be registered"
    uri: str = match.group(1)
    # Joomla's WebAsset item resolves a script URI by inserting the /js/ directory.
    folder, filename = uri.split("/", maxsplit=1)
    resolved: str = f"/media/{folder}/js/{filename}"
    assert resolved == "/media/plg_editors-xtd_pungacontactform/js/editor.js", resolved
    assert (ROOT / "plugins/editors-xtd/pungacontactform/media/js/editor.js").is_file()

    # Browser executes the ACTUAL import statement, not a text-substituted mock.
    # Embed the two modules as data: URLs because external URLs are unavailable
    # in the offline Chromium test environment.
    editor_api: str = (
        "export const JoomlaEditorButton = {"
        "registerAction: (name, callback) => {window.registeredActions[name] = callback;}"
        "};"
    )
    editor_api_url: str = "data:text/javascript;base64," + base64.b64encode(editor_api.encode()).decode()
    editor_url: str = "data:text/javascript;base64," + base64.b64encode(SCRIPT.encode()).decode()
    html: str = HTML.replace(
        "</head>",
        '<script type="importmap">'
        + '{"imports":{"editor-api":"' + editor_api_url + '"}}'
        + '</script><script type="module" src="' + editor_url + '"></script>'
        + "</head>",
    )

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(
            headless=True, executable_path="/usr/bin/chromium", args=["--no-sandbox"]
        )
        try:
            page = browser.new_page()
            errors: list[str] = []
            page.on("pageerror", lambda error: errors.append(str(error)))
            page.set_content(html)
            page.wait_for_function("Boolean(window.registeredActions['insert-pungacontactform'])")
            page.locator("#xtd").click()
            assert page.locator("dialog[open]").count() == 1
            page.locator("dialog select").nth(0).select_option("123")
            page.locator("dialog select").nth(1).select_option("centered")
            page.get_by_role("button", name="Insert form").click()
            assert page.evaluate("window.lastInsertion") == "{contactform module=123 layout=centered}"
            assert not errors, f"Browser errors while loading ES module: {errors}"
            print("OK: resolved Joomla WebAsset URI, native module import, click action, insertion")
        finally:
            browser.close()


if __name__ == "__main__":
    test_editor_asset_loading()
