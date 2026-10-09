# Architecture and integration

Punga Contact Form 0.1.4 is a Joomla 6 package containing a site component and a site module. Both frontends share one renderer, server-side setting resolution, CAPTCHA integration, and submission handler.

## Installation and setup

1. Install `pkg_pungacontactform_v0-1-4.zip` using **System → Install → Extensions**.
2. Open **Components → Punga Contact Form → Options** and configure the required global recipient. Optional menu and module recipients override it; blank overrides inherit the global setting.
3. Add a menu item of type **Punga Contact Form → Contact form** and/or publish the **Punga Contact Form** site module.
4. Configure Joomla's global mail transport and optional CAPTCHA plugin. Joomla's native Proof-of-Work CAPTCHA is supported where available.
5. Use the dashboard to review cumulative counters and the ten newest messages, with links to message details and the complete message list. Open **Options → Spam protection** for built-in rules, custom phrases, score thresholds and the spam testing tool.

## Form behavior

Visitors enter name, email, subject and message, with optional sender copy and Submit/Cancel controls. Registered users' Joomla name and email are prefilled but editable. The recipient address is never returned to the public browser. Per-instance settings can override copy mode, CAPTCHA mode, content-filter mode, introduction text and feedback text.

The contact handler validates the CSRF token, one-time session form context, IP rate limit, timing, honeypot, form fields, CAPTCHA and optional content spam classification before using Joomla's configured mailer factory. The user-provided sender is used only as Reply-To on the outgoing recipient message, while Joomla's global From identity is used as the actual mail sender. Requested sender copies are separate messages, not CC/BCC. Errors are not exposed to anonymous visitors.

## Content spam rules

The spam classifier is a deterministic scoring system (not an AI model). It checks link-only or link-heavy text, select spam terms and extreme repetitive/gibberish patterns. It offers **Off**, **Guests only** (default) and **All users** modes; mode can be overridden for each form. The default rejection threshold is 8 points. Strong spam phrase groups add +9 (only first matching group contributes); secondary phrases add +4 individually. Administrator-defined literal blocked phrases contribute +20, one matched phrase per message. The custom phrase list accepts at most 50 entries, 4–80 characters per phrase. Classification may produce false positives; use the administrator message list to review rejected content.

## Storage

Three independent daily outcome counters (`sent`, `rejected`, `failed`) survive deletion of individual messages. Counters start when 0.1.3 is installed. Every classified submission is recorded as history metadata (time, status, reason and source; sender and subject for validated messages) for the configured retention period. Body retention is independently configurable: disabled by default for successful submissions and enabled by default for content-filtered and failed messages. Other security failures create metadata-only records without retaining unvalidated submitted text. Retention defaults to 30 days and runs opportunistically as submissions occur. Archive state does not stop expiration; aggregate counters persist.

## Analytics integration

Joomla events: `onPungaContactFormSent` and `onPungaContactFormRejected`. Generic Punga Analytics event: `onPungaAnalyticsRecord`, with identifiers `contact.sent` and `contact.rejected`. Events carry form source type/id, visitor category, UTC timestamp, and rejection reason/score where applicable. They never carry sender/recipient addresses, names, IP addresses or submitted content. Mail transport failures are represented by local counters, not as successful Analytics events.

## Project tree

- `component/`: Joomla component, site and administrator MVC, SQL schema and upgrade migrations, translations and frontend style.
- `module/`: Site module manifest, entry point, template and translations.
- `package/`: Joomla package installer manifest.
- `tests/`: Static and PHP classifier/event tests.
- `docs/`: Architecture, administrator/security and mail-delivery documentation.
- `build.py`: Deterministic versioned ZIP builder. Run `python3 build.py` in the project root to create the installer and source archives under `dist/`.

Supported databases: MySQL/MariaDB and PostgreSQL. PHP 8.3+ and Joomla 6 are required. The static checks do not substitute for live Joomla installation, SMTP and browser tests.
