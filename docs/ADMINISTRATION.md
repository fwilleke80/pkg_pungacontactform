# Administration and spam diagnostics

The component dashboard shows the installed version from the Joomla extension manifest, a table of the ten newest message records with direct detail links, an entry point to the full list, and durable historical counters for submissions accepted by the mail transport (`sent`), security rejections (`rejected`), and mail-transport failures (`failed`). No counters can reconstruct submissions made before 0.1.3.

The message list uses Joomla administrator sorting, search, status/archive filters and pagination. `core.manage` is required to read messages; `core.edit.state` controls archiving; `core.delete` controls deletion. These records may contain sensitive text: grant management permission only to trusted administrators.

The message body is only stored according to the options: accepted messages disabled by default; content-filtered and transport-failed messages enabled by default. All submissions that reach an outcome are written as metadata-only history records; CAPTCHA, honeypot, timing and validation rejects are written without user-supplied text. The full body is included only when the corresponding retention option is enabled. Even with body retention off, validated sender/subject metadata is stored so the dashboard and list remain functional. Retention is 30 days by default, applied opportunistically during new submissions; archival does not exempt records. Deleted records do not change historical counters.

The three message-body retention switchers follow Joomla's standard behavior: **Yes (on) = 1, No (off) = 0**. Turning retention off only stops storing the body of future messages in that category; it does not disable the message-history record or delete existing bodies. Their defaults remain No for accepted messages and Yes for content-filtered or mail-transport-failed messages. Existing saved configuration values do not require a migration.

The existing **Options → Spam Protection** tab shows all built-in spam phrases and scoring weights as a read-only rule table; the **Test spam classifier** button there opens the existing administrator-only simulator. Strong phrases contribute +9 from the first matching strong group; secondary matches add +4 each. Custom phrases are literal text added in component Options (one phrase per line, at most 50 phrases of length 4–80), matching case-insensitively at word boundaries; any match adds +20. Separate link- and gibberish-related heuristics also contribute. The default rejection threshold is 8. The admin-only spam test view displays rule matches but does not send email, save content, or generate analytics events.

The configured recipient is never included in public markup. Punga Analytics receives only safe status metadata without personal data. Backend message contents must be treated as untrusted input and rendered as escaped text.

## Contact form alignment

Under **Options → Message and presentation → Form alignment**, select **Left** (default) or **Centered**.
Each contact-form menu item and module instance has its own **Form alignment** setting with **Inherit component setting**, **Left**, and **Centered** values. Existing installations and items remain left-aligned unless this is changed.

Centered alignment moves the complete form into the horizontal center of its containing area (page content or module position), while preserving the form’s responsive maximum width of 38 rem. Input labels, text, buttons, and fields retain their usual left alignment. No extra frontend JavaScript is used.

## Contact page title and visible heading

The standalone **Punga Contact Form → Contact form** menu item follows Joomla's built-in **Page Display** settings:

- **Show Page Heading** controls the presence of an HTML `<h1>` above the form.
- **Page Heading** specifies its text; if empty, the browser/menu title is used.
- **Browser Page Title** determines the page's document title. If empty, the menu item's title is used.
- **Global Configuration → SEO → Site Name in Page Titles** controls whether Joomla adds the site name, and whether it appears before or after the page title. This uses the framework's `HtmlView::setDocumentTitle()` without hardcoded separators.

The heading is escaped as plain text. When the form is configured **Centered**, the heading follows the same 38-rem centered column as the form, but the heading text itself remains left aligned. The module never modifies its host page's heading or browser title.

No menu item needs to be recreated to activate the fix. If old page titles or markup appear after upgrading, clear Joomla's page cache and relevant frontend caches.
