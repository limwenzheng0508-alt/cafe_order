# Order Table (Café Reservation)

This small PHP project provides a reservation form and server-side booking logic.

## What I changed / improved

- Added robust `reserve.php` with transactions, prepared statements, and validation.
- Hardened `db.php` (charset, mysqli error reporting, safer connection error message).
- Added CSRF protection (session token) on the form and server-side validation.
- Improved front-end UX: disabled submit button while sending, better error handling, reset on success.
- Escaped menu item output to avoid XSS and set date input min/max (today → +365 days).
- Validated menu item IDs exist before adding to reservation.
- Added server-side checks for booking window, email and phone formats.

## Quick setup / test

1. Ensure PHP and MySQL are running (e.g., XAMPP on Windows).
2. Database connection settings are in `db.php` (adjust username/password if needed).
3. Place the project in your web root and open `index.html` in the browser.
4. Try making reservations and check the `Reservation`, `Customer`, and `ReservationMenuItem` tables.

## Next improvements you might want

- Send email confirmations to customers.
- Implement rate-limiting or CAPTCHA to avoid abuse.
- Add unit/integration tests for booking logic.

## Admin UI

 - `admin.php` provides a simple admin interface to list reservations, cancel them, reassign table numbers, filter and export CSV.
 - Admin now uses a session login. Use [admin_login.php](admin_login.php) to sign in. Default credentials are in `config.php` (change them immediately):

	- username: `admin`
	- password: `admin123`

Open [admin_login.php](admin_login.php) to sign in, then visit [admin.php](admin.php) to manage reservations.

## Export to Excel

- There is an `Export Excel` link on the admin page.
- If you install PhpSpreadsheet via Composer (`composer require phpoffice/phpspreadsheet`), `export_excel.php` will produce a true `.xlsx` file. If PhpSpreadsheet is not installed, it will fall back to an Excel-compatible `.xls` HTML file that Excel can open.

To enable `.xlsx` export:

```bash
composer require phpoffice/phpspreadsheet
```


## Phone validation

- The reservation form now includes a `country` selector and client-side phone validation for Malaysia, US, and UK. Server-side validation mirrors the same checks.

If you want, I can extend the admin UI (search/filter, reassign tables, export CSV) or integrate email confirmations — tell me which feature to add next.
