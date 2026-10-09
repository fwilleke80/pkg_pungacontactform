# Punga Contact Form — Joomla 6

Release 0.1.2. One package installs a standalone contact form component (`com_pungacontactform`) and a site module (`mod_pungacontactform`). Requires Joomla 6, PHP 8.3+, and a functioning Joomla Mail configuration. Supports MySQL/MariaDB and PostgreSQL.

## Build/install

Run `python3 build.py` in this repository. It produces `dist/pkg_pungacontactform_v0-1-2.zip` (upload **this** package in System → Install → Extensions) plus two standalone sub-extension ZIPs inside `build/`.

**Use the top-level package ZIP only. Do not separately install its children unless you specifically intend a partial installation.**

## Setup

1. After installing, open Components → Punga Contact Form → Options. Configure **Recipient email address** and the optional presentation/copy/CAPTCHA settings. Mail uses Joomla's global From address and transport, with the user's email as Reply-To.
2. Make a menu item: Menu → New → Menu Item Type → Punga Contact Form → Contact form. Optionally override its recipient and settings in the menu item.
3. To embed the form elsewhere, create and publish a Site module of type **Punga Contact Form**. Configure its own recipient, sender-copy choice, text, status messages, and CAPTCHA override.
4. A logged-in visitor's name/email are prefilled but editable. Submit sends plain-text email; Cancel navigates home (component) or clears the inputs (module).
5. To enable CAPTCHA, enable a Joomla-compatible CAPTCHA plugin (Joomla 6.1+ includes the privacy-friendly **Proof-of-Work CAPTCHA** built in), configure it in **System → Global Configuration → Site → Default CAPTCHA**, and set Punga Contact Form's CAPTCHA mode to **Require** (recommended for public forms). `Auto` uses Joomla CAPTCHA when one is configured. `Off` disables CAPTCHA only; other protections remain.

## Content spam filter (0.1.1)

Component Options → **Spam protection → Content spam filter** chooses **Off**, **Guests only** (default) or **All users**. A menu item or module can override this mode (or inherit). "Guests" means Joomla's actual authenticated user state at submission time, not a visitor-provided form field. Logged-in users bypass only the message-content heuristics when **Guests only** is chosen: CAPTCHA, CSRF, honeypot, timing, rate limiting and field validation still run for everyone.

The filter examines submitted subject and body, using a **score**, not an AI model or remote API. Heuristics detect link-only messages (including bare common-domain URLs), very short text surrounding a link, multiple URLs, highly specific promotional phrases (e.g. paid backlinks, online casino, Viagra), and conservative patterns of repeated / machine-generated characters. Legitimate inquiries containing an ordinary URL are normally allowed. It cannot reliably identify every form of nonsense and may occasionally produce a false positive.

**Spam score threshold** is 8 by default (lower = stricter; supported 4–20). **Additional blocked phrases** accepts one literal phrase per line, to a maximum of 50 entries with 4–80 characters each. Such phrases force a discard; avoid broad words like "contact", "website" or "help". The threshold and custom list are configured globally, while the mode may also be overridden on individual menus/modules.

When a valid submission is classified as spam, **the extension sends no email at all, including no sender copy**, and displays the normal configured success message. This avoids disclosing filtering rules to automated senders. Message content is never stored or logged; when Analytics events are enabled, rejection reason and spam score are emitted without message content. Misclassification means the message is irrecoverably lost, so use the filter conservatively and test with real inquiries before enabling stricter settings.

## Punga Analytics integration (0.1.2)

The component emits two domain-level Joomla events, using `Joomla\Event\Event` with a `data` argument:

- `onPungaContactFormSent` — after Joomla's mail transport **accepts the recipient message**. A failed sender copy does not cancel this event. "Sent" does not guarantee that the destination mailbox finally received or opened the mail.
- `onPungaContactFormRejected` — for requests failing content filtering, honeypot, minimum time, input validation or CAPTCHA, after passing the per-IP quota. Rejections due to missing/expired session context, invalid CSRF tokens, quota exhaustion and underlying infrastructure failures are **not** recorded, to avoid unauthenticated events flooding Analytics. Rejections are suspicious, **not all confirmed spam**.

Each occurrence also emits `onPungaAnalyticsRecord` with `event_type` **`contact.sent`** or **`contact.rejected`**, `component` **`com_pungacontactform`**, `view` **`contact`**, `item_id` (the menu or module ID), and `data` (the same safe metadata as the domain event). Punga Analytics must have these two identifiers enabled as Custom Events for them to appear in reports. No modifications to Punga Analytics are bundled here.

The `data` argument includes `source` (`menu` / `module`), `source_id`, `visitor_type` (`guest` / `registered`) and `timestamp_utc`. Rejected events also carry `reason` (`content_filter`, `honeypot`, `too_fast`, `validation`, `captcha`) and a numeric `spam_score` only for `content_filter`. It **never** includes recipient or visitor email, visitor name, message text, subject, raw IP, links, or personal IDs. Dispatch failures are isolated; a broken Analytics listener cannot cancel a successful recipient delivery.

## Security & privacy

- The configured recipient address is **never rendered in the HTML**, nor included in hidden fields, status messages or email copies. It resides only in component/menu/module server-side settings and a protected server session. It is necessarily visible to authorized Joomla administrators who can edit those settings.
- CSRF protection uses Joomla form tokens. An unpredictable 40-character, single-use context token is bound to private session configuration and the return path for 30 minutes. Redirect destinations are server-generated, local paths, not user-supplied URLs.
- Inputs are validated server-side; names and subjects are stripped of control characters and lengths are bounded. Output is HTML-escaped. Admin text options render as **plain text**, not raw HTML. Email is sent as plain text; no user-controlled HTML is executed.
- A hidden honeypot field, a minimum 3-second completion time, Joomla CAPTCHA integration and DB-backed per-IP hourly attempt quota (default 10/hour) provide layered spam resistance. Quotas are applied before expensive CAPTCHA/mail operations. All failed submissions show a generic/custom error (never internal mail errors).
- Rate-limit data records only an HMAC-SHA256 digest of the connecting IP keyed by Joomla's site secret, the UTC hour bucket and the attempt counter; old buckets are opportunistically purged after 48 hours. No plaintext IP is stored.
- Sender copies are sent as separate emails with only the original visitor as recipient, never as CC/BCC of the destination message. A separate copy delivery error does not invalidate a successful original delivery.
- **Caching:** A form depends on a server-side session and uses `Cache-Control: private, no-store`. Exclude contact pages and pages containing the contact module from aggressive full-page caching or CDN HTML caching; Joomla's Page Cache plugin can otherwise serve another visitor's expired form token.
- CAPTCHA availability depends on the selected Joomla plugin. Test that plugin after installation, including invisible CAPTCHA variants. The extension cannot guarantee a third-party CAPTCHA plugin is working correctly.

## Behavior and limitations

- Email addresses are checked by PHP's mail syntax validator; the Joomla SMTP/email transport must be configured separately.
- One component-wide default plus optional per-menu-item / per-module recipient and content overrides. A blank text override inherits the component default.
- Failed submissions restore the previous fields for the relevant module or menu after redirect (except CAPTCHA response and honeypot). The server validates the CAPTCHA again on each request.
- Subject, name, body limits: 200 / 120 / 20,000 characters.
- No attachments, storage of correspondence, frontend mail addresses or external services are required. Actual SMTP sending is not testable without a Joomla instance.

## Source tree

- `component/`: Joomla component manifest, site/admin sources, translations, styles, database schema.
- `module/`: Joomla site module and its translations.
- `package/`: top-level package manifest.
- `build.py`: reproducible deterministic ZIP packaging and manifest checks.
- `tests/`: source/manifest/language checks.

License: GPL-2.0-or-later.
