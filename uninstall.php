<?php
/**
 * Удаление плагина HTML & XML Site Map Generator
 * 
 * @package HTML_SITE_MAP_GENERATOR
 */

// Защита от прямого доступа
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Удаляем настройки плагина
delete_option('html_site_map_generator_options');
delete_option('html_sitemap_cache_version');

// Если используется multisite, удаляем настройки для всех сайтов
if (is_multisite()) {
    $sites = get_sites();
    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);
        delete_option('html_site_map_generator_options');
        delete_option('html_sitemap_cache_version');
        
        // Удаляем файл sitemap.xml для каждого сайта
        $sitemap_path = ABSPATH . 'sitemap.xml';
        if (file_exists($sitemap_path)) {
            unlink($sitemap_path);
        }
        
        restore_current_blog();
    }
} else {
    // Удаляем файл sitemap.xml
    $sitemap_path = ABSPATH . 'sitemap.xml';
    if (file_exists($sitemap_path)) {
        unlink($sitemap_path);
    }
}