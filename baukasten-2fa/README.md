# Baukasten Addon: Two-Factor Approval

A second factor that asks another browser instead of asking for a code.

Sign in on your phone, and the desktop you are already signed in on shows a
confirmation in its admin bar. Yes finishes the sign-in, no stops it.

Requires [Baukasten - Privacy Toolkit](https://github.com/trstnbde/baukasten) and
the [Two Factor](https://wordpress.org/plugins/two-factor/) plugin.

## How it fits together

Two independent integration points:

- **Two Factor** — `Baukasten_Two_Factor_Approve` registers on the
  `two_factor_providers` filter and shows up as one more method on every user's
  profile. The class is deliberately not namespaced; the reason is in its
  docblock.
- **Baukasten** — a settings tab at position 60, registered on
  `baukasten/register_addons`, for the timing and privacy settings.

Neither depends on the other. The plugin activates and lints without either
host plugin installed, and says what is missing.

## Design notes

**Challenges live in their own table.** A transient offers no compare-and-swap,
so two tabs answering at once would both succeed. Every transition here is a
single `UPDATE ... WHERE status = <old>` and the affected row count decides who
won.

**The challenge id is a bearer token.** The waiting login page is not signed in
yet, so it cannot authenticate a poll the usual way. It holds 256 bits from
`random_bytes()` and the table stores only the SHA-256 hash. Two Factor's own
login nonce would have been the obvious alternative, but `verify_login_nonce()`
deletes the nonce when it fails — building a pollable endpoint on it would hand
anyone who can guess a user id a way to kill other people's sign-ins.

**The status endpoint returns one word.** No user name, no address, no browser.
Everything a person needs before answering is on the authenticated endpoint the
admin bar uses.

**A session cannot confirm itself.** Two Factor's revalidation flow runs while
already signed in, so without a session binding a user could approve their own
challenge from the tab next door. Each challenge records a hash of the session
it was raised in, and that session is filtered out on both the read and the
write.

**The form is never submitted automatically except on approval.** Two Factor
counts every rejected attempt, slows the account down exponentially, and resets
the password after thirty. A forgotten tab retrying on expiry would get there by
itself.

**Two polling speeds.** The heartbeat runs for every signed-in user anyway and
costs one extra field; the few-second poll starts only once the heartbeat says
something is waiting, and stops as soon as it is answered.

## Settings

Settings → Baukasten → Two-Factor. Every value also has a filter:
`baukasten/2fa/expiry`, `baukasten/2fa/poll_interval`, `baukasten/2fa/rate_limit`,
`baukasten/2fa/rate_window`, `baukasten/2fa/show_context`.

Actions fire on each decision: `baukasten/2fa/approved` and
`baukasten/2fa/denied`, both passed the user id.

## Renaming caution

`Baukasten_Two_Factor_Approve` is not just a class name. Two Factor writes it to
user meta, to session meta and to its site-wide allowlist option, so renaming
the class in a later version is a data migration, not a rename.

## Development

```
composer install
composer lint
```

Build a distributable zip from the repository root:

```
php bin/build.php baukasten-2fa
```
