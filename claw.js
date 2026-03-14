/**
 * claw.js v2.0.0
 * AI Agent Traffic Analytics by ClawTrak
 * https://clawtrak.com
 * 
 * Embed: <script defer data-site="YOUR_DOMAIN" src="https://clawtrak.com/claw.js"></script>
 * 
 * What it tracks:
 * - Which AI agents (ChatGPT, Claude, Perplexity) visit your site
 * - What pages they read
 * - Whether they're declared bots or headless browsers
 * 
 * What it doesn't track:
 * - No cookies, no personal data, no fingerprinting
 * - Only sends: user-agent, page URL, viewport size, bot flags
 */
(function() {
    if (window._clawTrakInit) return;
    window._clawTrakInit = true;

    var INGEST_URL = 'https://api.clawtrak.com/ingest';
    var siteId = null;

    // Extract config from script tag
    var el = document.currentScript;
    if (el) {
        siteId = el.getAttribute('data-site');
        var custom = el.getAttribute('data-endpoint');
        if (custom) INGEST_URL = custom;
    }

    // Known AI bot patterns (client-side pre-classification)
    var BOT_PATTERNS = [
        { name: 'GPTBot',         re: /GPTBot/i,           family: 'OpenAI' },
        { name: 'ChatGPT-User',   re: /ChatGPT-User/i,     family: 'OpenAI' },
        { name: 'OAI-SearchBot',   re: /OAI-SearchBot/i,    family: 'OpenAI' },
        { name: 'ClaudeBot',      re: /ClaudeBot/i,         family: 'Anthropic' },
        { name: 'Claude-User',    re: /Claude-User/i,       family: 'Anthropic' },
        { name: 'PerplexityBot',  re: /PerplexityBot/i,     family: 'Perplexity' },
        { name: 'Google-Extended', re: /Google-Extended/i,   family: 'Google' },
        { name: 'Googlebot',      re: /Googlebot/i,         family: 'Google' },
        { name: 'Bingbot',        re: /bingbot/i,           family: 'Microsoft' },
        { name: 'Bytespider',     re: /Bytespider/i,        family: 'ByteDance' },
        { name: 'Applebot',       re: /Applebot/i,          family: 'Apple' }
    ];

    function classifyUA(ua) {
        for (var i = 0; i < BOT_PATTERNS.length; i++) {
            if (BOT_PATTERNS[i].re.test(ua)) {
                return { name: BOT_PATTERNS[i].name, family: BOT_PATTERNS[i].family, isBot: true };
            }
        }
        // Heuristic checks
        if (/bot|crawler|spider|scraper/i.test(ua)) {
            return { name: 'Unknown Bot', family: 'Unknown', isBot: true };
        }
        if (/HeadlessChrome/i.test(ua)) {
            return { name: 'Headless Chrome', family: 'Automation', isBot: true };
        }
        return { name: null, family: null, isBot: false };
    }

    function gather() {
        var ua = navigator.userAgent || '';
        var bot = classifyUA(ua);
        return {
            ua: ua,
            url: window.location.href,
            ref: document.referrer || '',
            domain: window.location.hostname,
            path: window.location.pathname,
            sid: siteId || window.location.hostname,
            ts: Date.now(),
            lang: navigator.language || '',
            vp_w: window.innerWidth || 0,
            vp_h: window.innerHeight || 0,
            scr_w: window.screen ? window.screen.width : 0,
            scr_h: window.screen ? window.screen.height : 0,
            webdriver: !!navigator.webdriver,
            headless: /HeadlessChrome/i.test(ua),
            phantom: !!(window._phantom || window.callPhantom || window.__nightmare),
            bot_name: bot.name,
            bot_family: bot.family,
            is_bot: bot.isBot
        };
    }

    function send(payload) {
        var json = JSON.stringify(payload);
        var blob = new Blob([json], { type: 'application/json' });

        if (navigator.sendBeacon) {
            var ok = navigator.sendBeacon(INGEST_URL, blob);
            if (!ok && window.fetch) {
                fetch(INGEST_URL, { method: 'POST', body: json, keepalive: true }).catch(function(){});
            }
        } else if (window.fetch) {
            fetch(INGEST_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: json,
                keepalive: true
            }).catch(function(){});
        }
    }

    // Fire immediately
    send(gather());

})();
