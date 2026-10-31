/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 *
 * Google reCAPTCHA (v2 checkbox, v2 invisible, v3) on Zero forms, for the
 * storefront CSP (no inline code, no eval). Magento's own reCaptcha.js needs
 * RequireJS and Knockout, which the Zero theme does not load. The server side
 * is Magento's: it reads the POST field g-recaptcha-response.
 *
 * Markup (the theme's override of ReCaptchaFrontendUi's recaptcha.phtml):
 * - [data-zero-recaptcha="{json}"]  the container: {for, rendering, invisible, form}
 *     rendering = Magento's UI config (sitekey, size, theme, badge, hl); form =
 *     id of a form the container belongs to but is not inside (the footer
 *     newsletter): the container moves right after that form
 * - [data-zero-recaptcha-required]  message when the checkbox is not ticked
 * - [data-zero-recaptcha-failed]    message when Google's script cannot load
 * - [data-zero-recaptcha-widget]    where the widget is rendered
 * - [data-zero-recaptcha-error]     where messages show (role="alert")
 *
 * Google's script is added once, when a container comes near the viewport, or
 * its form gets focus or is submitted. A submit without a token is stopped:
 * the checkbox shows the message, the invisible types (v2 invisible and v3,
 * both executed by widget id as Magento does) run the check and then submit
 * the form again. A token works once, so it is reset after an HTMX request.
 * Without JavaScript the submit button stays locked, as in stock Magento.
 * If Google's script cannot load (blocked, offline), every reCAPTCHA form on
 * the page refuses to submit and shows the failed message; the server would
 * refuse a post without a token anyway.
 */
(function () {
    'use strict';

    if (window.zeroRecaptcha) {
        return;
    }
    window.zeroRecaptcha = true;

    var API = 'https://www.google.com/recaptcha/api.js';
    var COMPACT_BELOW = 304;
    var items = [];
    var state = 'idle'; // idle, loading, ready, failed

    /** Cleared, unhidden, then filled on the next frame, so screen readers announce it every time. */
    function showError(item, message) {
        if (item.errorEl) {
            item.errorEl.textContent = '';
            item.errorEl.hidden = false;
            window.requestAnimationFrame(function () {
                item.errorEl.textContent = message;
            });
        }
    }

    function alive(item) {
        return item.box.isConnected && item.form.isConnected;
    }

    /** Drops items whose form or container left the document (HTMX swaps). */
    function prune() {
        items = items.filter(function (item) {
            if (alive(item)) {
                return true;
            }
            if (item.observer) {
                item.observer.disconnect();
            }

            return false;
        });
    }

    function hideError(item) {
        if (item.errorEl) {
            item.errorEl.hidden = true;
        }
    }

    /** The token goes into the form: the container may sit outside it. */
    function setToken(item, token) {
        var field = item.form.querySelector('input[type="hidden"][data-zero-recaptcha-token]');
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = 'g-recaptcha-response';
            field.setAttribute('data-zero-recaptcha-token', '');
            item.form.appendChild(field);
        }
        field.value = token;
    }

    function submitAgain(item) {
        var submitter = item.submitter;

        item.submitter = null;
        if (typeof item.form.requestSubmit === 'function') {
            item.form.requestSubmit(submitter && item.form.contains(submitter) ? submitter : undefined);
        } else {
            item.form.submit();
        }
    }

    function isVisible(item) {
        return item.box.getClientRects().length > 0;
    }

    function render(item) {
        var rendering = {};
        var width;

        if (item.id !== null || state !== 'ready' || !alive(item)) {
            return;
        }
        // A checkbox in a closed <details> has no width yet: render it when it opens.
        if (!item.cfg.invisible && !isVisible(item)) {
            return;
        }
        Object.keys(item.cfg.rendering || {}).forEach(function (key) {
            if (key !== 'hl' && item.cfg.rendering[key] !== '' && item.cfg.rendering[key] !== null) {
                rendering[key] = item.cfg.rendering[key];
            }
        });
        width = item.box.clientWidth;
        if (!item.cfg.invisible && rendering.size === 'normal' && width > 0 && width < COMPACT_BELOW) {
            rendering.size = 'compact';
        }
        rendering.callback = function (token) {
            hideError(item);
            setToken(item, token);
            item.pending = 0;
            if (item.cfg.invisible) {
                submitAgain(item);
            }
        };
        rendering['expired-callback'] = function () {
            setToken(item, '');
        };
        rendering['error-callback'] = function () {
            item.pending = 0;
            showError(item, item.failed);
        };
        try {
            item.id = window.grecaptcha.render(item.widget, rendering);
        } catch (e) {
            item.pending = 0;
            showError(item, item.failed);
            return;
        }
        if (item.observer) {
            item.observer.disconnect();
            item.observer = null;
        }
        if (item.executeWhenRendered) {
            item.executeWhenRendered = false;
            window.grecaptcha.execute(item.id);
        }
    }

    window.zeroRecaptchaOnload = function () {
        state = 'ready';
        window.grecaptcha.ready(function () {
            prune();
            items.forEach(render);
        });
    };

    function loadApi() {
        var script;
        var hl;

        if (state !== 'idle') {
            return;
        }
        state = 'loading';
        hl = items.length && items[0].cfg.rendering ? items[0].cfg.rendering.hl : '';
        script = document.createElement('script');
        script.src = API + '?onload=zeroRecaptchaOnload&render=explicit' + (hl ? '&hl=' + encodeURIComponent(hl) : '');
        script.async = true;
        script.onerror = function () {
            state = 'failed';
            prune();
            items.forEach(function (item) {
                showError(item, item.failed);
            });
        };
        document.head.appendChild(script);
    }

    function unlockButtons(form) {
        form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]')
            .forEach(function (button) {
                button.removeAttribute('disabled');
            });
    }

    function bind(box) {
        var cfg;
        var form;
        var item;
        var observer;

        box.setAttribute('data-zero-recaptcha-bound', '');
        try {
            cfg = JSON.parse(box.getAttribute('data-zero-recaptcha'));
        } catch (e) {
            return;
        }
        if (!cfg) {
            return;
        }
        form = cfg.form ? document.getElementById(cfg.form) : box.closest('form');
        if (!form || form.tagName !== 'FORM') {
            return;
        }
        if (cfg.form) {
            form.insertAdjacentElement('afterend', box);
        }
        item = {
            box: box,
            cfg: cfg,
            form: form,
            widget: box.querySelector('[data-zero-recaptcha-widget]') || box,
            errorEl: box.querySelector('[data-zero-recaptcha-error]'),
            required: box.getAttribute('data-zero-recaptcha-required') || '',
            failed: box.getAttribute('data-zero-recaptcha-failed') || '',
            id: null,
            submitter: null,
            pending: 0, // time of the running invisible check, 0 when none
            observer: null,
            executeWhenRendered: false
        };
        items.push(item);
        unlockButtons(form);
        form.addEventListener('focusin', loadApi);

        if (typeof window.IntersectionObserver === 'function') {
            observer = new window.IntersectionObserver(function (entries) {
                if (entries.some(function (entry) { return entry.isIntersecting; })) {
                    loadApi();
                    render(item); // does nothing before the API is ready
                }
            }, {rootMargin: '200px'});
            item.observer = observer;
            observer.observe(box);
        } else {
            loadApi();
        }
    }

    function scan(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var boxes = Array.prototype.slice.call(
            scope.querySelectorAll('[data-zero-recaptcha]:not([data-zero-recaptcha-bound])')
        );

        if (scope.matches && scope.matches('[data-zero-recaptcha]:not([data-zero-recaptcha-bound])')) {
            boxes.push(scope);
        }
        boxes.forEach(bind);
    }

    function itemOf(form) {
        prune();
        for (var i = 0; i < items.length; i++) {
            if (items[i].form === form) {
                return items[i];
            }
        }

        return null;
    }

    document.addEventListener('submit', function (event) {
        var item = itemOf(event.target);

        if (!item) {
            return;
        }
        if (state === 'failed') {
            event.preventDefault();
            event.stopImmediatePropagation();
            showError(item, item.failed);
            return;
        }
        if (state === 'ready') {
            render(item);
        }
        if (item.id !== null && window.grecaptcha.getResponse(item.id)) {
            setToken(item, window.grecaptcha.getResponse(item.id));
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        if (!item.cfg.invisible) {
            showError(item, item.required);
            loadApi();
            return;
        }
        // A check just started: its callback submits the form. (A dismissed challenge gives no callback, so
        // only a second submit within a moment is ignored.)
        if (item.pending && Date.now() - item.pending < 1500) {
            return;
        }
        item.pending = Date.now();
        item.submitter = event.submitter || null;
        if (item.id === null) {
            item.executeWhenRendered = true;
            loadApi();
        } else {
            window.grecaptcha.execute(item.id);
        }
    }, true);

    document.addEventListener('htmx:afterRequest', function (event) {
        var form = event.target instanceof Element ? event.target.closest('form') : null;
        var item = form ? itemOf(form) : null;

        if (item && item.id !== null) {
            window.grecaptcha.reset(item.id);
            setToken(item, '');
            item.pending = 0;
        }
    });

    document.addEventListener('htmx:load', function (event) {
        scan(event.detail && event.detail.elt);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            scan(document);
        });
    } else {
        scan(document);
    }
}());
