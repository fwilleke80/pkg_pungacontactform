# Contact page routing

Since 0.1.6, the contact component registers a standard Joomla 6 `RouterView`
with a single site view, `contact`. The router is installed through Joomla's
`RouterFactory` service provider and the component's `RouterServiceInterface`.

When a Joomla menu item selects **Punga Contact Form → Contact form** and uses
the alias `contact`, the intended SEF URL is `/contact`. The `view=contact`
query parameter is internal; it should no longer appear in the visible link.
The menu item and its settings do not need to be recreated after an upgrade.

The component uses Joomla's standard menu, matched-view and no-menu routing
rules, rather than manipulating URLs with `.htaccess` or regex replacements.
Form submissions still use the `contact.send` task, and the one-time form
context stores the request's actual path to redirect after submission.

## Verification on a live Joomla site

1. Ensure Joomla SEF URLs are enabled in Global Configuration.
2. Install or upgrade the package, then clear Joomla caches if used.
3. Confirm the menu link and directly visited URL are `/contact` (or the
   corresponding nested alias/language prefix for your site).
4. Verify that the form loads when visited directly; verify POST, success and
   validation-error redirects return to the form URL.
5. Check the module on an unrelated page still submits and redirects back to
   its originating page.

The included static regressions verify service wiring but cannot replace
these Joomla runtime checks.