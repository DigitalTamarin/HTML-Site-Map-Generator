<?php
/**
 * Plugin Name: HTML & XML Site Map Generator
 * Plugin URI: https://denistamarin.ru/html-site-map-generator
 * Description: Advanced HTML and XML sitemap generator with admin settings. Генератор HTML и XML карт сайта с настройками в админке.
 * Version: 1.2.0
 * Author: Denis Tamarin
 * Author URI: https://denistamarin.ru/
 * License: GPL v2 or later
 * Text Domain: html-site-map-generator
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

// Проверка на конфликт
if (defined('HTML_SITE_MAP_GENERATOR_VERSION')) {
    return;
}

// Константы плагина
define('HTML_SITE_MAP_GENERATOR_VERSION', '4.0.0');
define('HTML_SITE_MAP_GENERATOR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HTML_SITE_MAP_GENERATOR_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('HTML_SITE_MAP_GENERATOR_BASENAME', plugin_basename(__FILE__));
define('HTML_SITE_MAP_GENERATOR_SITEMAP_PATH', ABSPATH . 'sitemap.xml');

// Подключаем основной класс
require_once HTML_SITE_MAP_GENERATOR_PLUGIN_PATH . 'includes/class-html-site-map-generator.php';

/**
 * Инициализация плагина
 */
function html_site_map_generator_init() {
    // Загрузка перевода
    load_plugin_textdomain(
        'html-site-map-generator',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
    
    // Инициализируем модуль
    HTML_Site_Map_Generator::init();
}
add_action('plugins_loaded', 'html_site_map_generator_init');

/**
 * Активация плагина
 */
function html_site_map_generator_activate() {
    // Устанавливаем настройки по умолчанию
    $default_options = array(
        'show_pages' => 'true',
        'show_posts' => 'true',
        'show_categories' => 'true',
        'show_authors' => 'false',
        'show_tags' => 'false',
        'show_date' => 'false',
        'show_comments_count' => 'false',
        'exclude_posts' => '',
        'include_posts' => '',
        'exclude_categories' => '',
        'include_categories' => '',
        'posts_from_categories' => '',
        'orderby' => 'title',
        'order' => 'ASC',
        'posts_per_section' => '0',
        'include_post_types' => array(),
        'include_taxonomies' => array(),
        'xml_enabled' => 'true',
        'xml_auto_update' => 'true',
        'xml_change_frequency' => 'weekly',
        'xml_priority' => '0.7'
    );
    
    add_option('html_site_map_generator_options', $default_options);
    
    // Создаем первоначальный sitemap.xml
    HTML_Site_Map_Generator::generate_sitemap_file();
}
register_activation_hook(__FILE__, 'html_site_map_generator_activate');

/**
 * Деактивация плагина
 */
function html_site_map_generator_deactivate() {
    // Не удаляем файл sitemap.xml при деактивации
    // Пользователь может временно отключить плагин
}
register_deactivation_hook(__FILE__, 'html_site_map_generator_deactivate');

/**
 * Удаление плагина
 */
function html_site_map_generator_uninstall() {
    // Удаляем настройки
    delete_option('html_site_map_generator_options');
    delete_option('html_sitemap_cache_version');
    
    // Удаляем файл sitemap.xml
    if (file_exists(HTML_SITE_MAP_GENERATOR_SITEMAP_PATH)) {
        unlink(HTML_SITE_MAP_GENERATOR_SITEMAP_PATH);
    }
}
register_uninstall_hook(__FILE__, 'html_site_map_generator_uninstall');

/**
 * Добавляем ссылку на настройки в списке плагинов
 */
function html_site_map_generator_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=html-site-map-generator-settings') . '">' . __('Настройки', 'html-site-map-generator') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . HTML_SITE_MAP_GENERATOR_BASENAME, 'html_site_map_generator_plugin_action_links');
