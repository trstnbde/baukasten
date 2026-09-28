# Baukasten Addon: Form Privacy

Contact Form 7 and Flamingo without the leaks: form assets only where a form is, no IP address, no address book, a retention period, an exporter, and spam protection without a third party.

An addon for [Baukasten - Privacy Toolkit](../baukasten).

| | |
|---|---|
| Requires WordPress | 6.5 |
| Requires PHP | 8.1 |
| Requires plugins | `baukasten`, `contact-form-7` |
| Works with | Flamingo (optional) |
| Settings tab | position 70 |
| License | GPLv2 or later |

## Why Flamingo stays

Flamingo is kept and fenced in rather than replaced. Contact Form 7 writes to it itself (`wpcf7_flamingo_submit()` on `wpcf7_submit`) and keeps that code current; every gap closes with filters both already offer, before the data exists; and a replacement would rebuild list, detail view, search, spam handling, export and eraser for no gain. Revisit that if Flamingo stops being maintained, if `wpcf7_flamingo_*` or `flamingo_add_*` go away, or if a requirement outgrows its data model (encrypted storage, say).

## What it does

| | Class | Hooks |
|---|---|---|
| F1 Assets only where a form is | `Assets` | `wpcf7_load_js`, `wpcf7_load_css` → false; `wpcf7_shortcode_callback`; `wp_enqueue_scripts` at 20 |
| F2 No IP address | `Remote_Ip` | `wpcf7_remote_ip_addr` → `''` |
| F3 No address book | `Address_Book` | `flamingo_add_contact` empties the address; `flamingo_map_meta_cap` maps `flamingo_edit_contact(s)` to `do_not_allow` |
| F4 Less metadata | `Submissions` | `wpcf7_flamingo_inbound_message_parameters` at 20; `wpcf7_flamingo_submit_if` |
| F5 Retention period | `Retention` | daily `baukasten/form_privacy/purge` |
| F6 Cleanup | `Cleanup`, `CLI` | button on the tab, `wp baukasten form-privacy clean` |
| F7 Exporter | `Exporter` | `wp_privacy_personal_data_exporters` |
| F8 Privacy policy text | `Policy_Text` | `wp_add_privacy_policy_content()` on `admin_init` |
| F9 Honeypot and fill time | `Honeypot` | `wpcf7_form_elements`, `wpcf7_form_hidden_fields`, `wpcf7_spam` at 8 |
| F10 Settings | `Settings_Tab` | `baukasten/register_addons` |

Filters are registered unconditionally; only calls into Flamingo are guarded with `class_exists()`.

### F1: when the form is known

Contact Form 7 registers its handles on `wp_enqueue_scripts` (priority 10). A block theme such as Twenty Twenty-Five renders the whole template before `wp_head`, so its `wpcf7_shortcode_callback` fires *before* the handles exist, and enqueueing there would be silently ignored. `Assets::on_form()` therefore only takes note, and `match_assets_to_page()` enqueues on `wp_enqueue_scripts` at 20. A form that turns up later — a classic theme renders after the head — is enqueued on the spot and prints in the footer. Classic themes are also checked ahead of time: `has_shortcode()` / `has_block( 'contact-form-7/contact-form-selector' )` on the entry being shown.

On a page without a form, `cloudflare-turnstile`, `google-recaptcha` and `wpcf7-recaptcha` are dequeued. `wpcf7_load_js` does not cover those.

`Assets::manages_cf7_assets()` is the interface for Business Cards, which then leaves the dequeueing to this plugin, and `baukasten/form_privacy/page_has_form` lets a plugin that prints a form from its own template — Business Cards again — say so.

### F3: the menu

With `flamingo_edit_contacts` disallowed, WordPress drops the Address Book submenu, and the top-level "Flamingo" item leads to the inbound messages, the first submenu the user may open (`wp-admin/includes/menu.php`). Opening `admin.php?page=flamingo` directly ends in core's "not allowed" message. Deleting contacts stays allowed, because Flamingo's own eraser checks `flamingo_delete_contact`.

### F4: the whitelist

Contact Form 7's special mail tags, without the underscore: `serial_number`, `remote_ip`, `user_agent`, `url`, `date`, `time`, `post_id`, `post_name`, `post_title`, `post_url`, `post_author`, `post_author_email`, `site_title`, `site_description`, `site_url`, `site_admin_email`, `user_login`, `user_email`, `user_display_name`. Default: `date`, `time`, `url`, `post_id`, `post_title`. `akismet` and `recaptcha` are emptied, and the Turnstile entry Contact Form 7 adds on priority 10 is not on the list.

### F5: which messages

`flamingo_inbound` with `post_status` `publish`, `flamingo-spam` and `trash` — spelled out, because `any` skips the spam status, which is registered with `exclude_from_search`. `date_query` on `post_date_gmt`. Batches of 100, `wp_delete_post( $id, true )` (fields live in `_field_*`, `_fields` and `post_content`, and only deleting the post removes all three), about 20 seconds per run.

### F9: the checks

`_bk_hp` is a real text field, off screen with CSS, `tabindex="-1"`, `autocomplete="off"`, `aria-hidden` on the wrapper, labelled "Leave this field empty". `_bk_ts` is `{time}.{wp_hash( time . '|' . form_id )}`, with no maximum age, so cached pages and tabs left open keep working. `wpcf7_spam` at 8 — before Contact Form 7's own checks on 9 and 10 — logs `honeypot`, `timestamp missing`, `timestamp invalid` or `too fast` through `add_spam_log()`. Contact Form 7 leaves fields starting with an underscore out of the posted data, so neither field appears in a mail or in Flamingo.

## Settings

Option `baukasten_form_privacy_settings`:

| Key | Type | Default |
|---|---|---|
| `assets_on_demand` | bool | `true` |
| `strip_ip` | bool | `true` |
| `store_submissions` | bool | `true` |
| `store_spam` | bool | `false` |
| `meta_whitelist` | string[] | `date`, `time`, `url`, `post_id`, `post_title` |
| `retention_days` | int | `90` |
| `honeypot` | bool | `true` |
| `min_fill_seconds` | int | `3` |

## Known gaps

- Spam sent by a person is not caught; that is what a service like Turnstile is for, at the price of a third party.
- The retention period depends on WP-Cron. Without a server cron job it runs when the site next has a visitor.
- `uninstall.php` removes only the option and the cron event. Flamingo's messages are Flamingo's.

## Development

```bash
composer install
composer lint
```

## License

GPLv2 or later. See [LICENSE](LICENSE).
