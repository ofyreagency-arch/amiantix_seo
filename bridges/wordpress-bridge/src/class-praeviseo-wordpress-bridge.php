<?php

declare(strict_types=1);

namespace PraeviseoWordPressBridge;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class PraeviseoWordPressBridge
{
    private const OPTION_KEY = 'praeviseo_bridge_settings';

    public static function boot(string $pluginFile): void
    {
        $instance = new self();
        add_action('admin_menu', [$instance, 'registerAdminPage']);
        add_action('admin_post_praeviseo_connect', [$instance, 'handleConnect']);
        add_action('init', [$instance, 'registerPostType']);
        add_action('rest_api_init', [$instance, 'registerRestRoutes']);
    }

    public function registerAdminPage(): void
    {
        add_menu_page(
            'PraeviSEO',
            'PraeviSEO',
            'manage_options',
            'praeviseo-bridge',
            [$this, 'renderAdminPage'],
            'dashicons-chart-line'
        );
    }

    public function renderAdminPage(): void
    {
        $settings = $this->settings();
        ?>
        <div class="wrap">
            <h1>PraeviSEO</h1>
            <p>Collez votre code de connexion puis cliquez sur Connect site.</p>
            <?php if (! empty($settings['status_message'])) : ?>
                <div class="notice notice-success"><p><?php echo esc_html((string) $settings['status_message']); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('praeviseo_connect'); ?>
                <input type="hidden" name="action" value="praeviseo_connect">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="praeviseo_connection_code">Code de connexion</label></th>
                        <td><input name="connection_code" id="praeviseo_connection_code" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="praeviseo_url">URL PraeviSEO</label></th>
                        <td><input name="praeviseo_url" id="praeviseo_url" class="regular-text" value="<?php echo esc_attr((string) ($settings['praeviseo_url'] ?? 'https://app.praeviseo.com')); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="publication_prefix">Section publique</label></th>
                        <td><input name="publication_prefix" id="publication_prefix" class="regular-text" value="<?php echo esc_attr((string) ($settings['publication_prefix'] ?? 'ressources')); ?>"></td>
                    </tr>
                </table>
                <?php submit_button('Connect site'); ?>
            </form>
        </div>
        <?php
    }

    public function handleConnect(): void
    {
        check_admin_referer('praeviseo_connect');

        $praeviseoUrl = rtrim((string) ($_POST['praeviseo_url'] ?? 'https://app.praeviseo.com'), '/');
        $prefix = trim((string) ($_POST['publication_prefix'] ?? 'ressources'), '/');
        $code = trim((string) ($_POST['connection_code'] ?? ''));

        $response = wp_remote_post($praeviseoUrl.'/api/bridge/connect', [
            'timeout' => 12,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'connection_code' => $code,
                'app_url' => rtrim((string) home_url(), '/'),
                'bridge' => 'wordpress_bridge',
                'publication_prefix' => $prefix !== '' ? $prefix : null,
            ]),
        ]);

        if (is_wp_error($response)) {
            wp_die(esc_html($response->get_error_message()));
        }

        $payload = json_decode((string) wp_remote_retrieve_body($response), true);

        if ((int) wp_remote_retrieve_response_code($response) !== 200 || ! is_array($payload)) {
            wp_die(esc_html((string) wp_remote_retrieve_body($response)));
        }

        update_option(self::OPTION_KEY, [
            'praeviseo_url' => $praeviseoUrl,
            'bridge_secret' => (string) ($payload['bridge_secret'] ?? ''),
            'site_id' => (string) ($payload['site_id'] ?? ''),
            'publication_prefix' => (string) (($payload['publication_prefix'] ?? '') ?: 'ressources'),
            'status_message' => 'Site connected successfully.',
        ]);

        wp_safe_redirect(admin_url('admin.php?page=praeviseo-bridge'));
        exit;
    }

    public function registerPostType(): void
    {
        register_post_type('praeviseo_page', [
            'label' => 'PraeviSEO Pages',
            'public' => true,
            'show_ui' => false,
            'rewrite' => ['slug' => $this->publicationPrefix()],
            'supports' => ['title', 'editor', 'custom-fields', 'excerpt'],
        ]);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('praeviseo/v1', '/publish', [
            'methods' => 'POST',
            'callback' => [$this, 'publish'],
            'permission_callback' => [$this, 'authorizeSignedRequest'],
        ]);
    }

    public function authorizeSignedRequest(WP_REST_Request $request): bool
    {
        $settings = $this->settings();
        $secret = (string) ($settings['bridge_secret'] ?? '');

        if ($secret === '') {
            return false;
        }

        $timestamp = (string) $request->get_header('x-praeviseo-timestamp');
        $signature = (string) $request->get_header('x-praeviseo-signature');
        $body = (string) $request->get_body();
        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        return hash_equals($expected, $signature);
    }

    public function publish(WP_REST_Request $request): WP_REST_Response
    {
        $payload = $request->get_json_params();
        $page = is_array($payload['page'] ?? null) ? $payload['page'] : [];

        $postId = wp_insert_post([
            'post_type' => 'praeviseo_page',
            'post_status' => 'publish',
            'post_name' => sanitize_title((string) ($page['slug'] ?? '')),
            'post_title' => (string) ($page['title'] ?? ''),
            'post_content' => (string) ($page['content'] ?? ''),
            'post_excerpt' => (string) ($page['meta_description'] ?? ''),
        ], true);

        if ($postId instanceof WP_Error) {
            return new WP_REST_Response(['status' => 'error', 'message' => $postId->get_error_message()], 500);
        }

        update_post_meta((int) $postId, '_praeviseo_h1', (string) ($page['h1'] ?? ''));
        update_post_meta((int) $postId, '_praeviseo_faq_json', wp_json_encode($page['faq'] ?? []));
        update_post_meta((int) $postId, '_praeviseo_schema_json', wp_json_encode($page['schema'] ?? []));
        update_post_meta((int) $postId, '_praeviseo_internal_links_json', wp_json_encode($page['internal_links'] ?? []));
        update_post_meta((int) $postId, '_praeviseo_canonical_url', (string) ($page['canonical_url'] ?? ''));
        update_post_meta((int) $postId, '_praeviseo_cluster', (string) ($page['cluster'] ?? ''));
        update_post_meta((int) $postId, '_praeviseo_is_noindex', ! empty($page['forced_noindex']) ? '1' : '0');

        return new WP_REST_Response([
            'status' => 'ok',
            'post_id' => $postId,
            'live_url' => get_permalink((int) $postId),
        ], 200);
    }

    /**
     * @return array<string,mixed>
     */
    private function settings(): array
    {
        $settings = get_option(self::OPTION_KEY, []);

        return is_array($settings) ? $settings : [];
    }

    private function publicationPrefix(): string
    {
        $prefix = trim((string) ($this->settings()['publication_prefix'] ?? 'ressources'), '/');

        return $prefix !== '' ? $prefix : 'ressources';
    }
}
