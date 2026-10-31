# Changelog: zerofront/module-zero-core

## 1.3.6

- Google reCAPTCHA (Magento's own ReCaptcha modules) now works on the Zero theme's forms without RequireJS: `view/frontend/web/js/recaptcha.js` drives v2 checkbox, v2 invisible and v3. `bin/magento zero:compat:report` lists the modules the theme covers through templates alone.
- The category sidebar's Compare Products and My Wish List come with the session bootstrap instead of a request of their own; the old `/zero/sidebar/` address keeps working for pages cached before the update.
- The session fragments are cached per store's bootstrap address, so store views under a path of one domain no longer share them.
- Product card cache entries include the swatch filters that pick a configurable's picture (`?color=50` shows the matching variant).
- The home page's featured products no longer convert a price that is already in the display currency a second time (wrong amounts in a non-base currency).
- The product's category eyebrow only names categories of the current store's tree that are active.
- Search suggestions are tagged with what they list, so saving a category or page refreshes them; an empty result no longer causes a 500 with Magento's built-in page cache.
- A query value, form field or cookie sent as an array no longer causes a 500 anywhere in the module. The store-specific data patch `MoveToZerofrontDev`, shipped in 1.3.5 by mistake, is no longer in the package (it only rewrote fixstacks.com addresses in stored content).

Earlier releases (1.3.5 and before): see the Git tags.
