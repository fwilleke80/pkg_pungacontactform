# Content embedding, editor button and automatic replies

## Installation

Install `pkg_pungacontactform_v0-2-0.zip` with Joomla's package installer. This package includes the existing component and site module plus two new plugins:

- **Content – Punga Contact Form** (`content/pungacontactform`)
- **Button – Punga Contact Form** (`editors-xtd/pungacontactform`)

Enable both plugins under **System → Plugins** after installation. Existing menu contact forms and modules remain unchanged.

## Placeholders

Insert these literally in Joomla articles or other content rendered via the `onContentPrepare` event:

```
{contactform}
{contactform layout=centered}
{contactform layout=left intro=no}
{contactform module=123}
{contactform module=123 layout=centered intro=no}
```

Only the three lowercase/uppercase-insensitive parameter names `module`, `layout`, and `intro` are recognized. Other or malformed parameters leave the original placeholder unchanged. No recipient, CAPTCHA, spam-filter or email identities can be supplied by an article. This prevents a content editor from changing protected mail configuration.

`module=123` resolves an actual published and currently accessible Punga Contact Form module. It inherits that module's settings; `layout` and `intro=no` can override appearance. If the specified module is not accessible, no form is rendered. The module must be published and, if applicable, assigned to the requested page. Placeholders without `module` inherit component-wide defaults. `intro=no` hides the introduction, but `intro=yes` simply retains its configured behavior.

Several instances per article are supported using distinct form context tokens and source identifiers. Embedded forms post to the existing Joomla contact controller and redirect back to the article. They reuse CAPTCHA, CSRF, spam scoring, honeypot, IP throttling, session context and Punga Analytics. Source kind `embed` is now explicitly recognized by the history and analytics subsystems.

**Caching:** Do not enable Joomla full-page caching, static HTML caching or CDN caching for pages that contain an active contact form. The renderer sets `Cache-Control: private, no-store` but cannot undo an upstream cache that serves a previously generated document. Cached one-time contexts will expire or belong to another session.

## Editor button

The **Contact Form** button uses Joomla's `onEditorButtonsSetup`/editor-api interface. Clicking it opens a small native dialog to choose a component/default form or published module configuration, Left/Centered/Inherited alignment, and whether to hide the introduction. Clicking **Insert form** writes the matching placeholder into the current editor. The button works only with editors supporting Joomla's editor API and insertion selection contract.

## Automatic acknowledgement replies

The existing sender copy sends the original message back to the visitor. The new acknowledgement instead sends **separate administrator-authored plain text**. Both features can be enabled, independently, in which case the visitor may receive two emails.

Configure under **Components → Punga Contact Form → Options → Message and presentation**:

1. Set **Public sender email address** and optional public name. This must be different from both the component default recipient and any menu/module recipient. It is also used for sender copies.
2. Set **Automatic acknowledgement** to Enabled.
3. Write **Acknowledgement subject** and **Acknowledgement message** (both nonempty).

Per-menu-item and per-module overrides can inherit, enable, disable or use custom text. Empty text overrides inherit the component values. Placeholder support in subject and body:

- `{name}`: submitted sender name
- `{subject}`: submitted original subject
- `{site_name}`: configured Joomla site name

Examples:

- Subject: `We received your message: {subject}`
- Body: `Hello {name},\n\nThank you for contacting {site_name}. We'll be in touch.` (enter real newlines in the textarea)

Automatic replies are only attempted **after the main contact mail is accepted by Joomla's transport**. They are suppressed on spam rejection, form validation errors or mail transport failure. Their outgoing identity is *always* the separate public sender; they have no recipient address, Reply-To to the private owner, CC or BCC. A reply-send failure is logged as a minimal warning and does not change the primary submission's success outcome. Neither the original message body nor the private recipient address is included in the acknowledgement unless the site administrator manually enters it in the configured template.

To prevent mail auto-response loops, choose a suitable no-reply mailbox as the public sender and configure appropriate SMTP anti-abuse policies. This feature only acknowledges a visitor's submitted contact form and does not respond to incoming emails.

## Manual Joomla verification

Test on an installed Joomla 6 site: package installation and enabling both plugins; modal insert into TinyMCE; placeholder rendered in an article; module selection accessibility; multiple forms; direct submit+redirect from an article; Joomla Proof-of-Work CAPTCHA; browser full-page cache disabled; plain-text acknowledgement with public sender; and legacy menu/module mail paths. These cannot be fully verified by static PHP/unit tests alone.
