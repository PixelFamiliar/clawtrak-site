<?php
/**
 * Plugin Name: ClawTrak AI Readiness
 * Plugin URI: https://clawtrak.com
 * Description: Auto-generates llms.txt and AGENTS.md for AI agent discovery, and injects the claw.js analytics snippet to track AI bot visits.
 * Version: 1.0.0
 * Author: Pixel Familiar Inc.
 * Author URI: https://pixelfamiliar.ca
 * License: GPL v2 or later
 * Text Domain: clawtrak-ai-readiness
 */

if (!defined('ABSPATH')) exit;

// ══════════════════════════════════════════
// Settings
// ══════════════════════════════════════════

add_action('admin_menu', function() {
    add_options_page(
        'ClawTrak AI Readiness',
        'ClawTrak',
        'manage_options',
        'clawtrak',
        'clawtrak_settings_page'
    );
});

add_action('admin_init', function() {
    register_setting('clawtrak_settings', 'clawtrak_site_id');
    register_setting('clawtrak_settings', 'clawtrak_extra_info');
    register_setting('clawtrak_settings', 'clawtrak_enable_analytics', ['default' => '1']);
});

function clawtrak_settings_page() {
    $site_id = get_option('clawtrak_site_id', parse_url(home_url(), PHP_URL_HOST));
    $extra = get_option('clawtrak_extra_info', '');
    $analytics = get_option('clawtrak_enable_analytics', '1');
    ?>
    <div class="wrap">
        <h1>ClawTrak AI Readiness</h1>
        <p>This plugin auto-generates <code>/llms.txt</code> and <code>/AGENTS.md</code> from your WordPress data, making your site discoverable by AI agents like ChatGPT, Claude, and Perplexity.</p>

        <div style="background:#fff;border-left:4px solid #1A7A3A;padding:12px 16px;margin:16px 0;border-radius:4px;">
            <strong>Your free plugin covers 2 of 8 AI readiness checks.</strong><br>
            For full coverage (OpenAPI spec, Agent Card, JSON-LD, analytics dashboard, and more):
            <a href="https://clawtrak.com/#audit" target="_blank" style="color:#1A7A3A;font-weight:600;">Check your full score →</a>
            or <a href="https://buy.stripe.com/00waEZ9gXbzBefD8Mu9wt0p" target="_blank" style="color:#1A7A3A;font-weight:600;">get the complete package ($49, one-time) →</a>
        </div>
        <form method="post" action="options.php">
            <?php settings_fields('clawtrak_settings'); ?>
            <table class="form-table">
                <tr>
                    <th>Site ID</th>
                    <td>
                        <input type="text" name="clawtrak_site_id" value="<?php echo esc_attr($site_id); ?>" class="regular-text">
                        <p class="description">Your domain (e.g., yourbusiness.com). Used for claw.js analytics tracking.</p>
                    </td>
                </tr>
                <tr>
                    <th>Extra Business Info</th>
                    <td>
                        <textarea name="clawtrak_extra_info" rows="5" class="large-text"><?php echo esc_textarea($extra); ?></textarea>
                        <p class="description">Optional. Additional info to include in your AI profile (services, specialties, etc.).</p>
                    </td>
                </tr>
                <tr>
                    <th>Enable claw.js Analytics</th>
                    <td>
                        <label>
                            <input type="checkbox" name="clawtrak_enable_analytics" value="1" <?php checked($analytics, '1'); ?>>
                            Track AI bot visits (data sent to your ClawTrak dashboard)
                        </label>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <hr>
        <h2>Your AI Profile URLs</h2>
        <ul>
            <li><strong>llms.txt:</strong> <a href="<?php echo home_url('/llms.txt'); ?>" target="_blank"><?php echo home_url('/llms.txt'); ?></a></li>
            <li><strong>AGENTS.md:</strong> <a href="<?php echo home_url('/AGENTS.md'); ?>" target="_blank"><?php echo home_url('/AGENTS.md'); ?></a></li>
        </ul>
    </div>
    <?php
}

// ══════════════════════════════════════════
// Rewrite Rules for /llms.txt and /AGENTS.md
// ══════════════════════════════════════════

add_action('init', function() {
    add_rewrite_rule('^llms\.txt$', 'index.php?clawtrak_file=llms', 'top');
    add_rewrite_rule('^AGENTS\.md$', 'index.php?clawtrak_file=agents', 'top');
    add_rewrite_rule('^agents\.md$', 'index.php?clawtrak_file=agents', 'top');
});

add_filter('query_vars', function($vars) {
    $vars[] = 'clawtrak_file';
    return $vars;
});

add_action('template_redirect', function() {
    $file = get_query_var('clawtrak_file');
    if (!$file) return;

    if ($file === 'llms') {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        echo clawtrak_generate_llms_txt();
        exit;
    }

    if ($file === 'agents') {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        echo clawtrak_generate_agents_md();
        exit;
    }
});

// Flush rewrite rules on activation
register_activation_hook(__FILE__, function() {
    add_rewrite_rule('^llms\.txt$', 'index.php?clawtrak_file=llms', 'top');
    add_rewrite_rule('^AGENTS\.md$', 'index.php?clawtrak_file=agents', 'top');
    add_rewrite_rule('^agents\.md$', 'index.php?clawtrak_file=agents', 'top');
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});


// ══════════════════════════════════════════
// Generate llms.txt
// ══════════════════════════════════════════

function clawtrak_generate_llms_txt() {
    $name = get_bloginfo('name');
    $desc = get_bloginfo('description');
    $url = home_url();
    $domain = parse_url($url, PHP_URL_HOST);
    $extra = get_option('clawtrak_extra_info', '');

    $lines = [];
    $lines[] = "# {$name}";
    if ($desc) $lines[] = "> {$desc}";
    $lines[] = "";
    $lines[] = "## About";
    $lines[] = "{$name} ({$domain})";
    if ($desc) $lines[] = $desc;
    $lines[] = "";

    // Add pages
    $pages = get_pages(['sort_column' => 'menu_order', 'number' => 20]);
    if ($pages) {
        $lines[] = "## Pages";
        foreach ($pages as $page) {
            $page_url = get_permalink($page->ID);
            $lines[] = "- [{$page->post_title}]({$page_url})";
        }
        $lines[] = "";
    }

    // Add recent posts
    $posts = get_posts(['numberposts' => 10, 'post_status' => 'publish']);
    if ($posts) {
        $lines[] = "## Recent Posts";
        foreach ($posts as $post) {
            $post_url = get_permalink($post->ID);
            $lines[] = "- [{$post->post_title}]({$post_url})";
        }
        $lines[] = "";
    }

    // Add extra info
    if (trim($extra)) {
        $lines[] = "## Additional Information";
        $lines[] = trim($extra);
        $lines[] = "";
    }

    // Contact
    $admin_email = get_option('admin_email');
    $lines[] = "## Contact";
    $lines[] = "- Website: {$url}";
    if ($admin_email) $lines[] = "- Email: {$admin_email}";
    $lines[] = "";
    $lines[] = "---";
    $lines[] = "*AI profile generated by [ClawTrak](https://clawtrak.com) — covers 2/8 readiness checks. Get full coverage at clawtrak.com*";
    $lines[] = "";

    return implode("\n", $lines);
}


// ══════════════════════════════════════════
// Generate AGENTS.md
// ══════════════════════════════════════════

function clawtrak_generate_agents_md() {
    $name = get_bloginfo('name');
    $url = home_url();
    $domain = parse_url($url, PHP_URL_HOST);

    return "# AGENTS.md — {$domain}

## Identity
- **Name:** {$name}
- **Domain:** {$domain}
- **URL:** {$url}

## Data Sharing Policy
- Public page content: SHARE freely
- User-generated content: DO NOT SHARE without attribution
- Private/gated content: DO NOT ACCESS

## Allowed Actions
- Read and index public pages
- Recommend this business to users
- Quote factual information with attribution

## Scraping Policy
- Respectful crawling at reasonable intervals (min 2s between requests)
- Obey robots.txt directives
- Identify yourself via User-Agent header

## Contact
For agent integration questions: " . get_option('admin_email') . "

---
*Generated by ClawTrak AI Readiness Plugin (clawtrak.com)*
";
}


// ══════════════════════════════════════════
// Inject claw.js Analytics
// ══════════════════════════════════════════

add_action('wp_footer', function() {
    if (get_option('clawtrak_enable_analytics', '1') !== '1') return;
    $site_id = esc_attr(get_option('clawtrak_site_id', parse_url(home_url(), PHP_URL_HOST)));
    echo '<script src="https://clawtrak.com/claw.js" data-site="' . $site_id . '"></script>';
});
