# Cookie Consent Bundle — Symfony 8.1 demos

FrankenPHP demos with SQLite, cookie consent modal, optional consent logging (`demo_nowo_cookie_consent_log`), and a configuration CRUD per locale.

| Demo | UI | Port |
| --- | --- | --- |
| `symfony8/` | Bootstrap 5 | **8014** |
| `symfony8-tailwind/` | Tailwind | **8015** |

```bash
make -C demo/symfony8 up
# Demo started at: http://localhost:8014

make -C demo/symfony8-tailwind up
# Demo started at: http://localhost:8015
```

Use the top navigation **Cookie consent config** to manage modal copy (title, intro, read-more label) stored in SQLite. Use the **language dropdown** (EN / ES / IT / FR) in the navbar to switch locale and preview the modal in each language.

```bash
make test
make down
```
