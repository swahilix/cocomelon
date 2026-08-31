# Business Mail Service Implementation Plan

## 1. Project Goal

Build a professional business email experience directly inside the Cocomelon / Prinx application so authorized staff can manage company email without leaving the system.

The mail service will provide:

- Inbox
- Drafts
- Outbox
- Sent
- Contact Book
- Bin / Trash
- Email compose and reply
- Attachments
- Search
- Threaded conversations
- Read / unread state
- Starred / important messages
- Email signatures
- Background synchronization
- Reliable queued sending
- Multiple business mailboxes where required

The first version should feel like a lightweight business-focused Gmail / Outlook experience while remaining simple, fast, and easy to maintain.

---

# 2. Core Architecture Decision

## 2.1 Do Not Build a Mail Server From Scratch

The application should **not** attempt to become its own SMTP/IMAP mail server.

Instead, Prinx should work as a webmail client connected to the company's existing business email provider.

Examples of compatible providers:

- cPanel email
- Namecheap Private Email
- Zoho Mail
- Google Workspace
- Microsoft 365
- Any provider supporting IMAP + SMTP

## 2.2 Recommended Transport Architecture

### Incoming mail

Use **IMAP** to:

- synchronize Inbox
- synchronize Sent
- synchronize Trash
- synchronize server folders
- retrieve message flags
- detect read/unread state
- retrieve attachments and message bodies

### Outgoing mail

Use **SMTP** to:

- send new email
- reply
- reply all
- forward
- send attachments

### Application database

The local application database will store a synchronized/indexed representation of mailbox data for fast UI performance, search, threading, contact suggestions, audit history, and reduced IMAP load.

The external mail server remains the source of truth for delivered email.

---

# 3. Recommended Laravel Technology

The application currently uses Laravel + Inertia + Vue.

Recommended components:

- Laravel queues for synchronization and sending
- Laravel scheduler for periodic mailbox sync
- Redis or database queue in production
- PHP IMAP library or a maintained Laravel-compatible IMAP client
- Laravel Mail / Symfony Mailer for SMTP
- Laravel encrypted casts or encrypted credential service
- Vue 3 + Inertia for the webmail interface
- MySQL full-text search initially
- Optional Meilisearch / Laravel Scout later for advanced search

Important rule:

Mail credentials must NEVER be stored in plain text.

---

# 4. Mail Module Navigation

Add a new main navigation item:

`Mail`

Opening Mail should display a dedicated mail workspace.

Recommended left sidebar:

1. Compose
2. Inbox
3. Starred
4. Drafts
5. Outbox
6. Sent
7. Contacts
8. Bin
9. Settings

Optional future folders:

- Archive
- Spam
- Custom IMAP folders
- Labels

---

# 5. Mailbox Account Management

## 5.1 Supported mailbox model

The system should support one or more business mailboxes.

Examples:

- info@company.com
- sales@company.com
- support@company.com
- accounts@company.com

Each mailbox can be assigned to:

- one user
- multiple staff members
- one department
- administrators only

## 5.2 Mailbox settings

Each mailbox should store:

- Display name
- Email address
- Provider name
- IMAP hostname
- IMAP port
- IMAP encryption
- IMAP username
- encrypted IMAP password
- SMTP hostname
- SMTP port
- SMTP encryption
- SMTP username
- encrypted SMTP password
- From name
- Reply-to address
- Default signature
- Sync enabled
- Send enabled
- Last sync time
- Last successful connection time
- Last connection error
- mailbox status

## 5.3 Connection testing

Admin settings must provide:

- Test IMAP Connection
- Test SMTP Connection
- Save only after validation, or allow save with warning
- Clear error messages when credentials or ports are incorrect

---

# 6. Roles and Permissions

Create granular mail permissions.

Suggested permissions:

- mail.view
- mail.compose
- mail.send
- mail.reply
- mail.forward
- mail.delete
- mail.restore
- mail.manage_contacts
- mail.manage_mailboxes
- mail.manage_settings
- mail.view_all_mailboxes

Examples:

### Administrator

Can access all configured company mailboxes and mail settings.

### Sales Staff

Can access only assigned sales mailbox.

### Support Staff

Can access only assigned support mailbox.

### Restricted User

Can view and reply but cannot permanently delete messages or change mailbox settings.

All mailbox access must be authorization checked server-side.

---

# 7. Database Design

## 7.1 `mail_accounts`

Suggested fields:

- id
- name
- email
- provider
- imap_host
- imap_port
- imap_encryption
- imap_username
- imap_password_encrypted
- smtp_host
- smtp_port
- smtp_encryption
- smtp_username
- smtp_password_encrypted
- from_name
- reply_to
- signature_html
- sync_enabled
- send_enabled
- last_synced_at
- last_connection_at
- last_error
- status
- created_at
- updated_at

---

## 7.2 `mail_account_user`

Used to assign mailboxes to application users.

Fields:

- id
- mail_account_id
- user_id
- can_read
- can_send
- can_delete
- can_manage
- created_at
- updated_at

---

## 7.3 `mail_folders`

Fields:

- id
- mail_account_id
- remote_name
- display_name
- type
- delimiter
- uid_validity
- last_uid
- sync_token / sync metadata
- created_at
- updated_at

Folder type values:

- inbox
- drafts
- outbox
- sent
- trash
- spam
- archive
- custom

---

## 7.4 `mail_messages`

Fields:

- id
- mail_account_id
- mail_folder_id
- remote_uid
- message_id
- in_reply_to
- thread_key
- from_name
- from_email
- reply_to_email
- subject
- preview_text
- body_text
- body_html
- received_at
- sent_at
- size
- has_attachments
- is_read
- is_starred
- is_important
- is_draft
- is_deleted
- headers_json
- synced_at
- created_at
- updated_at

Indexes:

- mail_account_id
- mail_folder_id
- remote_uid
- message_id
- thread_key
- from_email
- received_at
- is_read
- subject

Unique rule:

`mail_account_id + mail_folder_id + remote_uid`

---

## 7.5 `mail_recipients`

Fields:

- id
- mail_message_id
- type (`to`, `cc`, `bcc`)
- name
- email

---

## 7.6 `mail_attachments`

Fields:

- id
- mail_message_id
- remote_attachment_id / part_number
- original_name
- mime_type
- size
- storage_disk
- storage_path
- content_id
- is_inline
- created_at
- updated_at

Large attachments should be downloaded lazily when practical instead of storing everything immediately.

---

## 7.7 `mail_drafts`

A dedicated draft table is recommended instead of treating unsent drafts as normal synchronized messages.

Fields:

- id
- mail_account_id
- user_id
- reply_to_message_id nullable
- forward_of_message_id nullable
- subject
- body_html
- body_text
- to_json
- cc_json
- bcc_json
- scheduled_at nullable
- last_saved_at
- created_at
- updated_at

---

## 7.8 `mail_outbox`

Tracks pending and failed email.

Fields:

- id
- mail_account_id
- user_id
- draft_id nullable
- status
- attempts
- available_at
- processing_at
- sent_at
- failed_at
- last_error
- smtp_message_id
- payload_json
- created_at
- updated_at

Statuses:

- queued
- sending
- sent
- failed
- cancelled

---

## 7.9 `mail_contacts`

Fields:

- id
- owner_user_id nullable
- mail_account_id nullable
- first_name
- last_name
- company
- job_title
- email
- secondary_email
- phone
- secondary_phone
- address
- notes
- avatar_path nullable
- source (`manual`, `email`, `import`)
- is_favorite
- created_at
- updated_at

---

## 7.10 `mail_contact_groups`

Fields:

- id
- name
- description
- owner_user_id nullable
- created_at
- updated_at

Add pivot:

`mail_contact_group_contact`

---

## 7.11 `mail_audit_logs`

Recommended for business accountability.

Record:

- mailbox opened
- message sent
- message deleted
- message restored
- mailbox settings changed
- credentials changed
- attachment downloaded

Fields:

- id
- user_id
- mail_account_id
- mail_message_id nullable
- action
- metadata_json
- ip_address nullable
- user_agent nullable
- created_at

---

# 8. Inbox Implementation

The Inbox page should provide a professional three-area layout on desktop:

1. folder/sidebar
2. message list
3. reading pane

On smaller screens, these become separate views.

Inbox message rows should show:

- sender
- subject
- short preview
- attachment icon
- received time/date
- read/unread state
- starred state

Actions:

- open
- mark read
- mark unread
- star/unstar
- reply
- reply all
- forward
- move to bin
- permanently delete where permitted

Bulk actions:

- select all
- mark read
- mark unread
- star
- move to bin

---

# 9. Message Reading View

Display:

- sender name
- sender email
- recipient information
- subject
- timestamp
- message body
- attachments
- conversation thread

Actions:

- Reply
- Reply all
- Forward
- Star
- Mark unread
- Download attachments
- Print
- Move to bin

Security requirements:

- sanitize HTML email bodies
- prevent script execution
- block unsafe iframe/embed content
- proxy or optionally block remote tracking images
- never render arbitrary JavaScript from email

---

# 10. Compose Mail

Compose screen should support:

- From mailbox selector
- To
- Cc
- Bcc
- Subject
- Rich text editor
- attachments
- signature
- Send
- Save Draft
- Delete Draft

Optional future enhancements:

- scheduled sending
- templates
- email snippets
- read receipts if provider-supported

## Recipient autocomplete

Autocomplete should search:

- contact book
- previously contacted recipients
- company users where appropriate

---

# 11. Drafts

Draft behavior:

- auto-save while typing
- debounce save requests
- save recipient changes
- save attachments
- show last saved state
- drafts survive browser refresh
- opening a draft restores the exact compose state
- deleting draft sends it to Bin or removes it based on chosen behavior

Recommended auto-save interval:

- after short typing inactivity
- and before page navigation where possible

---

# 12. Outbox

Outbox is especially important for reliability.

When user presses Send:

1. validate recipients
2. validate mailbox permission
3. validate attachment limits
4. create Outbox record
5. dispatch queued send job
6. show message as `Queued`
7. queue worker sends through SMTP
8. on success mark as `Sent`
9. synchronize/add remote Sent copy as needed
10. on failure retry automatically

Outbox statuses visible to users:

- Queued
- Sending
- Failed
- Retry scheduled

Failed messages should provide:

- Retry
- Edit and resend
- Cancel
- readable failure reason

Never make the HTTP request wait for SMTP delivery.

---

# 13. Sent Mail

Sent view should show all successfully sent messages.

It should synchronize with the server's Sent folder so messages sent outside Prinx can also appear where supported.

Features:

- recipient
- subject
- sent date
- attachment indicator
- search
- forward
- resend
- move to bin

---

# 14. Contact Book

## Contact list

Provide:

- search
- alphabetic browsing
- favorites
- groups
- add contact
- edit contact
- delete contact

## Contact detail view

Show:

- name
- company
- job title
- email addresses
- phone numbers
- notes
- recent email history

Actions:

- Send Email
- Edit
- Add to Group
- Delete

## Automatic contact suggestions

When email is sent to a new recipient, optionally add it to a `Recent contacts` dataset.

Do not automatically pollute the main contact book without user control.

## Import/export later

Future support:

- CSV import
- CSV export
- vCard import
- vCard export

---

# 15. Bin / Trash

Messages moved to Bin should not immediately be permanently destroyed.

Functions:

- view deleted messages
- restore
- permanently delete
- empty bin

Recommended retention rule:

- follow the actual mail provider's Trash folder policy
- optionally run a local retention cleanup after a configurable number of days

Permanent deletion requires explicit confirmation.

---

# 16. Mail Synchronization Engine

Create jobs such as:

- `SyncMailAccountJob`
- `SyncMailFolderJob`
- `SyncMailMessageJob`
- `SyncMailFlagsJob`
- `SendMailMessageJob`
- `DeleteRemoteMailMessageJob`
- `MoveRemoteMailMessageJob`

## Incremental synchronization

Do not download every email during every sync.

Track:

- UIDVALIDITY
- last synchronized UID
- folder state
- message flags

Retrieve only new or changed messages.

## Scheduler

Example schedule:

- active Inbox: every 1-2 minutes
- Sent/Trash: every 5 minutes
- full reconciliation: periodically

The exact frequency should be configurable.

## Manual refresh

Inbox should include a refresh button that triggers a safe on-demand sync.

---

# 17. Threaded Conversations

Messages should be grouped into conversations using:

- `Message-ID`
- `In-Reply-To`
- `References`
- normalized subject fallback

Conversation UI should display the newest relevant message while allowing previous messages to expand/collapse.

This gives users a Gmail-like experience instead of displaying replies as unrelated emails.

---

# 18. Search

Search should support:

- sender
- recipient
- subject
- body
- attachment filename
- date range
- has attachment
- unread
- starred
- mailbox

Initial implementation:

- MySQL indexes + FULLTEXT where suitable

Future high-volume implementation:

- Laravel Scout + Meilisearch

---

# 19. Attachments

Requirements:

- display filename
- display file size
- MIME-type validation
- download individual attachment
- download all where practical
- preview images and PDFs where safe

Upload limits must be configurable.

Potentially dangerous files should not execute in-browser.

Storage strategy:

- temporary local/private storage
- never public disk for confidential email attachments
- signed/authorized download endpoints
- automatic cleanup for cached remote attachments

---

# 20. Email Signatures

Each mailbox can have a default signature.

Optional per-user override.

Signature fields can include:

- employee name
- position
- company
- phone
- website
- logo
- physical address

Support HTML signatures but sanitize stored markup.

---

# 21. Notifications

When new mail arrives:

- update unread count
- show notification badge in app navigation
- optionally show browser notification

Future options:

- notifications only for assigned mailbox
- notifications only for important sender/domain
- quiet hours

---

# 22. UI / UX Direction

The mail UI should match the existing Prinx application but be optimized for high information density.

Design principles:

- clean
- compact
- professional
- minimal unnecessary cards
- minimal border radius
- clear typography hierarchy
- fast keyboard/mouse workflow
- responsive

Desktop layout recommendation:

`Folder Sidebar | Message List | Reading Pane`

Compose can open as:

- full page initially
- optionally floating compose window later

Important mail counters should be visible beside folders:

- Inbox unread
- Draft count
- Outbox pending/failed

---

# 23. Backend Routes

Recommended route group:

```text
/mail
/mail/inbox
/mail/starred
/mail/drafts
/mail/outbox
/mail/sent
/mail/bin
/mail/contacts
/mail/settings
```

Suggested endpoint responsibilities:

```text
GET    /mail
GET    /mail/messages
GET    /mail/messages/{message}
POST   /mail/messages/{message}/read
POST   /mail/messages/{message}/unread
POST   /mail/messages/{message}/star
POST   /mail/messages/{message}/unstar
POST   /mail/messages/{message}/trash
POST   /mail/messages/{message}/restore
DELETE /mail/messages/{message}

GET    /mail/compose
POST   /mail/send

GET    /mail/drafts
POST   /mail/drafts
PATCH  /mail/drafts/{draft}
DELETE /mail/drafts/{draft}

GET    /mail/outbox
POST   /mail/outbox/{item}/retry
POST   /mail/outbox/{item}/cancel

GET    /mail/contacts
POST   /mail/contacts
PATCH  /mail/contacts/{contact}
DELETE /mail/contacts/{contact}

POST   /mail/sync
```

Mailbox settings should be admin-only.

---

# 24. Proposed Backend Classes

Controllers:

```text
app/Http/Controllers/Mail/MailboxController.php
app/Http/Controllers/Mail/MessageController.php
app/Http/Controllers/Mail/ComposeController.php
app/Http/Controllers/Mail/DraftController.php
app/Http/Controllers/Mail/OutboxController.php
app/Http/Controllers/Mail/ContactController.php
app/Http/Controllers/Mail/MailSettingsController.php
```

Services:

```text
app/Services/Mail/ImapService.php
app/Services/Mail/SmtpService.php
app/Services/Mail/MailSyncService.php
app/Services/Mail/MailThreadService.php
app/Services/Mail/MailSearchService.php
app/Services/Mail/MailAttachmentService.php
app/Services/Mail/MailCredentialService.php
```

Jobs:

```text
app/Jobs/Mail/SyncMailAccountJob.php
app/Jobs/Mail/SyncMailFolderJob.php
app/Jobs/Mail/SendMailMessageJob.php
app/Jobs/Mail/MoveMailMessageJob.php
app/Jobs/Mail/DeleteMailMessageJob.php
```

Policies:

```text
app/Policies/MailAccountPolicy.php
app/Policies/MailMessagePolicy.php
app/Policies/MailContactPolicy.php
```

---

# 25. Proposed Vue Pages

```text
resources/js/Pages/Mail/Index.vue
resources/js/Pages/Mail/Inbox.vue
resources/js/Pages/Mail/Message.vue
resources/js/Pages/Mail/Compose.vue
resources/js/Pages/Mail/Drafts.vue
resources/js/Pages/Mail/Outbox.vue
resources/js/Pages/Mail/Sent.vue
resources/js/Pages/Mail/Bin.vue
resources/js/Pages/Mail/Contacts/Index.vue
resources/js/Pages/Mail/Contacts/Show.vue
resources/js/Pages/Mail/Settings/Index.vue
```

Reusable components:

```text
resources/js/Components/Mail/MailSidebar.vue
resources/js/Components/Mail/MessageList.vue
resources/js/Components/Mail/MessageListItem.vue
resources/js/Components/Mail/MessageReader.vue
resources/js/Components/Mail/ComposeEditor.vue
resources/js/Components/Mail/RecipientInput.vue
resources/js/Components/Mail/AttachmentList.vue
resources/js/Components/Mail/MailToolbar.vue
resources/js/Components/Mail/ThreadView.vue
resources/js/Components/Mail/MailboxSwitcher.vue
```

---

# 26. Security Requirements

This module handles sensitive business communication and must receive stricter security treatment than ordinary storefront pages.

Mandatory requirements:

1. Encrypt IMAP/SMTP passwords at rest.
2. Never return credentials to frontend pages.
3. Require authorization for every mailbox/message action.
4. Sanitize HTML mail bodies.
5. Disable JavaScript inside email bodies.
6. Protect attachment downloads with authenticated routes.
7. Validate attachment MIME types and size.
8. Use CSRF protection for all mutations.
9. Rate-limit send endpoints.
10. Log security-sensitive actions.
11. Never log mailbox passwords.
12. Never include SMTP credentials in exception responses.
13. Use TLS for IMAP and SMTP in production.
14. Protect against email header injection.
15. Validate recipient addresses server-side.
16. Prevent open redirects from email content.
17. Consider blocking remote image loading by default for privacy.
18. Add session/permission checks before showing email notification data.

---

# 27. Reliability Requirements

Mail sending must be resilient.

Use:

- queue retries
- exponential backoff
- failed job tracking
- SMTP timeout handling
- idempotency keys where necessary
- message send state machine

Avoid duplicate sending after retry.

A message that has already received a successful SMTP response must never be re-sent accidentally simply because a later synchronization step failed.

---

# 28. Logging and Monitoring

Track:

- successful mail sync
- failed mail sync
- SMTP failures
- authentication failures
- queue failure count
- mailbox sync duration
- messages synchronized
- messages queued
- messages sent

Admin settings should show:

- connected/disconnected status
- last sync
- last successful send
- last error

---

# 29. Testing Strategy

## Unit tests

Test:

- mail threading
- recipient normalization
- credential encryption
- folder mapping
- email address validation
- send state transitions
- search filters

## Feature tests

Test:

- authorized staff can open assigned mailbox
- unauthorized user cannot access another mailbox
- user can create draft
- draft autosave endpoint works
- user can queue email
- successful queue marks mail sent
- failed send enters failed outbox state
- retry works without duplicate send
- messages can move to Bin
- messages can be restored
- contacts CRUD
- attachment authorization
- mailbox settings restricted to administrators

## Integration tests

Use mocked IMAP and SMTP adapters where possible.

A controlled test mailbox can be used for staging end-to-end tests.

---

# 30. Deployment Requirements

Production needs:

- PHP extensions required by selected IMAP library
- queue worker
- Laravel scheduler cron
- secure environment variables
- outbound SMTP connectivity
- outbound IMAP connectivity
- private attachment storage
- HTTPS

Queue worker example architecture:

```text
web request -> database/redis queue -> mail worker -> SMTP
```

Synchronization:

```text
Laravel scheduler -> sync job -> IMAP -> local mail index
```

---

# 31. Implementation Phases

## Phase 1 — Foundation

Tasks:

- install/choose maintained IMAP package
- create mail configuration
- create database migrations
- create models and relationships
- create permission structure
- implement encrypted mailbox credentials
- implement admin mailbox configuration
- add IMAP/SMTP connection test

Deliverable:

A business mailbox can be configured securely and successfully connected.

---

## Phase 2 — IMAP Synchronization

Tasks:

- build IMAP service abstraction
- discover folders
- map Inbox/Sent/Trash/Drafts
- synchronize messages
- synchronize flags
- store recipients
- build attachment metadata
- incremental UID-based sync
- scheduler jobs
- manual refresh

Deliverable:

Real incoming business email appears in the application Inbox.

---

## Phase 3 — Inbox and Reader UI

Tasks:

- mail layout
- folder sidebar
- unread counters
- message list
- reading pane
- responsive views
- read/unread actions
- star actions
- Bin action
- thread view

Deliverable:

Users can comfortably read and organize business email.

---

## Phase 4 — Compose, Drafts and SMTP

Tasks:

- rich compose editor
- recipient autocomplete
- Cc/Bcc
- attachments
- signatures
- draft create/update/delete
- autosave
- SMTP sending service
- Outbox records
- queued send job
- retries
- failed send UI
- Sent synchronization

Deliverable:

Users can reliably send and reply to business email.

---

## Phase 5 — Contact Book

Tasks:

- contacts CRUD
- groups
- favorites
- autocomplete integration
- recent correspondents
- contact message history

Deliverable:

Users have a useful integrated company contact book.

---

## Phase 6 — Search and Productivity

Tasks:

- global mail search
- advanced filters
- mailbox filtering
- date filters
- attachment filters
- keyboard shortcuts where useful
- bulk actions

Deliverable:

Large mailboxes remain easy to navigate.

---

## Phase 7 — Security Hardening

Tasks:

- HTML sanitization review
- remote-image privacy handling
- attachment security
- permission penetration tests
- send rate limits
- audit logs
- credential leakage review
- queue idempotency review

Deliverable:

Mail is safe enough for production business communication.

---

## Phase 8 — Monitoring and Production Readiness

Tasks:

- mailbox health UI
- sync failure reporting
- queue monitoring
- failed jobs handling
- connection status
- operational documentation
- backup strategy
- production smoke tests

Deliverable:

The mail module can operate reliably in production.

---

# 32. Recommended MVP Scope

The first production-ready release should include:

- one or multiple business mailbox accounts
- mailbox assignment to staff
- Inbox
- Drafts
- Outbox
- Sent
- Contacts
- Bin
- compose
- reply
- reply all
- forward
- attachments
- signatures
- read/unread
- starred messages
- thread view
- search
- background IMAP sync
- queued SMTP sending
- retry failed sends
- responsive UI
- security/audit controls

Do not delay MVP for:

- calendar integration
- AI email writing
- advanced labels
- complex email rules
- read receipts
- marketing campaign features

Those can be added later.

---

# 33. Future Enhancements

After the core system is stable:

## Mail templates

Reusable templates for:

- quotations
- invoices
- order updates
- customer support
- warranty replies
- service completion

## Internal assignment

Allow a shared inbox email to be assigned to a staff member.

Example:

`support@... -> Assigned to John`

## Internal notes

Staff can discuss an email internally without sending those notes to the customer.

## Status workflow

For support/sales mailboxes:

- New
- Open
- Waiting Customer
- Resolved

## Canned responses

Quick reply snippets.

## Rules

Examples:

- emails from supplier domain -> Purchases
- warranty subject -> Support
- invoice emails -> Accounts

## AI assistance

Optional future AI functionality:

- summarize long threads
- suggest replies
- rewrite tone
- extract order numbers
- extract customer information

AI must never send automatically without user confirmation unless a separate explicitly approved workflow is introduced.

---

# 34. Important Distinction: Business Mail vs Marketing Email

This module is for normal business mailbox communication such as:

- customer inquiries
- quotations
- orders
- supplier communications
- account communications
- support messages

Bulk newsletters and promotional campaigns should be a **separate marketing email module** using a transactional/bulk email provider rather than normal business SMTP credentials.

This protects domain reputation and avoids mailbox provider sending limits.

---

# 35. Recommended Final Architecture

```text
                      BUSINESS MAIL PROVIDER
                    (cPanel / Zoho / Workspace)
                         |             |
                      IMAP            SMTP
                         |             |
                         v             ^
                +---------------------------+
                |     Prinx Laravel App     |
                |                           |
                | IMAP Sync Service         |
                | SMTP Send Service         |
                | Thread Service            |
                | Search Service            |
                +-------------+-------------+
                              |
                    Queue + Scheduler
                              |
                              v
                        MySQL Mail Index
                              |
                              v
                    Inertia + Vue Webmail
                              |
      +----------+---------+---------+---------+---------+
      |          |         |         |         |         |
    Inbox      Drafts    Outbox     Sent    Contacts    Bin
```

---

# 36. Definition of Done

The business mail service is considered production-ready when:

- administrators can configure a mailbox securely
- IMAP and SMTP connections can be tested
- Inbox synchronizes reliably
- users can open/read real messages
- read/unread changes behave correctly
- users can compose and send email
- sent email is processed through a queue
- failed sends are visible and retryable
- Drafts auto-save correctly
- Outbox accurately represents pending/failed sends
- Sent displays successful messages
- Contacts can be created and selected during compose
- Bin supports restore/permanent deletion
- attachments are protected
- HTML email is sanitized
- users cannot access unauthorized mailboxes
- credentials are encrypted
- synchronization operates automatically
- queue workers recover from transient SMTP failures
- tests cover core security and mail workflows
- UI works on desktop, tablet and mobile

---

# 37. Suggested Implementation Order

Implement in this exact order:

1. Database schema
2. Mailbox models
3. Permission model
4. Mailbox admin settings
5. Credential encryption
6. IMAP connection test
7. SMTP connection test
8. IMAP service
9. Folder synchronization
10. Message synchronization
11. Inbox UI
12. Message reader
13. Threading
14. Compose UI
15. Draft autosave
16. SMTP queue/outbox
17. Sent mail
18. Reply / Reply All / Forward
19. Attachments
20. Contact Book
21. Bin / Restore
22. Search
23. Notifications
24. Audit logs
25. Security hardening
26. Test coverage
27. Production monitoring
28. Deployment documentation

This sequence minimizes rework and makes each later step depend on already-tested foundations.

---

# 38. Final Recommendation

Build this as a **provider-agnostic business webmail module** rather than tying Prinx to one hosting company.

The application should know only how to connect securely through standard IMAP/SMTP settings. This means the business can move between cPanel, Namecheap, Zoho, Google Workspace or another mail provider in the future without rebuilding the mail interface.

The module should prioritize:

1. reliability
2. security
3. clear mailbox permissions
4. fast synchronization
5. queued sending
6. excellent Inbox/Compose UX
7. simple operational maintenance

The resulting service should function as the company's internal business-email workspace and later provide a foundation for customer-support workflows, quotations, order communication, supplier communication and other ERP/ecommerce integrations.
