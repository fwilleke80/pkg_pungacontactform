# Mail delivery and sender privacy

## Recipient delivery

The main contact message uses Joomla's configured mail transport. The site's configured global From address is used for the **private message to the site recipient**; the visitor's supplied email is the Reply-To. The configured contact recipient is never put in the public form HTML.

## Sender copies (changed in 0.1.5)

Sender copies can be enabled as *off*, *optional* or *always*. **To protect the private recipient address, sender copies require an explicitly configured, public From address** in Components → Punga Contact Form → Options → General → **Public sender email address for copies**. An optional public display name can be configured separately. Use a mailbox or alias permitted by your SMTP provider, ideally on your site's authenticated sending domain (for example, `no-reply@example.com`).

- The Joomla global mail `From Email` is **never used as an implicit fallback** for sender copies. It may be the administrator's private address.
- If the public copy sender is absent, invalid, or identical to the component's global or the current form's overridden private recipient, copies are disabled. The optional checkbox is hidden. The main contact message continues to send. This is an intentional fail-closed change for existing installations upgrading from 0.1.4.
- The sender-copy mailer receives a separate, per-message Joomla `Registry` override for `mailfrom` and `fromname` **at creation time**, so the initial `From` / envelope sender comes from the public identity rather than the site's possibly private global mail settings. The copy has only the visitor in To, no CC/BCC, and no Reply-To to the private recipient.
- The main message can succeed even if a copy fails. Copy failures and privacy suppressions are logged without addresses or message content. The frontend success refers to submission to the site's mail transport, not to whether the optional copy arrived.

**Important limit:** SMTP relays and mailbox providers may independently insert or rewrite transport headers or an envelope sender, especially when authenticated with a personal mailbox. The extension cannot guarantee privacy against provider-inserted headers. Send an actual test and inspect the **full raw email headers** of the copy (From, Sender, Reply-To, Return-Path, Received and provider-specific headers). Consider using a public SMTP account or approved sender alias rather than authenticating with your private mailbox.

## Acceptance versus delivery

A successful Joomla mailer `send()` call confirms that the configured transport accepted the message, **not** that the destination inbox received it. Relays, greylisting and filtering can cause delays. Inspect timestamps in the `Received:` header chain and mail server logs to locate the delay. Immediate sending exceptions produce a failed outcome; sender-copy failures do not change the main message's accepted status.
