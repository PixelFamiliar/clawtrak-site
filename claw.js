/**
 * claw.js v1.0.0
 * The Agentic Web Tracker by ClawTrak
 * https://clawtrak.com
 * 
 * Embed: <script defer data-site="YOUR_SITE_ID" src="https://clawtrak.com/claw.js"></script>
 */
(function() {
    // Ensure we only run once
    if (window._clawTrakInitialized) return;
    window._clawTrakInitialized = true;

    // Default configuration
    var CONFIG = {
        ingestUrl: 'https://api.clawtrak.com/ingest', // Placeholder for actual ingest
        siteId: null
    };

    // Extract site ID from the script tag if provided
    var scriptElement = document.currentScript;
    if (scriptElement) {
        CONFIG.siteId = scriptElement.getAttribute('data-site');
        var customEndpoint = scriptElement.getAttribute('data-endpoint');
        if (customEndpoint) CONFIG.ingestUrl = customEndpoint;
    }

    /**
     * Gathers signals specifically useful for detecting programmatic 
     * AI scrapers, headless browsers, and automated crawlers.
     */
    function gatherSignals() {
        var signals = {
            // Core identity
            ua: navigator.userAgent || 'unknown',
            url: window.location.href,
            ref: document.referrer || '',
            domain: window.location.hostname,
            path: window.location.pathname,
            sid: CONFIG.siteId,
            ts: Date.now(),
            
            // Environment context
            lang: navigator.language || '',
            
            // Viewport & screen (bots often have 0x0 or weird dimensions)
            vp_w: window.innerWidth || 0,
            vp_h: window.innerHeight || 0,
            scr_w: window.screen ? window.screen.width : 0,
            scr_h: window.screen ? window.screen.height : 0,
            
            // Automation & Headless flags
            webdriver: !!navigator.webdriver,
            headless: false,
            phantom: !!(window._phantom || window.callPhantom || window.__nightmare)
        };

        // Advanced Chrome Headless detection
        if (navigator.userAgent.indexOf('HeadlessChrome') !== -1) {
            signals.headless = true;
        }
        if (window.chrome && !window.chrome.runtime) {
            // Suspicious Chrome environment
        }

        return signals;
    }

    /**
     * Dispatch the payload to the ingest API
     */
    function dispatch(payload) {
        if (!payload.sid) {
            console.warn('[ClawTrak] Missing data-site attribute on script tag.');
            // We still proceed, but it might be rejected by the backend missing a Site ID
        }

        var jsonStr = JSON.stringify(payload);
        var blob = new Blob([jsonStr], { type: 'application/json' });

        // Use sendBeacon for fire-and-forget, fallback to fetch/XHR
        if (navigator.sendBeacon) {
            var queued = navigator.sendBeacon(CONFIG.ingestUrl, blob);
            if (!queued && window.fetch) {
                // sendBeacon returning false means payload is too large or queue is full
                fetch(CONFIG.ingestUrl, { method: 'POST', body: jsonStr, keepalive: true }).catch(function(){});
            }
        } else if (window.fetch) {
            fetch(CONFIG.ingestUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: jsonStr,
                keepalive: true
            }).catch(function(){});
        }
    }

    // Execute immediately 
    var signals = gatherSignals();
    dispatch(signals);

})();
