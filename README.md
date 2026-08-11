# Inbox AI – Contact Form 7 Inbox, Submission Management and Database

## Tags: contact form 7, ai inbox, database, submissions, flamingo

## Documentation

- [`readme.txt`](readme.txt) — the WordPress.org plugin listing: features, FAQ, and changelog.
- [`CLAUDE.md`](CLAUDE.md) — codebase architecture: what's actually built vs. only planned, and how each major piece (Settings, AI pipeline, database, admin pages/AJAX, migration, security) fits together.
- [`tools/README.md`](tools/README.md) — how to explore and test the running plugin: seeding demo data, testing the Import & Migration wizard, watching AJAX calls, and a per-feature verification checklist (Slack, CRM, Inbound Email, etc.).
- [`docs/dev-tools.md`](docs/dev-tools.md) — reference for `tools/seed-demo-data.php`, the demo-data seeder.
- [`wiki/Home.md`](wiki/Home.md) — the end-user guide: setup, every Settings tab explained, using the AI Inbox, and troubleshooting. Start with [Getting Started](wiki/01-getting-started.md). (Also mirrored to this repo's [Wiki tab](https://github.com/theaminulai/inbox-ai/wiki) once pushed there — see `wiki/Home.md`'s own note.)
- [`wiki/Inbound-Email-Replies-Setup-Guide.md`](wiki/Inbound-Email-Replies-Setup-Guide.md) — step-by-step setup guide specifically for the Inbound Email Replies feature, including common IMAP/WP-Cron troubleshooting.