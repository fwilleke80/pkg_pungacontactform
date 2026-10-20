# Changelog

## 0.2.0 — 2026-10-20

### Added
- Installable content plugin supporting `{contactform}` and optional `module=`, `layout=`, and `intro=` parameters. Embedding uses the existing secure renderer, server-side mail configuration, separate one-time form context, message history and Analytics events.
- Installable Joomla editors-xtd **Contact Form** button with a small native dialog to build and insert article placeholders from available module configurations and presentation settings.
- Optional configurable automatic acknowledgement emails with custom subject and message templates and `{name}`, `{subject}`, `{site_name}` substitutions. Component defaults may be overridden by menu items and modules. Sender copies and acknowledgements are independent.

### Security and compatibility
- Automatic acknowledgements use only the already configured public sender identity, never the private recipient address, and are attempted only after successful main mail dispatch.
- Article placeholders cannot configure recipients, CAPTCHA settings or spam scoring. Only published/accessible module configurations can be selected.
- Root README stays short; usage, caching caveats and technical details are in `docs/EMBEDDING_AND_REPLIES.md`.
- Existing contact menu-item, module, moderation, routing and analytics features remain in place. No database schema migration.

### Verification
- Static PHP, XML/INI, existing regression suite and isolated parser/automatic-reply scenarios passed; a live Joomla 6 install, plugin activation and browser/mail test remain required.

## 0.1.9 — 2026-10-20

### Fixed
- Replace the unreliable first-message UPDATE/INSERT/retry counter sequence with a single atomic upsert on MySQL/MariaDB and PostgreSQL. Commit the history record and its counter as one transaction, falling back to a retained history record on a counter error. First submissions and concurrent requests no longer depend on an unchecked retry.
- On opening the administrator Dashboard, reconcile undercounted daily totals against retained message history, including earlier 0.1.3–0.1.8 submissions whose history records survived but counters did not. Never decrease existing totals when records are deleted or expired.
- Report statistics persistence failures in the Joomla log without sender names, recipient addresses or message content; show administrators a warning if reconciliation fails, and a one-time notice when records have been repaired.
- Rename the sent-message status and Dashboard metric from “Accepted by mail transport” to **“Sent”** (German: “Gesendet”). This still means Joomla accepted the message for dispatch, not guaranteed inbox arrival.

### Notes
- No database schema migration or component-option changes are needed.
- Missing counts can only be reconstructed from message records still retained in the database. Older deleted or expired submissions cannot be recovered.
- Verified with isolated PHP/database-mock tests for MySQL, MariaDB and PostgreSQL; live Joomla installation, actual database concurrency and SMTP tests remain outstanding.


## 0.1.8 — 2026-10-12

### Fixed
- Respect Joomla menu item **Show Page Heading** and **Page Heading** in the standalone contact view. Render the heading as escaped text above the form; fall back to the browser/menu title when the heading is blank.
- Use Joomla's inherited `HtmlView::setDocumentTitle()` to apply **Browser Page Title** and the Global Configuration **Site Name in Page Titles** setting, including its configured name position.
- Align the optional page heading with the existing centered form column without changing module rendering or submitted form data.

### Notes
- No database migration or menu item recreation is necessary. The heading follows the existing menu Page Display settings. A live Joomla/Cassiopeia verification is still required.

## 0.1.7 — 2026-10-12
- Added Left and Centered form alignment to component Options, with optional per-menu-item and per-module overrides (Inherit / Left / Centered).
- Centered alignment positions the shared form within its parent area without changing field or text alignment, sender-copy handling, or submission behavior.
- The existing Left layout remains the default for upgrades and new installations.
- Added English/German option labels and layout regression tests.

## 0.1.6 — 2026-10-09

### Fixed
- Register a proper Joomla 6 component SEF router for the `contact` frontend view. A contact menu item now routes to its alias (for example, `/contact`) without the redundant `?view=contact` query parameter.
- Preserve Joomla's standard non-menu routing and existing form POST/redirect flows.
- Remove the informational note above the Dashboard counters and the explanatory paragraph below the recent messages table.

### Notes
- Existing contact menu items need no recreation. On sites with cached menu output or page cache, clear the cache after upgrading.
- Routing behavior still requires verification with Joomla SEF enabled on a running Joomla 6 site.

## 0.1.5 — 2026-10-09

### Security fix
- Do not expose the private recipient address via the `From`, `Sender` or reply headers of sender-copy emails. Previously copies reused Joomla's global mail From address, which could be private.
- Require an explicit public From address for all visitor copies. If missing, invalid or equal to the effective or global recipient address, copies are suppressed; for optional copies the frontend checkbox is hidden.
- Initialize copy mailers with a public From override **before** constructing the Joomla mailer, protecting the initial message sender/envelope configuration. Main contact messages remain unaffected.
- Add a separate, optional public copy sender display name. Copy errors are logged without private data.
- Add isolated tests for sender-copy privacy and mailer options; document third-party SMTP header-rewriting limitations in `docs/MAIL_DELIVERY.md`.

### Fixed
- Correct the Joomla switcher direction in **Options → Message history and retention**. The three message-body retention controls now use Joomla's expected option order (No = 0 first, Yes = 1 second), so turning a switch on displays Yes and saves 1, and turning it off displays No and saves 0. Existing saved choices retain their original meaning.

### Upgrade note
- **Administrator action required to retain sender copies:** Configure a public copy sender email address in Component Options → General. Without it, sender copies intentionally remain disabled to prevent exposure of private mailboxes.

## 0.1.4 — 2026-10-09

### Added
- Dashboard: installed version read from Joomla extension metadata, plus the ten most recent sent, rejected and failed submission records with direct detail links and navigation to the full message manager.
- Built-in spam rules and scoring weights shown in the **existing** Options → Spam Protection tab, alongside a link to the existing spam-test tool.

### Fixed
- Every classified submission now creates a history record even when body retention is disabled; the storage switches only govern message-body retention. Unvalidated security rejections are recorded without storing submitted text.
- Joomla Component Options title translates to "Punga Contact Form" in English and German (`COM_PUNGACONTACTFORM_CONFIGURATION`).
- Clearly identify message bodies that have not been retained.

### Notes
- No database schema changes are required for 0.1.4. Earlier submissions omitted under the 0.1.3 `store_sent` behavior cannot be reconstructed; cumulative counters survive archival, deletion and retention cleanup.
- Live Joomla 6 administrator/configuration view and mail-flow checks remain necessary.

## 0.1.3 — 2026-10-09

### Fixed
- Use Joomla 6's supported mailer factory and application configuration APIs, with mail-transport failure handling.
- Correct the required global recipient description; empty menu/module recipient overrides inherit it.
- Add the missing English and German menu-item overrides tab translation.
- Add Joomla's native Toggle Inline Help button to component Options.

### Added
- Administrator dashboard with persistent counts for sent, rejected and failed submissions.
- Searchable, sortable, paginated Joomla message history with detail pages and ACL-protected archive, unarchive and delete actions.
- Optional message-body retention with per-category storage options and automatic 1–365-day cleanup.
- Stored scoring contributions for content-filtered messages, plus a read-only list of built-in spam rules and their weights.
- Administrator-only spam scoring simulator; custom blocked phrases remain editable in component Options.
- Simple root README; technical documentation moved into docs/.

### Notes
- "Sent" means accepted by Joomla's mail transport; final mailbox delivery can be delayed or fail afterwards.
- Aggregate counters begin with this release and cannot reconstruct historical submissions.
- Runtime installation and SMTP testing on a real Joomla 6 site are still required.

## 0.1.2
- Added content spam-scoring rules, optional guest-only filtering, custom blocked phrases and Punga Analytics events.

## 0.1.0
- Initial Joomla 6 contact page and module, configurable recipient and copies, native CAPTCHA integration and layered form protections.
