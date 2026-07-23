# R&D: AI-Powered Contact Form 7 Inbox

## 1. Project Overview

### Proposed Working Name
**CF7 AI Inbox**

### Objective
Build a standalone WordPress plugin that combines:

1. The AI analysis, reply drafting, categorization, priority scoring, and review workflow from `contact-form-7-ai-copilot`.
2. The reliable local submission storage, contact management, spam metadata, and inbox concepts from `Flamingo`.
3. Contact Form 7 integration through public hooks and APIs, without modifying Contact Form 7 core.

The final plugin should provide one unified inbox where administrators can store, review, classify, search, filter, summarize, and reply to Contact Form 7 submissions.

---

## 2. Source Repositories Reviewed

- `aminul-xs/contact-form-7-ai-copilot`
- `aminul-xs/contact-form-7`
- `aminul-xs/flamingo`

### Existing AI Plugin Capabilities

The current AI plugin provides:

- AI-generated submission summaries.
- Suggested replies.
- Category classification.
- Priority classification.
- Confidence scoring and reasoning.
- Manual reply review and confirmation.
- OpenAI, Anthropic, Gemini, and OpenRouter support.
- Searchable and filterable AI inbox.
- Submission statuses such as New, Reviewed, Replied, and Archived.
- Dashboard statistics and usage reporting.
- Local storage of up to 200 recent submissions.
- Encrypted API-key storage.
- PSR-4 architecture using the `CF7AIC\` namespace.
- PHP 8.1+ and WordPress 6.8+ requirements.

### Existing Flamingo Capabilities

Flamingo provides:

- Persistent storage for Contact Form 7 submissions.
- A custom post type for inbound messages.
- A custom post type for contacts.
- Message channels using a taxonomy.
- Storage of submitted fields and metadata.
- Spam and reCAPTCHA metadata.
- Search, filtering, trash, and CSV-related functionality.
- WordPress capability mapping for inbox access.
- A stable, mature Contact Form 7 storage workflow.

### Contact Form 7 Role

Contact Form 7 should remain an external required dependency. The new plugin should use Contact Form 7 hooks and public APIs instead of including or modifying CF7 core.

---

## 3. Recommended Product Strategy

## Recommended Approach: One Addon, CF7 Remains Separate

Create a new plugin that includes:

- Its own Flamingo-inspired storage layer.
- The existing AI Copilot service layer.
- A new unified inbox and review interface.
- Optional migration/import support for existing Flamingo records.
- Optional coexistence support when Flamingo is already active.

Do **not** directly combine the full Contact Form 7 source code into the new plugin.

### Why This Approach Is Best

- Contact Form 7 receives independent security and compatibility updates.
- Bundling CF7 would create update, branding, licensing, support, and maintenance complications.
- Public CF7 hooks already provide the integration points needed.
- The plugin remains smaller and easier to test.
- Users can update Contact Form 7 without waiting for this plugin.
- It avoids conflicts caused by duplicate CF7 functions, classes, scripts, REST routes, and constants.

---

## 4. Product Scope

## 4.1 Minimum Viable Product

The first release should include:

1. Contact Form 7 submission capture.
2. Permanent local submission storage.
3. Unified inbox list.
4. Submission details screen.
5. AI summary.
6. AI-generated reply draft.
7. Category, priority, and confidence.
8. Manual reply editing.
9. Manual Send Reply action with confirmation.
10. Search and filters.
11. Status management.
12. Provider settings.
13. Prompt settings.
14. Basic usage statistics.
15. Privacy tools.
16. Migration from the existing AI Copilot plugin.
17. Optional import from Flamingo.

## 4.2 Future Scope

- Conversation threading.
- Multiple agents and assignment.
- Internal notes.
- Tags and custom labels.
- Saved reply templates.
- AI-assisted spam detection.
- Sentiment detection.
- Language detection and translation.
- SLA tracking.
- WooCommerce customer matching.
- CRM integrations.
- Webhooks.
- Slack and email notifications.
- Auto-draft regeneration.
- Bulk AI processing.
- AI knowledge base and brand tone.
- Analytics dashboard.
- Team permissions and audit trail.

---

## 5. Functional Requirements

## 5.1 Submission Capture

The plugin should capture submissions through Contact Form 7 public hooks.

Recommended hooks:

- `wpcf7_before_send_mail`
- `wpcf7_mail_sent`
- `wpcf7_mail_failed`
- `wpcf7_spam`
- `wpcf7_submit`

### Recommended Capture Strategy

Use `wpcf7_before_send_mail` to create the initial database record before mail processing completes.

Then update the stored record through:

- `wpcf7_mail_sent` for successful notification delivery.
- `wpcf7_mail_failed` for failed mail delivery.
- `wpcf7_spam` for spam submissions.
- `wpcf7_submit` for final submission status and response information.

This is safer than depending only on `wpcf7_mail_sent`, because failed CF7 emails should not cause submissions to disappear from the inbox.

### Data to Capture

- CF7 form ID.
- Form title.
- Submission timestamp.
- Submitted fields.
- Name.
- Email.
- Subject.
- Message.
- Uploaded-file references.
- Page URL.
- User IP, only when explicitly enabled.
- User agent, only when explicitly enabled.
- CF7 submission status.
- Mail status.
- Spam status.
- Akismet metadata.
- reCAPTCHA metadata.
- Consent fields.
- Submission hash.
- Site ID on multisite.

Passwords and sensitive technical fields must never be stored.

---

## 5.2 Inbox

Inbox columns:

- Sender.
- Subject or message preview.
- Form.
- Status.
- Priority.
- Category.
- AI confidence.
- Assigned user, future-ready.
- Received date.
- Last activity.

Filters:

- Form.
- Status.
- Priority.
- Category.
- Confidence range.
- Spam.
- Date.
- Provider/model.
- Assigned user, future-ready.
- Search keyword.

Bulk actions:

- Mark reviewed.
- Archive.
- Restore.
- Mark spam.
- Mark not spam.
- Delete.
- Regenerate AI analysis.
- Export.

---

## 5.3 Submission Review Screen

Sections:

### Customer Information
- Name.
- Email.
- Phone.
- Other mapped fields.

### Original Submission
- All submitted fields.
- Attachments.
- Form name.
- Submission date.
- Source page.
- Mail delivery state.

### AI Analysis
- Summary.
- Category.
- Priority.
- Confidence.
- Reasoning.
- Sentiment, future.
- Detected language, future.

### Reply Composer
- Recipient.
- Subject.
- Editable reply.
- Regenerate reply.
- Save draft.
- Preview.
- Send with confirmation.

### Activity Timeline
- Submission received.
- AI analysis started.
- AI analysis completed or failed.
- Draft edited.
- Status changed.
- Reply sent.
- User who performed each action.

---

## 5.4 Status Model

Recommended statuses:

- `new`
- `processing`
- `needs-review`
- `reviewed`
- `drafted`
- `replied`
- `archived`
- `spam`
- `failed`

Do not use post status alone for all workflow states. Store business workflow status separately so WordPress trash and post-status behavior remain predictable.

---

## 6. Storage Architecture

## Recommended Storage: Custom Database Tables

Flamingo uses WordPress custom post types and post meta. That is stable and WordPress-native, but an AI inbox adds frequent filtering, analytics, status updates, token usage, activity logs, and potentially high submission volume.

For the combined plugin, custom tables are recommended.

### Main Tables

#### `{prefix}cf7ai_messages`

Suggested columns:

```sql
id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
site_id BIGINT UNSIGNED DEFAULT 1
form_id BIGINT UNSIGNED NOT NULL
form_title VARCHAR(255)
submission_hash VARCHAR(191)
sender_name VARCHAR(255)
sender_email VARCHAR(320)
subject TEXT
message LONGTEXT
fields LONGTEXT
meta LONGTEXT
channel VARCHAR(100)
submission_status VARCHAR(50)
workflow_status VARCHAR(50)
mail_status VARCHAR(50)
spam_status TINYINT(1) DEFAULT 0
priority VARCHAR(30)
category VARCHAR(100)
confidence DECIMAL(5,2)
ai_summary LONGTEXT
ai_reasoning LONGTEXT
ai_error TEXT
ai_provider VARCHAR(50)
ai_model VARCHAR(191)
reply_subject TEXT
reply_draft LONGTEXT
reply_sent_body LONGTEXT
reply_sent_at DATETIME NULL
created_at DATETIME NOT NULL
updated_at DATETIME NOT NULL
deleted_at DATETIME NULL
```

#### `{prefix}cf7ai_activities`

```sql
id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
message_id BIGINT UNSIGNED NOT NULL
user_id BIGINT UNSIGNED DEFAULT 0
event_type VARCHAR(100)
event_data LONGTEXT
created_at DATETIME NOT NULL
```

#### `{prefix}cf7ai_usage`

```sql
id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
message_id BIGINT UNSIGNED NULL
provider VARCHAR(50)
model VARCHAR(191)
prompt_tokens BIGINT UNSIGNED DEFAULT 0
completion_tokens BIGINT UNSIGNED DEFAULT 0
estimated_cost DECIMAL(12,6) DEFAULT 0
request_status VARCHAR(30)
created_at DATETIME NOT NULL
```

#### `{prefix}cf7ai_contacts` — optional for version 1

```sql
id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
email VARCHAR(320)
name VARCHAR(255)
first_seen_at DATETIME
last_seen_at DATETIME
submission_count BIGINT UNSIGNED DEFAULT 0
meta LONGTEXT
```

### Why Custom Tables

- Faster indexed filtering.
- Better scaling for large inboxes.
- Cleaner analytics.
- Easier retention and deletion.
- Avoids large postmeta queries.
- Better support for activity logs.
- Easier future assignment and team workflows.

### Alternative

For the fastest first release, retain the Flamingo custom-post-type model and add namespaced AI metadata. This reduces development time but may need migration to custom tables later.

### Final Recommendation

Use custom tables from the beginning, but provide a Flamingo importer.

---

## 7. AI Processing Architecture

## 7.1 Asynchronous Processing

AI requests should not run inside the visitor's form-submission request.

Recommended flow:

1. Capture and save the submission.
2. Queue an AI-processing job.
3. Return CF7's response normally.
4. Process the AI job through Action Scheduler or WP-Cron.
5. Store the structured AI result.
6. Update inbox status.
7. Record usage and errors.

### Preferred Queue

Use Action Scheduler when available or bundle it safely under a prefixed namespace. A lightweight internal queue based on custom tables plus WP-Cron is also acceptable.

### Benefits

- Faster visitor response.
- CF7 submission does not fail when the AI API is slow.
- Retries are possible.
- Rate limits are manageable.
- Failed jobs are visible.
- Batch processing becomes possible.

---

## 7.2 AI Response Contract

Require providers to return structured JSON:

```json
{
  "summary": "Short summary",
  "suggested_reply": "Reply draft",
  "reply_subject": "Suggested subject",
  "category": "Sales",
  "priority": "high",
  "confidence": 0.87,
  "reasoning": "The visitor requested an urgent quotation."
}
```

Validate every response against an internal schema.

### Allowed Priority Values

- low
- normal
- high
- urgent

### Confidence

Store confidence as `0–100` in the UI and normalize provider results internally.

### Failure Behavior

When AI processing fails:

- Keep the original submission.
- Set status to `needs-review` or `failed`.
- Display the provider error safely.
- Allow manual retry.
- Never block Contact Form 7.
- Never send a reply automatically by default.

---

## 8. Provider Abstraction

Recommended interface:

```php
interface ProviderInterface {
    public function get_id(): string;
    public function validate_credentials(): true|\WP_Error;
    public function get_models(): array|\WP_Error;
    public function analyze(AnalysisRequest $request): AnalysisResult|\WP_Error;
}
```

Provider classes:

```text
Providers/
├── OpenAIProvider.php
├── AnthropicProvider.php
├── GeminiProvider.php
└── OpenRouterProvider.php
```

A provider registry should make future providers pluggable.

```php
apply_filters('cf7ai_registered_providers', $providers);
```

### Provider Requirements

- Centralized timeout.
- Retry handling.
- HTTP status validation.
- JSON validation.
- Rate-limit handling.
- Redacted logging.
- No API key in logs.
- Configurable model.
- Connection test.
- Live model list with caching.

---

## 9. Proposed Plugin Architecture

```text
cf7-ai-inbox/
├── cf7-ai-inbox.php
├── uninstall.php
├── readme.txt
├── composer.json
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── languages/
├── includes/
│   ├── Plugin.php
│   ├── Activation.php
│   ├── Deactivation.php
│   ├── Requirements.php
│   ├── Admin/
│   │   ├── Menu.php
│   │   ├── AiInbox.php
│   │   ├── Contacts.php
│   │   ├── Settings.php
│   │   ├── Dashboard.php
│   │   └── Analytics.php
│   ├── Templates/
│   ├── AI/
│   │   ├── AIManager.php
│   │   ├── PromptBuilder.php
│   │   ├── ResponseValidator.php
│   │   ├── AnalysisRequest.php
│   │   ├── AnalysisResult.php
│   │   └── Providers/
│   ├── CF7/
│   │   ├── Integration.php
│   │   ├── SubmissionMapper.php
│   │   └── FormRepository.php
│   ├── Database/
│   │   ├── Migrator.php
│   │   ├── MessageRepository.php
│   │   ├── ActivityRepository.php
│   │   ├── ContactRepository.php
│   │   └── UsageRepository.php
│   ├── Jobs/
│   │   ├── Queue.php
│   │   ├── AnalyzeSubmissionJob.php
│   │   └── CleanupJob.php
│   ├── Mail/
│   │   ├── ReplyService.php
│   │   └── ReplyValidator.php
│   ├── Privacy/
│   │   ├── Exporter.php
│   │   ├── Eraser.php
│   │   └── Retention.php
│   ├── Migration/
│   │   ├── FlamingoImporter.php
│   │   └── AICopilotImporter.php
│   ├── REST/
│   │   ├── Routes.php
│   │   ├── MessagesController.php
│   │   ├── AIController.php
│   │   └── SettingsController.php
│   ├── Security/
│   │   ├── Capabilities.php
│   │   ├── Encryption.php
│   │   └── Sanitizer.php
│   └── Support/
│       ├── Logger.php
│       └── Helpers.php
└── tests/
    ├── Unit/
    ├── Integration/
    └── E2E/
```

### Namespace

```php
CF7AIInbox\
```

### Autoloading

```json
{
  "autoload": {
    "psr-4": {
      "CF7AIInbox\\": "includes/"
    }
  }
}
```

---

## 10. Flamingo Integration and Migration

## 10.1 Do Not Load Flamingo Classes Directly as Core Dependencies

The new plugin should not depend on internal Flamingo classes for normal operation. Internal classes are not guaranteed as a stable public API.

Instead:

- Implement a separate storage layer.
- Detect Flamingo when active.
- Import through a dedicated adapter.
- Keep the import optional.
- Store the Flamingo source post ID for traceability.

## 10.2 Import Mapping

| Flamingo data | New plugin data |
|---|---|
| `flamingo_inbound` post | Message row |
| `_subject` | `subject` |
| `_from` | sender display value |
| `_from_name` | `sender_name` |
| `_from_email` | `sender_email` |
| `_fields` | serialized/JSON `fields` |
| `_meta` | `meta` |
| `_submission_status` | `submission_status` |
| `_akismet` | spam metadata |
| `_recaptcha` | reCAPTCHA metadata |
| `_spam_log` | spam activity |
| `_consent` | consent fields |
| `_hash` | `submission_hash` |
| channel taxonomy | `channel` |

### Migration Rules

- Import in batches.
- Use AJAX or REST progress.
- Support resume.
- Prevent duplicate import.
- Preserve timestamps.
- Do not delete Flamingo data automatically.
- Show a migration report.
- Provide dry-run counts.
- Record failed items.
- Require explicit confirmation before cleanup.

---

## 11. Existing AI Copilot Migration

Migrate:

- General settings.
- Enabled form IDs.
- Provider selection.
- Encrypted API key when decryptable.
- Model selection.
- Prompt.
- Inbox entries.
- AI summaries.
- Reply drafts.
- Priority.
- Category.
- Confidence.
- Usage history.
- Reply status.

### Important

If encryption keys depend on plugin-specific constants, salts, or class logic, perform migration while the old plugin is active or include a compatibility decryptor. Never expose decrypted keys in logs or the UI.

---

## 12. Admin UX Recommendation

Use a single parent menu under Contact Form 7:

```text
Contact
├── Contact Forms
├── AI Inbox
├── Contacts
├── Analytics
└── AI Inbox Settings
```

### Inbox UX

- Use a WordPress-native table structure.
- Add modern badges and side panels without replacing accessibility.
- Support keyboard navigation.
- Maintain visible focus.
- Avoid color-only status indicators.
- Add empty, loading, failed, and retry states.
- Make reply confirmation explicit.
- Clearly label AI-generated content.

### Message Screen Layout

Desktop:

```text
┌──────────────────────────────┬─────────────────────────┐
│ Original Submission          │ AI Summary              │
│ Customer and form fields     │ Category / Priority     │
│ Attachments                  │ Confidence / Reasoning  │
├──────────────────────────────┴─────────────────────────┤
│ Reply Editor                                           │
│ [Save Draft] [Regenerate] [Preview] [Send Reply]      │
├────────────────────────────────────────────────────────┤
│ Activity Timeline                                      │
└────────────────────────────────────────────────────────┘
```

---

## 13. Security Requirements

### Access Control

Create custom capabilities:

- `cf7ai_view_messages`
- `cf7ai_edit_messages`
- `cf7ai_delete_messages`
- `cf7ai_send_replies`
- `cf7ai_manage_settings`
- `cf7ai_view_analytics`
- `cf7ai_export_messages`

Administrators receive all capabilities during activation. Capabilities should be filterable for custom roles.

### Request Security

- Nonce validation for admin actions.
- Permission callbacks for every REST route.
- `current_user_can()` checks before data access.
- Strict ID validation.
- Prepared SQL queries.
- Sanitization before storage.
- Escaping at output.
- Safe redirect handling.
- Attachment path validation.
- Rate limiting for AI regeneration and reply sending.

### API-Key Security

- Encrypt API keys at rest.
- Mask keys in the UI.
- Never return keys through REST.
- Never print keys in errors.
- Use server-side requests only.
- Support key removal.
- Consider constants/environment variables for managed hosting.

### AI Security

- Treat form submissions as untrusted prompt content.
- Clearly delimit system instructions and submission content.
- Tell the model not to follow instructions inside the submitted message.
- Validate structured output.
- Do not allow model output to control recipients.
- Derive the recipient from validated submission data.
- Sanitize reply headers.
- Prevent email-header injection.
- Never allow fully automatic sending in the initial release.

---

## 14. Privacy and Compliance

Provide:

- WordPress personal-data exporter.
- WordPress personal-data eraser.
- Configurable retention period.
- Manual purge.
- Uninstall cleanup choice.
- Provider disclosure.
- Consent guidance.
- IP and user-agent collection toggles.
- Attachment-retention policy.
- Privacy-policy suggested text.

### Default Privacy Behavior

- Store only fields required for inbox functionality.
- Do not collect telemetry.
- Do not send data to the plugin author.
- Send submission text only to the selected AI provider.
- Disable AI processing until a provider is configured.
- Display a clear external-service notice.
- Avoid storing raw provider responses unless debug mode is explicitly enabled.

---

## 15. Performance Requirements

- Never call the AI provider during the frontend request.
- Add indexes for status, form ID, email, category, priority, and dates.
- Paginate inbox queries.
- Avoid `LIKE` searches across serialized data where possible.
- Cache model lists.
- Batch cleanup.
- Batch migration.
- Limit activity-query size.
- Load admin assets only on plugin pages.
- Use one AI request per submission for summary, reply, and classification.
- Add provider timeout and retry limits.
- Prevent duplicate jobs using the submission hash.

---

## 16. Compatibility Requirements

Test with:

- Current stable WordPress.
- Previous two major WordPress releases.
- PHP 8.1, 8.2, 8.3, and 8.4.
- Current Contact Form 7.
- Contact Form 7 multisite.
- Flamingo active.
- Flamingo inactive.
- Existing AI Copilot active during migration.
- WP Mail SMTP.
- Common security plugins.
- Object caching.
- MariaDB and MySQL.
- RTL.
- WordPress locale switching.
- Large forms.
- File uploads.
- Multiple CF7 mail configurations.
- Spam and failed-mail flows.

---

## 17. Testing Plan

## Unit Tests

- Submission field mapping.
- Sensitive-field exclusion.
- Prompt building.
- Provider response parsing.
- Priority validation.
- Confidence normalization.
- API-key encryption.
- Header-injection prevention.
- Retention calculations.
- Migration mappings.

## Integration Tests

- Successful CF7 submission.
- Failed CF7 mail.
- Spam submission.
- AI success.
- AI timeout.
- AI invalid JSON.
- Provider rate limit.
- Queue retry.
- Reply sending.
- Permission enforcement.
- Flamingo import.
- Existing AI plugin migration.
- Multisite behavior.

## End-to-End Tests

- Configure provider.
- Select forms.
- Submit form.
- View inbox.
- Review analysis.
- Edit draft.
- Send reply.
- Archive submission.
- Export and erase personal data.
- Import Flamingo records.

---

## 18. Development Phases

## Phase 1: Foundation

- Plugin bootstrap.
- Requirements checks.
- PSR-4 architecture.
- Database schema.
- Capability system.
- CF7 dependency notice.
- Activation and upgrade migrations.

## Phase 2: Submission Storage

- CF7 hook integration.
- Submission mapper.
- Message repository.
- Inbox table.
- Message details screen.
- Search and filters.
- Spam and status handling.

## Phase 3: AI Layer

- Provider interface.
- OpenAI provider.
- Anthropic provider.
- Gemini provider.
- OpenRouter provider.
- Prompt builder.
- Structured response validation.
- Queue and retry system.
- Usage tracking.

## Phase 4: Reply Workflow

- Reply editor.
- Save draft.
- Regeneration.
- Confirmation modal.
- Secure email sending.
- Activity timeline.
- Replied status.

## Phase 5: Migration

- AI Copilot migration.
- Flamingo importer.
- Batch progress.
- Duplicate protection.
- Reports and rollback guidance.

## Phase 6: Privacy, Analytics, and QA

- Exporter and eraser.
- Retention settings.
- Dashboard.
- Usage analytics.
- Accessibility.
- Performance testing.
- Security review.
- WordPress.org packaging.

---

## 19. Risks and Mitigation

### Risk: Duplicate Storage

When Flamingo is active, both plugins may save the same submission.

**Mitigation:** Add a setting:

- Use CF7 AI Inbox storage only.
- Import from Flamingo.
- Link to Flamingo records without duplicate import, optional advanced mode.

The default should be independent storage with a clear duplicate-storage warning when Flamingo is active.

### Risk: AI Cost

Large or repeated submissions may increase provider cost.

**Mitigation:**

- One request per submission.
- Character limits.
- Usage reporting.
- Per-form enablement.
- Daily/monthly limits.
- Retry caps.
- Manual processing option.

### Risk: Slow or Failed Provider

**Mitigation:** Asynchronous processing, retries, visible failure status, and manual review.

### Risk: Prompt Injection

**Mitigation:** Treat submission content as quoted data, use strict system instructions, validate JSON, and prohibit automatic recipient or action control.

### Risk: Data Privacy

**Mitigation:** Explicit provider disclosures, retention controls, eraser/exporter support, configurable field exclusion, and no telemetry.

### Risk: Large Database

**Mitigation:** Custom tables, indexes, retention policies, batch deletion, and archive/export options.

### Risk: Migration Failure

**Mitigation:** Dry run, batch processing, resumable import, duplicate keys, logging, and no automatic deletion of source data.

---

## 20. Licensing and Attribution

The reviewed projects use GPL-compatible licensing. Reused or adapted Flamingo and Contact Form 7 code must preserve applicable copyright notices and attribution.

Recommended practice:

- Prefer reimplementation around documented behavior and public hooks.
- Copy only code that is genuinely needed.
- Preserve license headers where code is copied or substantially adapted.
- Include attribution in `readme.txt`, source headers, and a third-party notices file.
- Do not use Contact Form 7 or Flamingo trademarks in a way that implies official ownership or endorsement.

---

## 21. Recommended Release Requirements

Suggested baseline:

```text
Requires WordPress: 6.7+
Requires PHP: 8.1+
Requires Contact Form 7: current supported release
License: GPL-2.0-or-later
```

Before release, verify the actual supported versions through CI rather than relying only on source-plugin declarations.

---

## 22. Final Recommendation

Build a new standalone addon rather than merging all three repositories directly.

The plugin should:

1. Require Contact Form 7.
2. Capture submissions through public CF7 hooks.
3. Use its own scalable message storage.
4. incorporate the AI Copilot provider and review workflow.
5. Provide a Flamingo importer rather than depending on Flamingo internals.
6. Run AI requests asynchronously.
7. Require human confirmation before sending replies.
8. Include strong capability, privacy, migration, and prompt-injection protections.
9. Preserve an extension-friendly, PSR-4 architecture.
10. Keep the first release focused on a reliable unified inbox.

This approach provides the intended combined product while reducing maintenance risk and preserving compatibility with future Contact Form 7 and Flamingo updates.
