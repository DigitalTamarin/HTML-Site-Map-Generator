<?php
/**
 * Основной класс модуля HTML карты сайта
 * 
 * @package HTML_SITE_MAP_GENERATOR
 */

if (!defined('ABSPATH')) {
    exit;
}

class HTML_Site_Map_Generator {
    
    private static $options;
    
    /**
     * Инициализация модуля
     */
    public static function init() {
        // Шорткод для HTML карты
        add_shortcode('html_sitemap', array(__CLASS__, 'generate_html_sitemap'));
        
        // Обработка XML карты
        add_action('init', array(__CLASS__, 'handle_xml_sitemap'));
        
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_styles'));
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_init', array(__CLASS__, 'settings_init'));
        
        // Загружаем настройки и устанавливаем значения по умолчанию
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
            'orderby' => 'title',
            'order' => 'ASC',
            'posts_per_section' => '0',
            'include_post_types' => array(),
            'include_taxonomies' => array(),
            'xml_enabled' => 'true',
            'xml_include_posts' => 'true',
            'xml_include_pages' => 'true',
            'xml_include_categories' => 'true',
            'xml_include_custom_post_types' => 'true',
            'xml_change_frequency' => 'weekly',
            'xml_priority' => '0.7'
        );
        
        self::$options = wp_parse_args(get_option('html_site_map_generator_options', array()), $default_options);
        
        // Сохраняем обновленные настройки, если они не существуют
        if (false === get_option('html_site_map_generator_options')) {
            update_option('html_site_map_generator_options', $default_options);
        }
    }
    
    /**
     * Обработка XML карты сайта
     */
    public static function handle_xml_sitemap() {
        // Проверяем, запрашивается ли sitemap.xml
        if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] === '/sitemap.xml') {
            // Проверяем, включена ли XML карта в настройках
            $options = get_option('html_site_map_generator_options', array());
            $xml_enabled = isset($options['xml_enabled']) ? $options['xml_enabled'] : 'true';
            
            if ($xml_enabled === 'true') {
                header('Content-Type: application/xml; charset=utf-8');
                echo self::generate_xml_content();
                exit;
            } else {
                // Если XML отключена, возвращаем 404
                status_header(404);
                exit;
            }
        }
    }
    
    /**
     * Подключение стилей
     */
    public static function enqueue_styles() {
        wp_enqueue_style(
            'html-sitemap-style',
            HTML_SITE_MAP_GENERATOR_PLUGIN_URL . 'assets/css/sitemap-style.css',
            array(),
            HTML_SITE_MAP_GENERATOR_VERSION
        );
    }
    
    /**
     * Добавляем меню в админку
     */
    public static function add_admin_menu() {
        add_options_page(
            __('Карта сайта - Настройки', 'html-site-map-generator'),
            __('Карта сайта', 'html-site-map-generator'),
            'manage_options',
            'html-site-map-generator-settings',
            array(__CLASS__, 'settings_page')
        );
    }
    
    /**
     * Инициализация настроек
     */
    public static function settings_init() {
        register_setting('html_site_map_generator_settings', 'html_site_map_generator_options', array(__CLASS__, 'sanitize_options'));
        
        // Секция основных настроек HTML
        add_settings_section(
            'html_site_map_generator_main_section',
            __('Основные настройки HTML карты', 'html-site-map-generator'),
            array(__CLASS__, 'main_settings_section_callback'),
            'html_site_map_generator_settings'
        );
        
        // Секция настроек ID
        add_settings_section(
            'html_site_map_generator_id_section',
            __('Настройки включения/исключения по ID', 'html-site-map-generator'),
            array(__CLASS__, 'id_settings_section_callback'),
            'html_site_map_generator_settings'
        );
        
        // Секция дополнительных настроек
        add_settings_section(
            'html_site_map_generator_advanced_section',
            __('Дополнительные настройки', 'html-site-map-generator'),
            array(__CLASS__, 'advanced_settings_section_callback'),
            'html_site_map_generator_settings'
        );
        
        // Секция включения типов контента
        add_settings_section(
            'html_site_map_generator_include_section',
            __('Включение дополнительных типов контента', 'html-site-map-generator'),
            array(__CLASS__, 'include_settings_section_callback'),
            'html_site_map_generator_settings'
        );
        
        // Секция настроек XML карты
        add_settings_section(
            'html_site_map_generator_xml_section',
            __('Настройки XML карты сайта', 'html-site-map-generator'),
            array(__CLASS__, 'xml_settings_section_callback'),
            'html_site_map_generator_settings'
        );
        
        // Основные настройки HTML
        add_settings_field(
            'show_pages',
            __('Показывать страницы', 'html-site-map-generator'),
            array(__CLASS__, 'show_pages_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_main_section'
        );
        
        add_settings_field(
            'show_posts',
            __('Показывать записи', 'html-site-map-generator'),
            array(__CLASS__, 'show_posts_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_main_section'
        );
        
        add_settings_field(
            'show_categories',
            __('Показывать категории', 'html-site-map-generator'),
            array(__CLASS__, 'show_categories_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_main_section'
        );
        
        add_settings_field(
            'show_authors',
            __('Показывать авторов', 'html-site-map-generator'),
            array(__CLASS__, 'show_authors_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_main_section'
        );
        
        add_settings_field(
            'show_tags',
            __('Показывать метки', 'html-site-map-generator'),
            array(__CLASS__, 'show_tags_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_main_section'
        );
        
        add_settings_field(
            'show_date',
            __('Показывать даты', 'html-site-map-generator'),
            array(__CLASS__, 'show_date_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_main_section'
        );
        
        add_settings_field(
            'show_comments_count',
            __('Показывать количество комментариев', 'html-site-map-generator'),
            array(__CLASS__, 'show_comments_count_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_main_section'
        );
        
        // Настройки ID
        add_settings_field(
            'exclude_posts',
            __('Исключить записи/страницы (ID)', 'html-site-map-generator'),
            array(__CLASS__, 'exclude_posts_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_id_section'
        );
        
        add_settings_field(
            'include_posts',
            __('Показать только записи/страницы (ID)', 'html-site-map-generator'),
            array(__CLASS__, 'include_posts_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_id_section'
        );
        
        add_settings_field(
            'exclude_categories',
            __('Исключить категории (ID)', 'html-site-map-generator'),
            array(__CLASS__, 'exclude_categories_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_id_section'
        );
        
        add_settings_field(
            'include_categories',
            __('Показать только категории (ID)', 'html-site-map-generator'),
            array(__CLASS__, 'include_categories_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_id_section'
        );
        
        // Дополнительные настройки
        add_settings_field(
            'orderby',
            __('Сортировка по умолчанию', 'html-site-map-generator'),
            array(__CLASS__, 'orderby_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_advanced_section'
        );
        
        add_settings_field(
            'order',
            __('Порядок сортировки', 'html-site-map-generator'),
            array(__CLASS__, 'order_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_advanced_section'
        );
        
        add_settings_field(
            'posts_per_section',
            __('Записей на раздел', 'html-site-map-generator'),
            array(__CLASS__, 'posts_per_section_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_advanced_section'
        );
        
        // Настройки включения
        add_settings_field(
            'include_post_types',
            __('Включить произвольные типы записей', 'html-site-map-generator'),
            array(__CLASS__, 'include_post_types_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_include_section'
        );
        
        add_settings_field(
            'include_taxonomies',
            __('Включить произвольные таксономии', 'html-site-map-generator'),
            array(__CLASS__, 'include_taxonomies_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_include_section'
        );
        
        // Настройки XML
        add_settings_field(
            'xml_enabled',
            __('Включить XML карту', 'html-site-map-generator'),
            array(__CLASS__, 'xml_enabled_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_xml_section'
        );
        
        add_settings_field(
            'xml_include_posts',
            __('Включить записи в XML', 'html-site-map-generator'),
            array(__CLASS__, 'xml_include_posts_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_xml_section'
        );
        
        add_settings_field(
            'xml_include_pages',
            __('Включить страницы в XML', 'html-site-map-generator'),
            array(__CLASS__, 'xml_include_pages_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_xml_section'
        );
        
        add_settings_field(
            'xml_include_categories',
            __('Включить категории в XML', 'html-site-map-generator'),
            array(__CLASS__, 'xml_include_categories_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_xml_section'
        );
        
        add_settings_field(
            'xml_include_custom_post_types',
            __('Включить произвольные типы записей в XML', 'html-site-map-generator'),
            array(__CLASS__, 'xml_include_custom_post_types_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_xml_section'
        );
        
        add_settings_field(
            'xml_change_frequency',
            __('Частота изменений по умолчанию', 'html-site-map-generator'),
            array(__CLASS__, 'xml_change_frequency_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_xml_section'
        );
        
        add_settings_field(
            'xml_priority',
            __('Приоритет по умолчанию', 'html-site-map-generator'),
            array(__CLASS__, 'xml_priority_callback'),
            'html_site_map_generator_settings',
            'html_site_map_generator_xml_section'
        );
    }
    
    /**
     * Санитизация опций
     */
    public static function sanitize_options($input) {
        // Для чекбоксов
        $checkbox_fields = array(
            'show_pages', 'show_posts', 'show_categories', 'show_authors', 
            'show_tags', 'show_date', 'show_comments_count',
            'xml_enabled', 'xml_include_posts', 'xml_include_pages', 
            'xml_include_categories', 'xml_include_custom_post_types'
        );
        
        foreach ($checkbox_fields as $field) {
            $input[$field] = isset($input[$field]) ? 'true' : 'false';
        }
        
        // Для массивов
        $array_fields = array('include_post_types', 'include_taxonomies');
        foreach ($array_fields as $field) {
            $input[$field] = isset($input[$field]) ? (array) $input[$field] : array();
        }
        
        return $input;
    }
    
    /**
     * Колбэк секции основных настроек
     */
    public static function main_settings_section_callback() {
        echo '<p>' . __('Выберите какие основные разделы показывать в HTML карте сайта', 'html-site-map-generator') . '</p>';
    }
    
    /**
     * Колбэк секции настроек ID
     */
    public static function id_settings_section_callback() {
        echo '<p>' . __('Укажите ID для точного контроля отображаемого контента', 'html-site-map-generator') . '</p>';
    }
    
    /**
     * Колбэк секции дополнительных настроек
     */
    public static function advanced_settings_section_callback() {
        echo '<p>' . __('Дополнительные настройки отображения', 'html-site-map-generator') . '</p>';
    }
    
    /**
     * Колбэк секции включения типов контента
     */
    public static function include_settings_section_callback() {
        echo '<p>' . __('Включите дополнительные типы записей и таксономии', 'html-site-map-generator') . '</p>';
    }
    
    /**
     * Колбэк секции настроек XML
     */
    public static function xml_settings_section_callback() {
        echo '<p>' . __('Настройки генерации XML карты сайта для поисковых систем. XML карта доступна по адресу:', 'html-site-map-generator') . ' <code>' . home_url('/sitemap.xml') . '</code></p>';
    }
    
    // Callback функции для полей настроек
    public static function show_pages_callback() {
        $value = isset(self::$options['show_pages']) ? self::$options['show_pages'] : 'true';
        echo '<label><input type="checkbox" name="html_site_map_generator_options[show_pages]" value="true" ' . checked($value, 'true', false) . '> ' . __('Показывать страницы', 'html-site-map-generator') . '</label>';
    }
    
    public static function show_posts_callback() {
        $value = isset(self::$options['show_posts']) ? self::$options['show_posts'] : 'true';
        echo '<label><input type="checkbox" name="html_site_map_generator_options[show_posts]" value="true" ' . checked($value, 'true', false) . '> ' . __('Показывать записи', 'html-site-map-generator') . '</label>';
    }
    
    public static function show_categories_callback() {
        $value = isset(self::$options['show_categories']) ? self::$options['show_categories'] : 'true';
        echo '<label><input type="checkbox" name="html_site_map_generator_options[show_categories]" value="true" ' . checked($value, 'true', false) . '> ' . __('Показывать категории', 'html-site-map-generator') . '</label>';
    }
    
    public static function show_authors_callback() {
        $value = isset(self::$options['show_authors']) ? self::$options['show_authors'] : 'false';
        echo '<label><input type="checkbox" name="html_site_map_generator_options[show_authors]" value="true" ' . checked($value, 'true', false) . '> ' . __('Показывать авторов', 'html-site-map-generator') . '</label>';
    }
    
    public static function show_tags_callback() {
        $value = isset(self::$options['show_tags']) ? self::$options['show_tags'] : 'false';
        echo '<label><input type="checkbox" name="html_site_map_generator_options[show_tags]" value="true" ' . checked($value, 'true', false) . '> ' . __('Показывать метки', 'html-site-map-generator') . '</label>';
    }
    
    public static function show_date_callback() {
        $value = isset(self::$options['show_date']) ? self::$options['show_date'] : 'false';
        echo '<label><input type="checkbox" name="html_site_map_generator_options[show_date]" value="true" ' . checked($value, 'true', false) . '> ' . __('Показывать дату публикации', 'html-site-map-generator') . '</label>';
    }
    
    public static function show_comments_count_callback() {
        $value = isset(self::$options['show_comments_count']) ? self::$options['show_comments_count'] : 'false';
        echo '<label><input type="checkbox" name="html_site_map_generator_options[show_comments_count]" value="true" ' . checked($value, 'true', false) . '> ' . __('Показывать количество комментариев', 'html-site-map-generator') . '</label>';
    }
    
    public static function exclude_posts_callback() {
        $value = isset(self::$options['exclude_posts']) ? self::$options['exclude_posts'] : '';
        echo '<input type="text" name="html_site_map_generator_options[exclude_posts]" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">' . __('ID записей/страниц через запятую. Исключает указанные элементы из карты сайта.', 'html-site-map-generator') . '</p>';
    }
    
    public static function include_posts_callback() {
        $value = isset(self::$options['include_posts']) ? self::$options['include_posts'] : '';
        echo '<input type="text" name="html_site_map_generator_options[include_posts]" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">' . __('ID записей/страниц через запятую. Показывает только указанные элементы. Имеет приоритет над исключением.', 'html-site-map-generator') . '</p>';
    }
    
    public static function exclude_categories_callback() {
        $value = isset(self::$options['exclude_categories']) ? self::$options['exclude_categories'] : '';
        echo '<input type="text" name="html_site_map_generator_options[exclude_categories]" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">' . __('ID категорий через запятую. Исключает указанные категории из карты сайта.', 'html-site-map-generator') . '</p>';
    }
    
    public static function include_categories_callback() {
        $value = isset(self::$options['include_categories']) ? self::$options['include_categories'] : '';
        echo '<input type="text" name="html_site_map_generator_options[include_categories]" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">' . __('ID категорий через запятую. Показывает только указанные категории. Имеет приоритет над исключением.', 'html-site-map-generator') . '</p>';
    }
    
    public static function orderby_callback() {
        $value = isset(self::$options['orderby']) ? self::$options['orderby'] : 'title';
        $options = array(
            'title' => __('По заголовку', 'html-site-map-generator'),
            'date' => __('По дате', 'html-site-map-generator'),
            'modified' => __('По дате изменения', 'html-site-map-generator'),
            'menu_order' => __('По порядку меню', 'html-site-map-generator'),
            'name' => __('По названию', 'html-site-map-generator')
        );
        
        echo '<select name="html_site_map_generator_options[orderby]">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }
    
    public static function order_callback() {
        $value = isset(self::$options['order']) ? self::$options['order'] : 'ASC';
        echo '<label><input type="radio" name="html_site_map_generator_options[order]" value="ASC" ' . checked($value, 'ASC', false) . '> ' . __('По возрастанию (A-Z)', 'html-site-map-generator') . '</label><br>';
        echo '<label><input type="radio" name="html_site_map_generator_options[order]" value="DESC" ' . checked($value, 'DESC', false) . '> ' . __('По убыванию (Z-A)', 'html-site-map-generator') . '</label>';
    }
    
    public static function posts_per_section_callback() {
        $value = isset(self::$options['posts_per_section']) ? self::$options['posts_per_section'] : '0';
        echo '<input type="number" name="html_site_map_generator_options[posts_per_section]" value="' . esc_attr($value) . '" class="small-text" min="0">';
        echo '<p class="description">' . __('0 - без ограничений', 'html-site-map-generator') . '</p>';
    }
    
    public static function include_post_types_callback() {
        $selected_types = isset(self::$options['include_post_types']) ? (array) self::$options['include_post_types'] : array();
        $post_types = get_post_types(array('public' => true, '_builtin' => false), 'objects');
        
        if (empty($post_types)) {
            echo '<p>' . __('Произвольные типы записей не найдены', 'html-site-map-generator') . '</p>';
            return;
        }
        
        echo '<fieldset>';
        foreach ($post_types as $post_type) {
            $checked = in_array($post_type->name, $selected_types) ? 'checked' : '';
            echo '<label style="display: block; margin-bottom: 8px;">';
            echo '<input type="checkbox" name="html_site_map_generator_options[include_post_types][]" value="' . esc_attr($post_type->name) . '" ' . $checked . '> ';
            echo esc_html($post_type->labels->name . ' (' . $post_type->name . ')');
            echo '</label>';
        }
        echo '<p class="description">' . __('Выберите произвольные типы записей для отображения', 'html-site-map-generator') . '</p>';
        echo '</fieldset>';
    }
    
    public static function include_taxonomies_callback() {
        $selected_taxonomies = isset(self::$options['include_taxonomies']) ? (array) self::$options['include_taxonomies'] : array();
        $taxonomies = get_taxonomies(array('public' => true, '_builtin' => false), 'objects');
        
        if (empty($taxonomies)) {
            echo '<p>' . __('Произвольные таксономии не найдены', 'html-site-map-generator') . '</p>';
            return;
        }
        
        echo '<fieldset>';
        foreach ($taxonomies as $taxonomy) {
            $checked = in_array($taxonomy->name, $selected_taxonomies) ? 'checked' : '';
            echo '<label style="display: block; margin-bottom: 8px;">';
            echo '<input type="checkbox" name="html_site_map_generator_options[include_taxonomies][]" value="' . esc_attr($taxonomy->name) . '" ' . $checked . '> ';
            echo esc_html($taxonomy->labels->name . ' (' . $taxonomy->name . ')');
            echo '</label>';
        }
        echo '<p class="description">' . __('Выберите произвольные таксономии для отображения', 'html-site-map-generator') . '</p>';
        echo '</fieldset>';
    }
    
    // XML настройки
    public static function xml_enabled_callback() {
        $value = isset(self::$options['xml_enabled']) ? self::$options['xml_enabled'] : 'true';
        echo '<label><input type="checkbox" name="html_site_map_generator_options[xml_enabled]" value="true" ' . checked($value, 'true', false) . '> ' . __('Включить XML карту сайта', 'html-site-map-generator') . '</label>';
        echo '<p class="description">' . __('XML карта будет доступна по адресу: ', 'html-site-map-generator') . '<code>' . home_url('/sitemap.xml') . '</code></p>';
    }
    
    public static function xml_include_posts_callback() {
        $value = isset(self::$options['xml_include_posts']) ? self::$options['xml_include_posts'] : 'true';
        echo '<label><input type="checkbox" name="html_site_map_generator_options[xml_include_posts]" value="true" ' . checked($value, 'true', false) . '> ' . __('Включать записи в XML карту', 'html-site-map-generator') . '</label>';
    }
    
    public static function xml_include_pages_callback() {
        $value = isset(self::$options['xml_include_pages']) ? self::$options['xml_include_pages'] : 'true';
        echo '<label><input type="checkbox" name="html_site_map_generator_options[xml_include_pages]" value="true" ' . checked($value, 'true', false) . '> ' . __('Включать страницы в XML карту', 'html-site-map-generator') . '</label>';
    }
    
    public static function xml_include_categories_callback() {
        $value = isset(self::$options['xml_include_categories']) ? self::$options['xml_include_categories'] : 'true';
        echo '<label><input type="checkbox" name="html_site_map_generator_options[xml_include_categories]" value="true" ' . checked($value, 'true', false) . '> ' . __('Включать категории в XML карту', 'html-site-map-generator') . '</label>';
    }
    
    public static function xml_include_custom_post_types_callback() {
        $value = isset(self::$options['xml_include_custom_post_types']) ? self::$options['xml_include_custom_post_types'] : 'true';
        echo '<label><input type="checkbox" name="html_site_map_generator_options[xml_include_custom_post_types]" value="true" ' . checked($value, 'true', false) . '> ' . __('Включать произвольные типы записей в XML карту', 'html-site-map-generator') . '</label>';
    }
    
    public static function xml_change_frequency_callback() {
        $value = isset(self::$options['xml_change_frequency']) ? self::$options['xml_change_frequency'] : 'weekly';
        $options = array(
            'always' => __('Always - всегда', 'html-site-map-generator'),
            'hourly' => __('Hourly - ежечасно', 'html-site-map-generator'),
            'daily' => __('Daily - ежедневно', 'html-site-map-generator'),
            'weekly' => __('Weekly - еженедельно', 'html-site-map-generator'),
            'monthly' => __('Monthly - ежемесячно', 'html-site-map-generator'),
            'yearly' => __('Yearly - ежегодно', 'html-site-map-generator'),
            'never' => __('Never - никогда', 'html-site-map-generator')
        );
        
        echo '<select name="html_site_map_generator_options[xml_change_frequency]">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Частота обновления контента по умолчанию', 'html-site-map-generator') . '</p>';
    }
    
    public static function xml_priority_callback() {
        $value = isset(self::$options['xml_priority']) ? self::$options['xml_priority'] : '0.7';
        $options = array(
            '1.0' => __('1.0 - высший приоритет', 'html-site-map-generator'),
            '0.9' => __('0.9 - очень высокий', 'html-site-map-generator'),
            '0.8' => __('0.8 - высокий', 'html-site-map-generator'),
            '0.7' => __('0.7 - средний', 'html-site-map-generator'),
            '0.6' => __('0.6 - ниже среднего', 'html-site-map-generator'),
            '0.5' => __('0.5 - низкий', 'html-site-map-generator'),
            '0.4' => __('0.4 - очень низкий', 'html-site-map-generator'),
            '0.3' => __('0.3 - минимальный', 'html-site-map-generator'),
            '0.2' => __('0.2 - очень минимальный', 'html-site-map-generator'),
            '0.1' => __('0.1 - наименьший', 'html-site-map-generator'),
            '0.0' => __('0.0 - не индексировать', 'html-site-map-generator')
        );
        
        echo '<select name="html_site_map_generator_options[xml_priority]">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Приоритет индексации по умолчанию', 'html-site-map-generator') . '</p>';
    }
    
    /**
     * Страница настроек
     */
    public static function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Карта сайта - Настройки', 'html-site-map-generator'); ?></h1>
            
            <form action='options.php' method='post'>
                <?php
                settings_fields('html_site_map_generator_settings');
                do_settings_sections('html_site_map_generator_settings');
                submit_button();
                ?>
            </form>
            
            <div class="card">
                <h2><?php echo esc_html__('Использование', 'html-site-map-generator'); ?></h2>
                
                <h3><?php echo esc_html__('HTML карта сайта:', 'html-site-map-generator'); ?></h3>
                <p><?php echo esc_html__('Для вставки HTML карты сайта на страницу используйте шорткод:', 'html-site-map-generator'); ?></p>
                <code>[html_sitemap]</code>
                
                <h3><?php echo esc_html__('XML карта сайта:', 'html-site-map-generator'); ?></h3>
                <p><?php echo esc_html__('XML карта сайта доступна по прямой ссылке для поисковых систем:', 'html-site-map-generator'); ?></p>
                <code><?php echo home_url('/sitemap.xml'); ?></code>
                
                <p><?php echo esc_html__('Добавьте эту ссылку в файл robots.txt и в панели вебмастеров поисковых систем.', 'html-site-map-generator'); ?></p>
                
                <h4><?php echo esc_html__('Пример для robots.txt:', 'html-site-map-generator'); ?></h4>
                <code>Sitemap: <?php echo home_url('/sitemap.xml'); ?></code>
            </div>
        </div>
        <?php
    }
    
    /**
     * Генерация HTML карты сайта
     */
    public static function generate_html_sitemap() {
        // Используем настройки из админки
        $options = self::$options;
        
        // Устанавливаем значения по умолчанию для всех опций
        $defaults = array(
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
            'orderby' => 'title',
            'order' => 'ASC',
            'posts_per_section' => '0',
            'include_post_types' => array(),
            'include_taxonomies' => array()
        );
        
        // Объединяем с настройками из базы данных
        $options = wp_parse_args($options, $defaults);
        
        // Преобразуем строковые значения в boolean
        $show_pages = ($options['show_pages'] === 'true');
        $show_posts = ($options['show_posts'] === 'true');
        $show_categories = ($options['show_categories'] === 'true');
        $show_authors = ($options['show_authors'] === 'true');
        $show_tags = ($options['show_tags'] === 'true');
        $show_date = ($options['show_date'] === 'true');
        $show_comments_count = ($options['show_comments_count'] === 'true');
        
        // Обрабатываем ID
        $exclude_posts = !empty($options['exclude_posts']) ? array_map('intval', explode(',', $options['exclude_posts'])) : array();
        $include_posts = !empty($options['include_posts']) ? array_map('intval', explode(',', $options['include_posts'])) : array();
        $exclude_categories = !empty($options['exclude_categories']) ? array_map('intval', explode(',', $options['exclude_categories'])) : array();
        $include_categories = !empty($options['include_categories']) ? array_map('intval', explode(',', $options['include_categories'])) : array();
        
        ob_start();
        
        echo '<div class="html-sitemap">';
        
        // Страницы
        if ($show_pages) {
            self::display_pages($options, $exclude_posts, $include_posts);
        }
        
        // Записи по категориям
        if ($show_posts) {
            self::display_posts_by_category($options, $exclude_posts, $include_posts, $exclude_categories, $include_categories);
        }
        
        // Категории
        if ($show_categories) {
            self::display_categories($options, $exclude_categories, $include_categories);
        }
        
        // Авторы
        if ($show_authors) {
            self::display_authors();
        }
        
        // Метки
        if ($show_tags) {
            self::display_tags();
        }
        
        // Произвольные типы записей
        self::display_custom_post_types($options, $exclude_posts, $include_posts);
        
        // Произвольные таксономии
        self::display_custom_taxonomies($options);
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Генерация содержимого XML карты
     */
    private static function generate_xml_content() {
        $options = self::$options;
        
        // Устанавливаем значения по умолчанию
        $defaults = array(
            'xml_include_posts' => 'true',
            'xml_include_pages' => 'true',
            'xml_include_categories' => 'true',
            'xml_include_custom_post_types' => 'true',
            'xml_change_frequency' => 'weekly',
            'xml_priority' => '0.7'
        );
        
        $options = wp_parse_args($options, $defaults);
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        
        // Главная страница
        $xml .= self::generate_xml_url(home_url(), $options['xml_change_frequency'], '1.0');
        
        // Страницы
        if ($options['xml_include_pages'] === 'true') {
            $pages = get_pages(array('post_status' => 'publish'));
            foreach ($pages as $page) {
                $xml .= self::generate_xml_url(get_permalink($page->ID), $options['xml_change_frequency'], '0.8');
            }
        }
        
        // Записи
        if ($options['xml_include_posts'] === 'true') {
            $posts = get_posts(array('post_type' => 'post', 'numberposts' => -1, 'post_status' => 'publish'));
            foreach ($posts as $post) {
                $xml .= self::generate_xml_url(get_permalink($post->ID), $options['xml_change_frequency'], $options['xml_priority']);
            }
        }
        
        // Категории
        if ($options['xml_include_categories'] === 'true') {
            $categories = get_categories(array('hide_empty' => true));
            foreach ($categories as $category) {
                $xml .= self::generate_xml_url(get_category_link($category->term_id), 'weekly', '0.6');
            }
        }
        
        // Произвольные типы записей
        if ($options['xml_include_custom_post_types'] === 'true') {
            $post_types = get_post_types(array('public' => true, '_builtin' => false), 'objects');
            foreach ($post_types as $post_type) {
                $posts = get_posts(array('post_type' => $post_type->name, 'numberposts' => -1, 'post_status' => 'publish'));
                foreach ($posts as $post) {
                    $xml .= self::generate_xml_url(get_permalink($post->ID), $options['xml_change_frequency'], '0.5');
                }
            }
        }
        
        $xml .= '</urlset>';
        
        return $xml;
    }
    
    /**
     * Генерация отдельного URL для XML
     */
    private static function generate_xml_url($url, $change_frequency, $priority) {
        $url = esc_url($url);
        
        $xml = '<url>';
        $xml .= '<loc>' . $url . '</loc>';
        $xml .= '<lastmod>' . gmdate('Y-m-d\TH:i:s+00:00') . '</lastmod>';
        $xml .= '<changefreq>' . $change_frequency . '</changefreq>';
        $xml .= '<priority>' . $priority . '</priority>';
        $xml .= '</url>';
        
        return $xml;
    }
    
    /**
     * Отображение страниц
     */
    private static function display_pages($options, $exclude_posts, $include_posts) {
        $orderby = isset($options['orderby']) ? $options['orderby'] : 'title';
        $order = isset($options['order']) ? $options['order'] : 'ASC';
        $show_date = isset($options['show_date']) ? ($options['show_date'] === 'true') : false;
        
        $args = array(
            'sort_column' => $orderby,
            'sort_order' => $order
        );
        
        // Если указаны конкретные ID для включения
        if (!empty($include_posts)) {
            $args['include'] = $include_posts;
        } else if (!empty($exclude_posts)) {
            $args['exclude'] = $exclude_posts;
        }
        
        $pages = get_pages($args);
        
        if ($pages) {
            echo '<div class="sitemap-section pages-section">';
            echo '<h2 class="sitemap-title">' . __('Страницы', 'html-site-map-generator') . '</h2>';
            echo '<ul class="sitemap-list pages-list">';
            
            foreach ($pages as $page) {
                if ($page->post_status === 'publish') {
                    echo '<li class="sitemap-item page-item">';
                    echo '<a href="' . get_permalink($page->ID) . '" class="sitemap-link">';
                    echo apply_filters('the_title', $page->post_title);
                    
                    if ($show_date) {
                        echo ' <span class="post-date">(' . get_the_date('', $page->ID) . ')</span>';
                    }
                    
                    echo '</a>';
                    echo '</li>';
                }
            }
            
            echo '</ul>';
            echo '</div>';
        }
    }
    
    /**
     * Отображение записей по категориям
     */
    private static function display_posts_by_category($options, $exclude_posts, $include_posts, $exclude_categories, $include_categories) {
        $orderby = isset($options['orderby']) ? $options['orderby'] : 'title';
        $order = isset($options['order']) ? $options['order'] : 'ASC';
        $posts_per_section = isset($options['posts_per_section']) ? intval($options['posts_per_section']) : 0;
        $show_date = isset($options['show_date']) ? ($options['show_date'] === 'true') : false;
        $show_comments = isset($options['show_comments_count']) ? ($options['show_comments_count'] === 'true') : false;
        
        $cat_args = array(
            'hide_empty' => true,
            'orderby' => $orderby,
            'order' => $order
        );
        
        // Если указаны конкретные ID категорий для включения
        if (!empty($include_categories)) {
            $cat_args['include'] = $include_categories;
        } else if (!empty($exclude_categories)) {
            $cat_args['exclude'] = $exclude_categories;
        }
        
        $categories = get_categories($cat_args);
        
        if ($categories) {
            echo '<div class="sitemap-section posts-section">';
            echo '<h2 class="sitemap-title">' . __('Записи по категориям', 'html-site-map-generator') . '</h2>';
            
            foreach ($categories as $category) {
                $posts_args = array(
                    'category' => $category->term_id,
                    'post_type' => 'post',
                    'numberposts' => $posts_per_section > 0 ? $posts_per_section : -1,
                    'orderby' => $orderby,
                    'order' => $order,
                    'post_status' => 'publish'
                );
                
                // Если указаны конкретные ID записей для включения
                if (!empty($include_posts)) {
                    $posts_args['include'] = $include_posts;
                } else if (!empty($exclude_posts)) {
                    $posts_args['exclude'] = $exclude_posts;
                }
                
                $posts = get_posts($posts_args);
                
                if ($posts) {
                    echo '<div class="category-group">';
                    echo '<h3 class="category-title">';
                    echo '<a href="' . get_category_link($category->term_id) . '">';
                    echo $category->name;
                    echo '</a>';
                    echo ' <span class="post-count">(' . count($posts) . ')</span>';
                    echo '</h3>';
                    
                    echo '<ul class="sitemap-list posts-list">';
                    foreach ($posts as $post) {
                        echo '<li class="sitemap-item post-item">';
                        echo '<a href="' . get_permalink($post->ID) . '" class="sitemap-link">';
                        echo apply_filters('the_title', $post->post_title);
                        
                        if ($show_date) {
                            echo ' <span class="post-date">(' . get_the_date('', $post->ID) . ')</span>';
                        }
                        
                        if ($show_comments) {
                            $comments_count = get_comments_number($post->ID);
                            echo ' <span class="comments-count">[' . $comments_count . ']</span>';
                        }
                        
                        echo '</a>';
                        echo '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                }
            }
            
            echo '</div>';
        }
    }
    
    /**
     * Отображение категорий
     */
    private static function display_categories($options, $exclude_categories, $include_categories) {
        $orderby = isset($options['orderby']) ? $options['orderby'] : 'title';
        $order = isset($options['order']) ? $options['order'] : 'ASC';
        
        $args = array(
            'hide_empty' => true,
            'orderby' => $orderby,
            'order' => $order
        );
        
        // Если указаны конкретные ID категорий для включения
        if (!empty($include_categories)) {
            $args['include'] = $include_categories;
        } else if (!empty($exclude_categories)) {
            $args['exclude'] = $exclude_categories;
        }
        
        $categories = get_categories($args);
        
        if ($categories) {
            echo '<div class="sitemap-section categories-section">';
            echo '<h2 class="sitemap-title">' . __('Категории', 'html-site-map-generator') . '</h2>';
            echo '<ul class="sitemap-list categories-list">';
            
            foreach ($categories as $category) {
                echo '<li class="sitemap-item category-item">';
                echo '<a href="' . get_category_link($category->term_id) . '" class="sitemap-link">';
                echo $category->name;
                echo ' <span class="post-count">(' . $category->count . ')</span>';
                echo '</a>';
                echo '</li>';
            }
            
            echo '</ul>';
            echo '</div>';
        }
    }
    
    /**
     * Отображение авторов
     */
    private static function display_authors() {
        $authors = get_users(array(
            'orderby' => 'post_count',
            'order' => 'DESC',
            'who' => 'authors'
        ));
        
        if ($authors) {
            echo '<div class="sitemap-section authors-section">';
            echo '<h2 class="sitemap-title">' . __('Авторы', 'html-site-map-generator') . '</h2>';
            echo '<ul class="sitemap-list authors-list">';
            
            foreach ($authors as $author) {
                $post_count = count_user_posts($author->ID);
                if ($post_count > 0) {
                    echo '<li class="sitemap-item author-item">';
                    echo '<a href="' . get_author_posts_url($author->ID) . '" class="sitemap-link">';
                    echo $author->display_name;
                    echo ' <span class="post-count">(' . $post_count . ')</span>';
                    echo '</a>';
                    echo '</li>';
                }
            }
            
            echo '</ul>';
            echo '</div>';
        }
    }
    
    /**
     * Отображение меток
     */
    private static function display_tags() {
        $tags = get_tags(array(
            'hide_empty' => true,
            'orderby' => 'count',
            'order' => 'DESC'
        ));
        
        if ($tags) {
            echo '<div class="sitemap-section tags-section">';
            echo '<h2 class="sitemap-title">' . __('Метки', 'html-site-map-generator') . '</h2>';
            echo '<ul class="sitemap-list tags-list">';
            
            foreach ($tags as $tag) {
                echo '<li class="sitemap-item tag-item">';
                echo '<a href="' . get_tag_link($tag->term_id) . '" class="sitemap-link">';
                echo $tag->name;
                echo ' <span class="post-count">(' . $tag->count . ')</span>';
                echo '</a>';
                echo '</li>';
            }
            
            echo '</ul>';
            echo '</div>';
        }
    }
    
    /**
     * Отображение произвольных типов записей
     */
    private static function display_custom_post_types($options, $exclude_posts, $include_posts) {
        $orderby = isset($options['orderby']) ? $options['orderby'] : 'title';
        $order = isset($options['order']) ? $options['order'] : 'ASC';
        $posts_per_section = isset($options['posts_per_section']) ? intval($options['posts_per_section']) : 0;
        $show_date = isset($options['show_date']) ? ($options['show_date'] === 'true') : false;
        $show_comments = isset($options['show_comments_count']) ? ($options['show_comments_count'] === 'true') : false;
        
        $include_post_types = isset($options['include_post_types']) ? (array) $options['include_post_types'] : array();
        
        $post_types = get_post_types(array(
            'public' => true,
            '_builtin' => false
        ), 'objects');
        
        if ($post_types && !empty($include_post_types)) {
            foreach ($post_types as $post_type) {
                if (in_array($post_type->name, $include_post_types)) {
                    $args = array(
                        'post_type' => $post_type->name,
                        'numberposts' => $posts_per_section > 0 ? $posts_per_section : -1,
                        'orderby' => $orderby,
                        'order' => $order,
                        'post_status' => 'publish'
                    );
                    
                    // Если указаны конкретные ID записей для включения
                    if (!empty($include_posts)) {
                        $args['include'] = $include_posts;
                    } else if (!empty($exclude_posts)) {
                        $args['exclude'] = $exclude_posts;
                    }
                    
                    $posts = get_posts($args);
                    
                    if ($posts) {
                        echo '<div class="sitemap-section custom-post-type-section">';
                        echo '<h2 class="sitemap-title">' . $post_type->labels->name . '</h2>';
                        echo '<ul class="sitemap-list custom-post-type-list">';
                        
                        foreach ($posts as $post) {
                            echo '<li class="sitemap-item custom-post-item">';
                            echo '<a href="' . get_permalink($post->ID) . '" class="sitemap-link">';
                            echo apply_filters('the_title', $post->post_title);
                            
                            if ($show_date) {
                                echo ' <span class="post-date">(' . get_the_date('', $post->ID) . ')</span>';
                            }
                            
                            if ($show_comments) {
                                $comments_count = get_comments_number($post->ID);
                                echo ' <span class="comments-count">[' . $comments_count . ']</span>';
                            }
                            
                            echo '</a>';
                            echo '</li>';
                        }
                        
                        echo '</ul>';
                        echo '</div>';
                    }
                }
            }
        }
    }
    
    /**
     * Отображение произвольных таксономий
     */
    private static function display_custom_taxonomies($options) {
        $orderby = isset($options['orderby']) ? $options['orderby'] : 'title';
        $order = isset($options['order']) ? $options['order'] : 'ASC';
        
        $include_taxonomies = isset($options['include_taxonomies']) ? (array) $options['include_taxonomies'] : array();
        
        $taxonomies = get_taxonomies(array(
            'public' => true,
            '_builtin' => false
        ), 'objects');
        
        if ($taxonomies && !empty($include_taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                if (in_array($taxonomy->name, $include_taxonomies)) {
                    $terms = get_terms(array(
                        'taxonomy' => $taxonomy->name,
                        'hide_empty' => true,
                        'orderby' => $orderby,
                        'order' => $order
                    ));
                    
                    if ($terms && !is_wp_error($terms)) {
                        echo '<div class="sitemap-section custom-taxonomy-section">';
                        echo '<h2 class="sitemap-title">' . $taxonomy->labels->name . '</h2>';
                        echo '<ul class="sitemap-list custom-taxonomy-list">';
                        
                        foreach ($terms as $term) {
                            echo '<li class="sitemap-item custom-taxonomy-item">';
                            echo '<a href="' . get_term_link($term) . '" class="sitemap-link">';
                            echo $term->name;
                            echo ' <span class="post-count">(' . $term->count . ')</span>';
                            echo '</a>';
                            echo '</li>';
                        }
                        
                        echo '</ul>';
                        echo '</div>';
                    }
                }
            }
        }
    }
}