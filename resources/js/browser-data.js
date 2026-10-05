/*
 * Opayo Pi: browser data for 3D Secure v2.
 *
 * Fills hidden inputs browserLanguage, browserColorDepth, browserScreenHeight,
 * browserScreenWidth and browserTz in a payment form, creating them if they
 * are missing. On the server, read them with
 * Academe\Opayo\Pi\Checkout\BrowserData::fromArray($_POST).
 *
 * Serve this file from your public assets, load it with a script tag, then
 * call fill() on each payment form any time before it is submitted:
 *
 *   OpayoBrowserData.fill(document.getElementById('pay-form'));
 *
 * No dependencies. Safe to inline in a script element.
 */
(function (global) {
    'use strict';

    var COLOR_DEPTHS = [1, 4, 8, 15, 16, 24, 32, 48];

    function collect() {
        return {
            browserLanguage: navigator.language || 'en-GB',
            browserColorDepth: COLOR_DEPTHS.indexOf(screen.colorDepth) !== -1 ? screen.colorDepth : 24,
            browserScreenHeight: screen.height,
            browserScreenWidth: screen.width,
            browserTz: new Date().getTimezoneOffset()
        };
    }

    function fill(form) {
        var data = collect();

        Object.keys(data).forEach(function (name) {
            var input = form.querySelector('input[name="' + name + '"]');

            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                form.appendChild(input);
            }

            input.value = String(data[name]);
        });
    }

    global.OpayoBrowserData = { collect: collect, fill: fill };
})(window);
