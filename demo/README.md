# Cookie Consent Bundle — Symfony 8 demo

FrankenPHP demo with SQLite, cookie consent modal, optional consent logging (`demo_nowo_cookie_consent_log`), and a configuration CRUD per locale.

```bash
make up
# Demo started at: http://localhost:8014
```

Use the top navigation **Cookie consent config** to manage modal copy (title, intro, read-more label) stored in SQLite. Use the **language dropdown** (EN / ES / IT / FR) in the navbar to switch locale and preview the modal in each language.

```bash
make test
make down
```
