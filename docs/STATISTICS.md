# Submission statistics and recovery

Punga Contact Form records each classified form outcome in two independent places:

- `#__pungacontactform_messages`: Retained message history, with optional message bodies and configurable expiration.
- `#__pungacontactform_counts`: Daily cumulative counters for `sent`, `rejected` and `failed`. These survive normal message deletion and expiration.

## What “Sent” means

The main contact message was handed to Joomla's configured mailer, and `send()` did not report an error. Downstream SMTP queueing, recipient server acceptance and final inbox delivery are not confirmed by Joomla. Sender-copy delivery is separate and does not change the main outcome.

## Atomic counts

The counter writer uses the existing composite primary key (`day`, `status`) and one atomic database statement per submission:

- MySQL/MariaDB: `INSERT ... ON DUPLICATE KEY UPDATE total = total + 1`.
- PostgreSQL: `INSERT ... ON CONFLICT (day, status) DO UPDATE SET total = total + 1`.

The first-insert and concurrent-update cases do not require reading the current total, nor do they rely on a caught exception to infer a race. Each retained message record and its increment are committed together in a database transaction; consequently, the Dashboard normally never observes a message that is not yet included in the counter. If this transaction fails, the extension rolls it back and attempts to retain the message metadata separately, enabling later reconciliation. Errors are logged with exception class and sanitized code only, not form data or recipient details. A statistics failure does not undo an email already handed to the transport.

## Reconciliation on administrator Dashboard

When opening the Dashboard, the extension groups **retained** message records by UTC day and status and compares those figures with the corresponding lifetime daily counters. Where a counter is too small or missing, an atomic `GREATEST(existing, retained)` upsert corrects it. A Joomla administrator notice reports how many daily entries were repaired. Reconciliation problems are reported as an administrator warning and a privacy-safe log entry.

Reconciliation is monotonic: it never decreases totals. It can repair missing counts only for submissions whose history entries still exist. If the retention period has expired, or entries were manually deleted, old submissions cannot be reconstructed from the remaining data. Consequently, totals from old releases may remain undercounted for expired history.

Message history and statistics use no public endpoint, and the Dashboard is subject to the existing `core.manage` permission check.
