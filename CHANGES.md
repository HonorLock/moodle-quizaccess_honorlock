# Changelog

## 4.3.0 (2026103000)

- Added support for Moodle 5.2 and Moodle 5.3.
- Activation now enables Web services authentication for the whole site. Moodle 5.3 rejects API calls
  from web service users when this authentication plugin is disabled.
- Upgrading enables Web services authentication on sites where Honorlock Proctoring is already active,
  so no manual admin action is needed.
- Disabling Honorlock Proctoring leaves Web services authentication enabled, because other integrations
  on the site may rely on it.
- Replaced the Moodle 5.3 deprecated `user_create_user()` and `user_delete_user()` with
  `\core\user::create_user()` and `\core\user::delete_user()`, falling back on older Moodle versions.
