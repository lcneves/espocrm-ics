# Task: Develop an ICS Calendar Subscription Extension for EspoCRM

## Objective

Develop a lightweight, installable extension for **EspoCRM 10.x** that exposes a secure, read-only iCalendar (ICS) subscription endpoint, allowing users to subscribe to their EspoCRM meetings from Apple Calendar, Google Calendar, Outlook, and other standards-compliant calendar clients.

The extension must export complete meeting information, including descriptions and **all participants, regardless of their EspoCRM entity type**.

The implementation should be maintainable, secure, and compatible with EspoCRM's native extension architecture. Avoid unnecessary complexity, external services, and background jobs.

## 1. Architecture

Implement the extension as a native EspoCRM PHP module.

- Use EspoCRM's custom API actions and routes.
- Use EspoCRM's ORM and dependency injection.
- Do not modify EspoCRM core files.
- Do not require a separate service, synchronization daemon, or cron job.
- Generate ICS dynamically when the endpoint is requested.
- Use an established PHP iCalendar library, preferably `sabre/vobject`, if compatible with EspoCRM's dependency architecture.
- Deliver a standard installable EspoCRM extension ZIP package.

Before implementation, inspect the installed EspoCRM version, its source code, extension structure, and entity metadata. Follow the conventions of that version rather than assuming API signatures.

Relevant documentation:

- https://docs.espocrm.com/development/api-action/
- https://docs.espocrm.com/development/modules/
- https://docs.espocrm.com/development/orm/
- https://docs.espocrm.com/user-guide/activities-and-calendar/
- https://www.rfc-editor.org/rfc/rfc5545

## 2. Calendar subscription endpoint

Expose an endpoint resembling:

`GET /api/v1/CalendarFeed/:token`

The endpoint must:

1. Authenticate the subscription token.
2. Identify the EspoCRM user associated with the token.
3. Retrieve meetings belonging to that user's calendar.
4. Retrieve complete details and all participants for every meeting.
5. Serialize the meetings into a valid RFC 5545 iCalendar document.
6. Return the document with `Content-Type: text/calendar; charset=utf-8`.

The feed must be read-only. No CalDAV support or two-way synchronization is required.

There should be no need for the calendar client to authenticate using EspoCRM credentials.

### Subscription tokens

Implement secure, per-user subscription tokens:

- Generate cryptographically secure random tokens with at least 256 bits of entropy.
- Associate each token with a particular EspoCRM user.
- Store only a cryptographic hash of the token.
- Support generating, listing, and revoking subscriptions.
- Multiple subscriptions per user are desirable, allowing individual devices or clients to be revoked.
- Tokens should not expire automatically unless configured.
- Invalid or revoked tokens must return HTTP 404 or another appropriate non-disclosing error.
- Immediately disable subscriptions for inactive or deleted users.

For the initial version, a simple admin-only management mechanism is sufficient. This can be a native EspoCRM administrative interface, following EspoCRM conventions.

Do not require administrators to manipulate the database manually.

## 3. Selecting meetings

For a given subscription user, export meetings that satisfy at least one of these conditions:

- The user is the meeting's assigned user (`assignedUser`).
- The user is included among its User attendees (`users` relationship).

The same meeting must appear only once, even if the user is both assigned and an attendee.

Use the following default date range:

- 12 months in the past.
- 24 months into the future.

Use actual event start/end overlap when filtering, rather than only checking the start date.

These defaults may be configurable.

Export planned and held meetings. Exclude meetings marked `Not Held` by default, documenting this behavior.

Use paginated or otherwise bounded ORM queries so that large datasets cannot cause excessive memory usage.

Only export meetings the subscription user is authorized to see, respecting EspoCRM's ACLs and record-level access rules. Do not use the privileged feed endpoint to bypass access restrictions.

## 4. Meeting information to export

Every meeting should map to a `VEVENT` containing:

| ICS property | EspoCRM source |
|---|---|
| `UID` | Stable identifier derived from Meeting ID |
| `SUMMARY` | Meeting name |
| `DESCRIPTION` | Full meeting description, plus participants |
| `DTSTART` | Meeting start |
| `DTEND` | Meeting end |
| `DTSTAMP` | Appropriate stable modification timestamp |
| `LAST-MODIFIED` | Meeting modification timestamp, accounting for participant changes |
| `LOCATION` | Meeting location, if available |
| `URL` | Link to the meeting's EspoCRM detail page |
| `ATTENDEE` | Participants with valid email addresses |

Use UTC for timed events.

Support all-day meetings using `VALUE=DATE`, respecting the iCalendar convention that `DTEND` is exclusive.

Use stable UIDs across requests. Meeting updates must update the existing event in calendar clients rather than creating duplicate events.

Ensure that changes to attendees, including relationship-only changes that may not modify the Meeting record's `modifiedAt` field, are reflected in the feed and recognized by calendar clients. Choose an appropriate revision strategy based on EspoCRM's actual behavior.

Do not change every event's timestamp merely because the feed was requested.

Deleted meetings must disappear from the subscription on refresh.

Do not include invitation scheduling semantics that might cause calendar clients to send invitation or cancellation emails.

## 5. Participants — important requirement

**All meeting participants must be exported, regardless of their entity type.**

At minimum, the extension must support:

1. The assigned User (`assignedUser`).
2. All User attendees (`users`).
3. All Contact attendees (`contacts`).
4. All Lead attendees (`leads`).

Do not confuse the meeting's related Account, Opportunity, Case, or parent record with an attendee. Only relationships actually representing participants should be included.

### Participant data

For each participant, resolve:

- Display name.
- Entity type (User, Contact, Lead, etc.).
- Primary email address, if available.
- Acceptance status, if available.
- Role, distinguishing the assigned user from other attendees.

Use EspoCRM's native relationships and metadata. Do not hardcode database table names unnecessarily.

For relationship-specific acceptance status, obtain it from the relevant relationship attributes rather than assuming the status belongs to the participant entity itself.

Account for custom participant entities that might have different name or email field structures.

### Representing participants in ICS

Use two complementary approaches.

**A. Standard `ATTENDEE` properties**

For every participant with a valid email address, emit a standards-compliant `ATTENDEE` property containing:

- `mailto:` calendar address.
- `CN` (display name).
- `PARTSTAT` where the acceptance status maps meaningfully to the iCalendar standard.

Map EspoCRM statuses such as Accepted, Tentative, and Declined to the appropriate RFC 5545 values. Missing or unknown statuses should not be misrepresented as accepted.

Do not invent email addresses for participants who lack them.

Do not create misleading `ORGANIZER` values or mark someone as the organizer merely because they are assigned to the meeting.

**B. Human-readable participant list in `DESCRIPTION`**

Calendar clients vary in how they display `ATTENDEE` properties. Therefore, append a complete, readable participant list to the meeting description.

Example:

```text
Discussion of the commercial proposal and next steps.

Participants:
- Ana Silva (Assigned User) — ana@example.com
- Pedro Costa (User, Accepted) — pedro@example.com
- Maria Pereira (Contact, Tentative) — maria@example.com
- João Santos (Lead, Declined)
```

Requirements:

- Include participants even when they have no email address.
- Identify the entity type for each participant.
- Include acceptance status when available.
- Prevent duplicate entries when the assigned user also appears in the attendees relationship.
- Preserve the original meeting description.
- Preserve meaningful line breaks and paragraph structure.
- Handle HTML descriptions by safely converting them to readable plain text.
- Correctly escape all ICS special characters.
- Preserve Unicode characters, including Portuguese accents.
- Avoid exposing raw HTML, database identifiers, or internal relationship structures in the visible text.

The participant list should always be available in the event description, even when the calendar application ignores the structured `ATTENDEE` properties.

Ensure that existing meeting descriptions and participant names do not become corrupted by escaping, encoding, or line-folding errors.

## 6. Security

The subscription URL functions as a bearer credential and may expose sensitive CRM information.

Implement the following protections:

- Validate the token before accessing meeting information.
- Never use a user-supplied ID to select the calendar owner.
- Prevent token enumeration.
- Do not expose tokens in application logs, exception messages, or analytics.
- Use restrictive response caching headers.
- Ensure one user's subscription cannot expose another user's unrelated meetings.
- Honor appropriate ACL restrictions when accessing related participant data.
- Never allow access to emails, descriptions, or other sensitive fields beyond the scope of the authorized meeting data.
- Ensure revocation takes effect immediately, including when caching is enabled.

Use EspoCRM's supported authorization services wherever applicable.

## 7. Compatibility and performance

The feed must work with Apple Calendar subscriptions on macOS and iOS.

Also aim for compatibility with Google Calendar and Outlook calendar subscriptions.

Requirements:

- RFC 5545-compliant ICS output.
- CRLF line endings.
- Correct line folding.
- Proper text escaping.
- Correct UTF-8 handling.
- Consistent UTC timestamps.
- Correct all-day event representation.
- Stable event identities.
- No duplicated meeting entries.
- Correct update and removal behavior.

Do not implement incremental synchronization protocols, CalDAV, or WebDAV.

Avoid N+1 database queries where practical, particularly when loading attendees.

Caching may be introduced if useful, but correctness, privacy, and immediate token revocation take priority. A small deployment should work adequately without caching.

## 8. Extension administration

Keep administration simple.

In the administrative UI, provide a way to:

- Generate a subscription URL for an EspoCRM user.
- Identify which user owns each subscription.
- Revoke an individual subscription.
- Regenerate a subscription.
- Configure the exported date range.

Users should receive a complete subscription URL that can be pasted directly into Apple Calendar.

Do not expose tokens in a generic list view after initial creation.

## 9. Testing

Implement automated tests covering the following scenarios.

### Meeting selection

- A meeting assigned to the subscription user is included.
- A meeting where the user is an attendee is included.
- A meeting where the user is both assigned and an attendee appears once.
- A meeting belonging exclusively to another user is excluded.
- Meetings outside the configured date range are excluded.
- A spanning event overlapping the date range is included.
- Deleted or excluded meetings are not exported.

### Participants

- Meeting with only an assigned user.
- Meeting with multiple User attendees.
- Meeting with Contact attendees.
- Meeting with Lead attendees.
- Meeting with participants across all three attendee types.
- Meeting with a custom supported attendee type.
- Participant without email.
- Participant with missing or unknown acceptance status.
- Assigned user duplicated in the User attendee relationship.
- Participant whose name includes accented or special characters.
- Participant relationship changes without changes to other meeting fields.
- Restricted related participant records.

### ICS formatting

- Valid ICS with no events.
- Valid ICS with multiple events.
- Updated meeting retains its UID.
- Meeting title containing commas, semicolons, and newlines.
- Multiline description.
- HTML description.
- Unicode description.
- Long folded lines.
- Time-zone conversion.
- Daylight saving transitions.
- All-day events.
- Correct `ATTENDEE` serialization.
- No accidental invitation side effects.

### Security

- Missing token.
- Invalid token.
- Revoked token.
- Inactive subscription owner.
- Attempts to access another user's meetings.
- Attempts to bypass ACL restrictions.
- Tokens absent from application logs.

Parse generated ICS output with an independent, standards-compliant parser as part of the tests.

## 10. Deliverables

Produce:

1. Complete extension source code.
2. Standard installable EspoCRM extension ZIP.
3. Any required database migration or metadata definitions.
4. Automated tests.
5. README.md formatted for GitHub (public visibility, MIT license) at least with installation instructions, instructions for generating and revoking subscription URLs, usage instructions and documentation of configurable options and default behavior.

## 11. Implementation approach

Before writing code:

1. Find the docs and source code for the latest EspoCRM release.
2. Inspect the Meeting entity definitions and relationships.
3. Verify the supported methods for constructing raw, non-JSON API responses.
4. Verify how to obtain attendee relationship attributes, including acceptance statuses.
5. Verify the mechanism for tracking participant relationship changes.
6. Select a compatible iCalendar serialization library.

Then implement and test the extension.

You may install the latest EspoCRM in a gitignored directory if needed for testing.

Prefer a small number of clearly separated components:

- Subscription token management.
- Calendar feed API action.
- Meeting retrieval and authorization.
- Participant resolution.
- ICS serialization.

Follow native EspoCRM design patterns. Prefer simple, idiomatic PHP over custom frameworks.

Avoid unrelated CRM changes or features not required for this extension.

## Acceptance criteria

The extension is complete when:

- An administrator can generate a subscription URL for a user.
- That URL can be subscribed to from Apple Calendar.
- The calendar displays the user's EspoCRM meetings.
- Meeting titles, times, descriptions, and locations are correct.
- Every participant appears in the event details, regardless of entity type or email availability.
- User, Contact, and Lead attendees are correctly identified.
- Meeting modifications, including participant changes, propagate on calendar refresh.
- Deleted meetings disappear after refresh.
- Revoked subscription URLs stop working.
- Subscriptions cannot disclose unauthorized meeting information.
- The extension installs without modifying EspoCRM core code.
- Automated tests pass.

**Implement the extension, not merely a proof of concept or architectural proposal.** Make reasonable implementation decisions based on the installed EspoCRM codebase, document those decisions, and deliver a working, installable extension.
