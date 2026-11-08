# Zero_Core

The storefront core of **Zero for Magento Open Source**: what the Zero theme
needs from the server so pages work without RequireJS, jQuery or Knockout.

- HTMX endpoints for private content: the cart counter, minicart, welcome
  message, account links, wishlist and compare counters, messages, form key
  and session bootstrap (`Controller/`), so full-page-cached pages stay
  cacheable.
- View models for the theme's templates: home page sections, product page,
  catalog, customer account (`ViewModel/`).
- Region lookup, search suggestions, captcha refresh, recently viewed and
  compared products.
- Admin: Stores > Configuration > **Zero**.

Install it on its own to build your own theme on top of it, or install the
theme (`zerofront/theme-frontend-zero`), which requires it.

## Install

Requires Magento Open Source 2.4.7–2.4.9 and PHP 8.2–8.4 with the GD extension built with WebP (`php -r 'var_dump(function_exists("imagewebp"));'` says `true`; Magento's own PHP requirements cover the rest). Without WebP in GD, Zero skips its WebP image copies and serves the originals.

```bash
composer require zerofront/module-zero-core
bin/magento setup:upgrade
bin/magento setup:di:compile           # production mode
bin/magento setup:static-content:deploy # production mode
bin/magento cache:flush
```

Part of [`zerofront/zero`](https://zerofront.dev), which installs the Zero theme and every Zero module at once.

What changed in each release: [CHANGELOG.md](CHANGELOG.md).

## License

OSL-3.0 or AFL-3.0, your choice: see `NOTICE.md`.

---

Zero is made by [Fixstacks](https://fixstacks.com).
Documentation: https://zerofront.dev/docs. Demo store: https://demo.zerofront.dev.
