# Calendar Feed

Read-only iCalendar subscriptions for [EspoCRM](https://www.espocrm.com/) meetings. Each person gets one or more secret URLs. Calendar applications poll the URL and receive that person's meetings, including every participant.

The feed is a snapshot. It does not send invitations, and it does not change meetings in EspoCRM.

## Requirements

- EspoCRM 10.0 or newer, before 11
- PHP 8.3 or newer, before 8.6

## Install

1. Download `calendar-feed-1.1.0.zip` from the release, or build it with `npm run extension`. The package is written to `build/`.
2. In EspoCRM, open Administration → Extensions, upload the ZIP, and install it.
3. The installer rebuilds the database. Subscription tables are created as part of that.
4. Set **site URL** in Administration → Settings to the public address of this EspoCRM. Subscription links are based on it.

## Create a subscription

Open Administration → Calendar Feed → Subscriptions.

Create a row with a label (for example the device name) and the user whose meetings should be exported. The full URL is shown once, with a button to copy it. It is not stored and it does not appear on the list.

Paste the URL into a calendar application:

- Apple Calendar: File → New Calendar Subscription, then paste the `https` URL. You can also replace `https` with `webcal` if the dialog asks for that.
- Google Calendar: Other calendars → From URL.
- Outlook: Add calendar → Subscribe from web.

The calendar refreshes on its own schedule. The feed itself is not cached.

## Revoke and regenerate

On the subscription, use **Revoke** to disable the URL immediately. The next refresh receives nothing for that link.

**Regenerate URL** replaces the secret and shows the new address once. The previous address stops working.

A person can have several active subscriptions. Tokens do not expire. Revoking one does not affect the others.

## What is included

A meeting is included when the user is the assigned user or a user attendee, and when the meeting overlaps the export window. The default window is 12 months in the past and 24 months in the future. Change it under Administration → Calendar Feed → Settings.

Planned and Held meetings are included. Meetings marked Not Held are left out unless that option is enabled. Deleted meetings disappear from the feed. There is no cancellation entry.

Participants come from User, Contact, and Lead, and from any other meeting attendee link that stores an acceptance status. Account, opportunity, case, and the parent record are not listed as attendees. The description includes the full participant list. An email address is included only when the subscription's user is allowed to see it.

Stock EspoCRM meetings have no location field, so the feed does not add one. When a meeting has a join URL, that address is the event link. Otherwise the link opens the meeting in EspoCRM.

## Development

```
npm install
composer install
composer install --working-dir=src/files/custom/Espo/Modules/CalendarFeed
npm run unit-tests
```

Integration tests need MySQL or MariaDB. Copy `tests/integration/config.php` from the example in the EspoCRM extension template, then:

```
npm run integration-tests
```

## License

[MIT](LICENSE) © 2026 Lucas Neves
