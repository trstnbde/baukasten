=== Baukasten Addon: Two-Factor Approval ===
Contributors: trstnbde
Tags: two-factor, 2fa, login, security, admin bar
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: baukasten, two-factor
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a two-factor method that asks a second, already signed-in browser session to approve the login from the WordPress admin bar.

== Description ==

A second factor that asks another browser instead of asking for a code.

Sign in on your phone and a confirmation appears in the admin bar of the desktop
you are already signed in on. Press yes and the phone finishes signing in; press
no and it does not. No app to install, no codes to copy, nothing to enrol.

This is an addon for the Baukasten - Privacy Toolkit and for the Two Factor
plugin. It registers itself as one more method alongside the ones Two Factor
already offers, and each person switches it on for themselves on their profile.

= How it works =

When a sign-in needs a second factor, a request is opened and held server-side
for a short time — two minutes by default, adjustable. Every other browser where
you are signed in learns about it through the WordPress heartbeat and shows the
confirmation in its admin bar. Answering it is a single authenticated request,
and the answer is written in one atomic step, so two devices answering at the
same moment cannot disagree: the first one wins and the others are told it was
already answered.

= What it does not do =

* It cannot be answered by the browser that is signing in. Only some *other*
  session can confirm, which is the entire point.
* Nobody else ever sees your request — not other users, not administrators.
* If no other session is open, the request simply waits until it expires. The
  sign-in page then offers whatever other methods you have switched on. It is
  worth keeping one of those on.

= Privacy =

Each request stores a shortened IP address and a rough browser name, so the
question "was that you?" can be answered. The full address and the full browser
string are never written down. Requests are deleted once they are answered and
the retention window is up, twenty-four hours by default.

== Installation ==

1. Install and activate the Baukasten - Privacy Toolkit and the Two Factor plugin.
2. Install and activate this plugin.
3. Open your profile and switch on "Confirmation in another session" under
   Two-Factor Options.
4. Timing and privacy settings live under Settings, Baukasten, Two-Factor.

== Frequently Asked Questions ==

= The method does not appear on my profile =

The Two Factor plugin has a site-wide list of the methods a site allows. If an
administrator has ever saved that screen, methods added afterwards are not on
the list. The Two-Factor tab under Settings, Baukasten says so when this is the
case and offers a button to fix it.

= The confirmation takes a while to appear =

The WordPress heartbeat ticks about once a minute and slows down in a tab nobody
has touched. Allow up to a minute. If requests keep expiring before anyone sees
them, raise the waiting time on the settings tab.

= Can I use this as my only second factor? =

You can, but keeping a second method switched on is wiser. If you ever find
yourself without another open session, the other method is how you get in.

== Screenshots ==

1. Signing in: the second factor asks another session to confirm, and says so while it waits.
2. The confirmation waiting in the admin bar of a session that is already signed in.
3. The Two-Factor tab under Settings, Baukasten, with the waiting time and retention window.

== Changelog ==

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.0 =
First release.
