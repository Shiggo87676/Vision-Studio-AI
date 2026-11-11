# Vision Studio AI

The Phase 1 foundation is a small PHP 8.1+ application using MySQL and PDO. Application code lives outside the public web root; only `public/` should be exposed by a web server.

## Requirements

- WAMP PHP 8.3.28 at `C:\wamp64\bin\php\php8.3.28\php.exe`, with `pdo` and `pdo_mysql`
- MySQL 5.7+ (or a compatible MySQL server)
- Composer (optional; the application has no third-party PHP dependencies)

## Required PHP Runtime

This project is configured to use WAMP PHP 8.3.28 for every PHP operation. The VS Code workspace prepends its directory to the integrated terminal PATH, configures the PHP validator to use its executable, and provides WAMP-backed serve, migration, and test tasks. Composer scripts also invoke the WAMP executable directly. Do not use the separately installed WinGet PHP.

When working outside the VS Code workspace terminal, run PHP using its full path:

```powershell
& 'C:\wamp64\bin\php\php8.3.28\php.exe' --version
```

## Setup

1. Copy `.env.example` to `.env` and set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.
2. Create the database and grant the configured user access:

   ```sql
   CREATE DATABASE vision_studio_ai
     CHARACTER SET utf8mb4
     COLLATE utf8mb4_unicode_ci;
   ```

3. Run the migrations from the project root:

   ```powershell
   & 'C:\wamp64\bin\php\php8.3.28\php.exe' database\migrate.php
   ```

   The migration runner creates its own `migrations` tracking table and applies pending migrations in filename order. It does not drop tables or reset data.
4. Start the development server:

   ```powershell
   & 'C:\wamp64\bin\php\php8.3.28\php.exe' -S 127.0.0.1:8000 -t public public\router.php
   ```

   Open `http://127.0.0.1:8000`. Configure production web servers to use `public/` as the document root.

If Composer is installed, `composer install`, `composer test`, `composer test-auth`, and `composer test-email-verification` are also available. The project Composer scripts invoke WAMP PHP 8.3.28 directly. The homepage does not require Composer's generated autoloader.

## Database

Database credentials are read from the process environment or `.env`; existing process variables take precedence. PDO uses exception mode, associative fetches, native prepared statements, and the `utf8mb4` connection charset.

The initial migrations create `users` and `password_reset_tokens`, with unique email/token constraints and a cascading foreign key. Additive migrations create `auth_login_rate_limits`, `remember_tokens`, `user_activities`, and `email_verification_tokens`; token values are stored as SHA-256 hashes, and user-owned records use cascading foreign keys. The additive registration migration adds nullable `users.terms_accepted_at`; the account-security migration adds `users.auth_version` with a default of 1 so existing users and sessions remain compatible. The activity migration records relevant account events and notifications without passwords, reset tokens, or other credentials. Existing records and migration history are preserved. Password reset requests reuse the existing rate-limit and reset-token tables. PHP sessions are stored in the ignored `storage/sessions/` directory, not in the database.

To check the database connection and apply migrations, configure `.env` and run the migration command. Database changes use `CREATE TABLE IF NOT EXISTS`, and completed migration filenames are recorded so already-applied migrations are skipped.

## Registration and authentication

The application provides registration with required Terms and Conditions acceptance, CSRF-protected forms, server-side validation, prepared database writes, password hashing, a 10-attempt/15-minute registration throttle, and a one-time success notification. Accepted terms are recorded in the additive `users.terms_accepted_at` column. New accounts cannot authenticate until the user confirms ownership of the email address with a random, single-use token (stored only as a SHA-256 hash and expiring after 24 hours). Resend requests are rate limited and return a generic response. Verification email uses the configured SMTP server; the verification page requires a CSRF-protected confirmation POST so link scanners do not consume tokens on GET. Accounts that predate email verification and lack an `email_verified_at` timestamp must verify via `/verify-email`; a completed password reset also confirms the address because the reset link was delivered to that mailbox. Login uses `password_verify`, generic credential errors, active-account and verified-email checks, login throttling, session fixation protection, and an optional 90-day “Remember me” cookie. Sessions use HTTP-only, SameSite=Lax cookies (Secure over HTTPS), a 30-minute idle timeout, and a 12-hour absolute timeout. Logout is CSRF-protected, revokes remembered login, invalidates the authenticated session cookie, and displays a one-time confirmation on the homepage. `/dashboard` is protected and `/account` remains a compatible route. The dashboard displays account-backed statistics, user-scoped recent activity, and notifications; unavailable project data remains an honest empty state. Registration, email verification, password reset requests/completions, password changes, and profile-name changes create transactional, credential-free activity/notification records. The CSRF-protected `/notifications/read` endpoint only updates the signed-in user’s notification records. `/settings` displays account information, links to functional profile and password settings, and does not expose nonfunctional preference controls. The `/profile` page displays account name, email, creation date, and status; users may update their name, while email changes are not offered without a verification workflow. `/change-password` requires the current password, validates the replacement and confirmation, prevents password reuse, and is rate limited. A successful password change rotates the current session ID, preserves the current session, revokes remember-me tokens, and increments `users.auth_version`; other sessions are rejected on their next request. Registration, reset, and change passwords require at least 7 characters and may not exceed 72 UTF-8 bytes. Password recovery generates single-use random tokens, stores only SHA-256 hashes, expires links after 30 minutes, limits requests, and updates the password and invalidates outstanding reset/remember-me tokens in one transaction. Requests return a generic response regardless of account existence. Recovery and verification email use the configured SMTP server; see SMTP setup below.

### SMTP setup for password recovery

Set these variables in the local, Git-ignored `.env` file:

```dotenv
APP_URL=https://your-public-site.example
SMTP_HOST=smtp.your-provider.example
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=your-smtp-username
SMTP_PASSWORD=your-smtp-password-or-app-password
MAIL_FROM_ADDRESS=no-reply@your-domain.example
MAIL_FROM_NAME=Vision Studio AI
```

Use the SMTP host, port, encryption mode, username, and password provided by your email provider. Port 587 uses STARTTLS; port 465 uses implicit TLS (`SMTP_ENCRYPTION=ssl`). Keep `.env` private and use an app password where the provider requires one. `APP_URL` must be the absolute public HTTP or HTTPS base URL users should return to. Password reset links expire after 30 minutes; email verification links expire after 24 hours. Both are single-use, hashed at rest, and sent through SMTP. SMTP delivery requires valid provider settings and outbound network access from the PHP host.

## Public website

The homepage at `/` includes the shared responsive navigation and footer, hero, feature cards, about section, and a call to action. On narrow screens, use the menu button to open the navigation. Login and registration retain their working authentication behavior and share the same visual design system. Forgot-password requests and reset links use the protected, rate-limited database-backed workflow described above.

Serve the application with the PHP executable that has `pdo_mysql` enabled. The WAMP commands above use PHP 8.3.28. Routes are handled by `public/router.php` for the development server and `public/.htaccess` for Apache.

## Testing

Run the environment and homepage regression checks with:

```powershell
& 'C:\wamp64\bin\php\php8.3.28\php.exe' tests\run.php
```

After applying migrations, run the database-backed authentication checks, including remembered-login restoration and logout revocation:

```powershell
& 'C:\wamp64\bin\php\php8.3.28\php.exe' tests\auth.php
```

Run password-reset, account-activity, and database checks with:

```powershell
& 'C:\wamp64\bin\php\php8.3.28\php.exe' tests\password_reset.php
```

Run email verification checks with:

```powershell
& 'C:\wamp64\bin\php\php8.3.28\php.exe' tests\email_verification.php
```

Password reset and email verification tests use in-memory mailers and do not send real email. Verify delivery separately after configuring SMTP in `.env`.

The authentication integration suite also exercises email verification, profile updates, current-password verification, password replacement, invalidation of other authenticated sessions, activity/notification privacy, notification acknowledgement, and cascading activity deletion.

Check PHP syntax with:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { & 'C:\wamp64\bin\php\php8.3.28\php.exe' -l $_.FullName }
```

Database connection, migrations, and authentication checks require a running MySQL server and valid `.env` credentials. SMTP delivery additionally requires the configured provider settings and outbound network access.
