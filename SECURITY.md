# Security policy

## Supported versions

| Version | Supported |
|---|---|
| 1.0.x | Yes |
| 0.1 – 0.3 | **No.** Do not run them. See below. |

## Known issues in versions before 1.0.0

Versions 0.1 to 0.3 (2013 – 2021) are public history, kept only so the record is complete:

* **SQL injection** in the term lookup: the post title was interpolated straight into a `$wpdb`
  query (`WHERE name = '$title'`). Fixed in 1.0.0 by a prepared lookup.
* **Destructive activation**: activating the plugin rewrote every existing post and term slug, with
  no preview and no redirects, so published URLs broke silently. 1.0.0 never changes an existing
  slug unless you ask it to, and then redirects the old URL with a 301.
* Non-Arabic characters (é, ü, Cyrillic, …) were replaced with dashes.

If you are running any version below 1.0.0, update from
<https://wordpress.org/plugins/arabic-to-lat/>.

## Reporting a vulnerability

Please do **not** open a public issue for a security problem.

1. Preferred: GitHub **Security → Report a vulnerability** (private advisory) on this repository.
2. Or report it through the WordPress plugin security team at <https://patchstack.com/database/vdp>
   or <https://wpscan.com/submit>, who will pass it on and coordinate disclosure.

Please include the plugin version, the WordPress and PHP versions, and the steps to reproduce.
Expect a first answer within 7 days. This is a personal, unpaid project, so there is no bounty, but
every report is credited in the changelog unless you ask otherwise.
