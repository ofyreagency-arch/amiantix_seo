<?php
/**
 * Plugin Name: PraeviSEO Bridge
 * Description: Official lightweight WordPress bridge to connect a site to PraeviSEO.
 * Version: 0.1.0
 * Author: PraeviSEO
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__.'/src/class-praeviseo-wordpress-bridge.php';

\PraeviseoWordPressBridge\PraeviseoWordPressBridge::boot(__FILE__);
