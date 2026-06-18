# EventHub

EventHub is a starter PHP/MySQL project scaffold for event browsing and booking.

## Structure

- `config/` database connection wrapper
- `classes/` OOP models for users, events, and bookings
- `public/` web-facing pages and shared includes
- `assets/` custom CSS

## Run locally

1. Install PHP 8.1+ and MySQL.
2. Set your database environment variables if needed:
   - `DB_HOST`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASSWORD`
3. Start the PHP server from the project root:

```bash
php -S 127.0.0.1:8000 -t public
```

4. Open `http://127.0.0.1:8000` in your browser.

## Notes

The scaffold includes the requested project layout and Bootstrap-based pages. The persistence, authentication, encryption, and role enforcement logic still need to be implemented against a real schema.
