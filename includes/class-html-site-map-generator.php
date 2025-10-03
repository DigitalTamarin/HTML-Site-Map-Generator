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
        
        // Редирект с виртуального sitemap.xml на реальный файл
        add_action('template_redirect', array(__CLASS__, 'handle_sitemap_redirect'));
        
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_styles'));
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_init', array(__CLASS__, 'settings_init'));
        
        // Автоматическое обновление sitemap.xml при изменении контента
        add_action('save_post', array(__CLASS__, 'auto_update_sitemap'));
        add_action('delete_post', array(__CLASS__, 'auto_update_sitemap'));
        add_action('created_category', array(__CLASS__, 'auto_update_sitemap'));
        add_action('delete_category', array(__CLASS__, 'auto_update_sitemap'));
        add_action('edited_category', array(__CLASS__, 'auto_update_sitemap'));
        
        // Загружаем настройки
        self::$options = get_option('html_site_map_generator_options', array());
        
        // Устанавливаем значения по умолчанию если настройки пустые
        if (empty(self::$options)) {
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
            
            update_option('html_site_map_generator_options', $default_options);
            self::$options = $default_options;
        }
    }

    /**
     * Редирект с виртуального sitemap.xml на реальный файл
     */
    public static function handle_sitemap_redirect() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Проверяем запрос sitemap.xml
        if (preg_match('#^/sitemap\.xml$#', $request_uri) || $request_uri === '/sitemap.xml') {
            
            // Проверяем, включена ли XML карта в настройках
            $options = get_option('html_site_map_generator_options', array());
            $xml_enabled = isset($options['xml_enabled']) ? $options['xml_enabled'] : 'true';
            
            if ($xml_enabled === 'true' && file_exists(HTML_SITE_MAP_GENERATOR_SITEMAP_PATH)) {
                // Редирект на реальный файл
                wp_redirect(home_url('/sitemap.xml'), 301);
                exit;
            } else {
                // Если XML отключена или файла нет, возвращаем 404
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                exit;
            }
        }
    }

    /**
     * Создание/обновление файла sitemap.xml
     */
    public static function generate_sitemap_file() {
        $xml_content = self::generate_xml_content();
        
        // Пытаемся записать файл
        $result = file_put_contents(HTML_SITE_MAP_GENERATOR_SITEMAP_PATH, $xml_content);
        
        if ($result === false) {
            error_log('HTML Sitemap: Failed to write sitemap.xml file');
            return false;
        }
        
        // Устанавливаем правильные права
        chmod(HTML_SITE_MAP_GENERATOR_SITEMAP_PATH, 0644);
        
        error_log('HTML Sitemap: sitemap.xml file generated successfully');
        return true;
    }

    /**
     * Автоматическое обновление sitemap.xml
     */
    public static function auto_update_sitemap($post_id = null) {
        // Проверяем, включено ли автообновление
        $options = get_option('html_site_map_generator_options', array());
        $auto_update = isset($options['xml_auto_update']) ? $options['xml_auto_update'] : 'true';
        
        if ($auto_update !== 'true') {
            return;
        }
        
        // Для постов проверяем статус
        if ($post_id) {
            $post = get_post($post_id);
            if ($post && $post->post_status !== 'publish' && $post->post_status !== 'trash') {
                return;
            }
        }
        
        // Обновляем sitemap.xml
        self::generate_sitemap_file();
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
        
        // НОВОЕ ПОЛЕ: Категории для показа записей
        add_settings_field(
            'posts_from_categories',
            __('Показывать записи только из категорий (ID)', 'html-site-map-generator'),
            array(__CLASS__, 'posts_from_categories_callback'),
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
            'xml_auto_update',
            __('Автообновление XML карты', 'html-site-map-generator'),
            array(__CLASS__, 'xml_auto_update_callback'),
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
            'xml_enabled', 'xml_auto_update'
        );
        
        foreach ($checkbox_fields as $field) {
            $input[$field] = isset($input[$field]) ? 'true' : 'false';
        }
        
        // Для массивов
        $array_fields = array('include_post_types', 'include_taxonomies');
        foreach ($array_fields as $field) {
            $input[$field] = isset($input[$field]) ? (array) $input[$field] : array();
        }
        
        // Для текстовых полей с ID
        $id_fields = array('exclude_posts', 'include_posts', 'exclude_categories', 'include_categories', 'posts_from_categories');
        foreach ($id_fields as $field) {
            if (isset($input[$field])) {
                // Удаляем все символы кроме цифр и запятых
                $input[$field] = preg_replace('/[^0-9,]/', '', $input[$field]);
                // Удаляем лишние запятые
                $input[$field] = preg_replace('/,+/', ',', $input[$field]);
                $input[$field] = trim($input[$field], ',');
            }
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
    echo '<p>' . __('Настройки генерации XML карты сайта для поисковых систем.', 'html-site-map-generator') . '</p>';
    echo '<p>' . __('XML карта доступна по адресу:', 'html-site-map-generator') . ' <code>' . home_url('/sitemap.xml') . '</code></p>';
    
    // Показываем статус файла
    if (file_exists(HTML_SITE_MAP_GENERATOR_SITEMAP_PATH)) {
        $file_size = filesize(HTML_SITE_MAP_GENERATOR_SITEMAP_PATH);
        $last_generated = get_option('html_sitemap_last_generated');
        
        echo '<div class="notice notice-success inline"><p>';
        echo __('Файл sitemap.xml существует.', 'html-site-map-generator') . ' ';
        echo __('Размер:', 'html-site-map-generator') . ' ' . size_format($file_size) . '. ';
        
        if ($last_generated) {
            echo __('Обновлен:', 'html-site-map-generator') . ' ' . date_i18n('d.m.Y H:i:s', $last_generated);
        } else {
            // Если время нет в опциях, создаем его сейчас
            $current_time = current_time('timestamp');
            update_option('html_sitemap_last_generated', $current_time);
            echo __('Обновлен:', 'html-site-map-generator') . ' ' . date_i18n('d.m.Y H:i:s', $current_time);
        }
        
        echo '</p></div>';
    } else {
        echo '<div class="notice notice-warning inline"><p>';
        echo __('Файл sitemap.xml не существует. Нажмите "Обновить XML карту" для создания.', 'html-site-map-generator');
        echo '</p></div>';
    }
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
    
    /**
     * Callback для нового поля - категории для показа записей
     */
    public static function posts_from_categories_callback() {
        $value = isset(self::$options['posts_from_categories']) ? self::$options['posts_from_categories'] : '';
        echo '<input type="text" name="html_site_map_generator_options[posts_from_categories]" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">' . __('ID категорий через запятую. Будут показаны записи только из указанных категорий. Имеет приоритет над другими настройками категорий.', 'html-site-map-generator') . '</p>';
        
        // Показываем список категорий для удобства
        $categories = get_categories(array('hide_empty' => false));
        if ($categories) {
            echo '<div style="margin-top: 10px; max-height: 150px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; background: #f9f9f9;">';
            echo '<strong>' . __('Список категорий:', 'html-site-map-generator') . '</strong><br>';
            foreach ($categories as $category) {
                echo '<span style="display: inline-block; margin: 2px 5px; font-size: 12px;">';
                echo esc_html($category->name) . ' (ID: ' . $category->term_id . ')';
                echo '</span> | ';
            }
            echo '</div>';
        }
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
    
    public static function xml_auto_update_callback() {
        $value = isset(self::$options['xml_auto_update']) ? self::$options['xml_auto_update'] : 'true';
        echo '<label><input type="checkbox" name="html_site_map_generator_options[xml_auto_update]" value="true" ' . checked($value, 'true', false) . '> ' . __('Автоматически обновлять XML карту при изменении контента', 'html-site-map-generator') . '</label>';
        echo '<p class="description">' . __('При создании, редактировании или удалении записей, страниц, категорий.', 'html-site-map-generator') . '</p>';
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
        echo '<p class="description">' . __('Частота обновления контента по умолчанию для XML карты', 'html-site-map-generator') . '</p>';
    }
    
    public static function xml_priority_callback() {
        $value = isset(self::$options['xml_priority']) ? self::$options['xml_priority'] : '0.7';
        $options = array(
            '1.0' => '1.0 - ' . __('Высший приоритет', 'html-site-map-generator'),
            '0.9' => '0.9',
            '0.8' => '0.8',
            '0.7' => '0.7 - ' . __('Средний приоритет', 'html-site-map-generator'),
            '0.6' => '0.6',
            '0.5' => '0.5',
            '0.4' => '0.4',
            '0.3' => '0.3',
            '0.2' => '0.2',
            '0.1' => '0.1 - ' . __('Низший приоритет', 'html-site-map-generator'),
            '0.0' => '0.0 - ' . __('Исключить', 'html-site-map-generator')
        );
        
        echo '<select name="html_site_map_generator_options[xml_priority]">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Приоритет по умолчанию для XML карты', 'html-site-map-generator') . '</p>';
    }
    
    /**
     * Страница настроек
     */
    public static function settings_page() {
        // Обработка ручного обновления sitemap.xml
        if (isset($_POST['update_sitemap']) && check_admin_referer('update_sitemap_action', 'update_sitemap_nonce')) {
            $result = self::generate_sitemap_file();
            if ($result) {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('XML карта сайта успешно обновлена!', 'html-site-map-generator') . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Ошибка при обновлении XML карты сайта. Проверьте права на запись файлов.', 'html-site-map-generator') . '</p></div>';
            }
        }
        
        // Обработка удаления sitemap.xml
        if (isset($_POST['delete_sitemap']) && check_admin_referer('delete_sitemap_action', 'delete_sitemap_nonce')) {
            if (file_exists(HTML_SITE_MAP_GENERATOR_SITEMAP_PATH)) {
                $result = unlink(HTML_SITE_MAP_GENERATOR_SITEMAP_PATH);
                if ($result) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('Файл sitemap.xml успешно удален!', 'html-site-map-generator') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . __('Ошибка при удалении файла sitemap.xml.', 'html-site-map-generator') . '</p></div>';
                }
            }
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php
            // Проверяем права пользователя
            if (!current_user_can('manage_options')) {
                wp_die(__('У вас недостаточно прав для доступа к этой странице.', 'html-site-map-generator'));
            }
            ?>
            
            <div class="card">
                <h2><?php _e('Управление XML картой сайта', 'html-site-map-generator'); ?></h2>
                
                <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                    <form method="post" style="margin: 0;">
                        <?php wp_nonce_field('update_sitemap_action', 'update_sitemap_nonce'); ?>
                        <button type="submit" name="update_sitemap" class="button button-primary">
                            <?php _e('Обновить XML карту', 'html-site-map-generator'); ?>
                        </button>
                    </form>
                    
                    <?php if (file_exists(HTML_SITE_MAP_GENERATOR_SITEMAP_PATH)): ?>
                    <form method="post" style="margin: 0;">
                        <?php wp_nonce_field('delete_sitemap_action', 'delete_sitemap_nonce'); ?>
                        <button type="submit" name="delete_sitemap" class="button button-secondary" onclick="return confirm('<?php _e('Вы уверены что хотите удалить файл sitemap.xml?', 'html-site-map-generator'); ?>')">
                            <?php _e('Удалить XML файл', 'html-site-map-generator'); ?>
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <a href="<?php echo home_url('/sitemap.xml'); ?>" target="_blank" class="button">
                        <?php _e('Просмотреть XML', 'html-site-map-generator'); ?>
                    </a>
                </div>
                
                <p><strong><?php _e('Использование:', 'html-site-map-generator'); ?></strong></p>
                <p><?php _e('Для вывода HTML карты сайта используйте шорткод:', 'html-site-map-generator'); ?></p>
                <code>[html_sitemap]</code>
                
                <p><?php _e('XML карта сайта доступна по адресу:', 'html-site-map-generator'); ?></p>
                <code><?php echo home_url('/sitemap.xml'); ?></code>
            </div>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('html_site_map_generator_settings');
                do_settings_sections('html_site_map_generator_settings');
                submit_button(__('Сохранить настройки', 'html-site-map-generator'));
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
 * Генерация HTML карты сайта
 */
public static function generate_html_sitemap($atts = array()) {
    // Получаем настройки
    $options = get_option('html_site_map_generator_options', array());
    
    // Обрабатываем атрибуты шорткода
    $atts = shortcode_atts(array(
        'show_pages' => isset($options['show_pages']) ? $options['show_pages'] : 'true',
        'show_posts' => isset($options['show_posts']) ? $options['show_posts'] : 'true',
        'show_categories' => isset($options['show_categories']) ? $options['show_categories'] : 'true',
        'show_authors' => isset($options['show_authors']) ? $options['show_authors'] : 'false',
        'show_tags' => isset($options['show_tags']) ? $options['show_tags'] : 'false',
        'show_date' => isset($options['show_date']) ? $options['show_date'] : 'false',
        'show_comments_count' => isset($options['show_comments_count']) ? $options['show_comments_count'] : 'false',
        'exclude_posts' => isset($options['exclude_posts']) ? $options['exclude_posts'] : '',
        'include_posts' => isset($options['include_posts']) ? $options['include_posts'] : '',
        'exclude_categories' => isset($options['exclude_categories']) ? $options['exclude_categories'] : '',
        'include_categories' => isset($options['include_categories']) ? $options['include_categories'] : '',
        'posts_from_categories' => isset($options['posts_from_categories']) ? $options['posts_from_categories'] : '',
        'orderby' => isset($options['orderby']) ? $options['orderby'] : 'title',
        'order' => isset($options['order']) ? $options['order'] : 'ASC',
        'posts_per_section' => isset($options['posts_per_section']) ? $options['posts_per_section'] : '0',
        'include_post_types' => isset($options['include_post_types']) ? $options['include_post_types'] : array(),
        'include_taxonomies' => isset($options['include_taxonomies']) ? $options['include_taxonomies'] : array(),
    ), $atts);
    
    // Нормализуем значения
    foreach ($atts as $key => $value) {
        if ($value === 'true') $atts[$key] = 'true';
        if ($value === 'false') $atts[$key] = 'false';
        if ($value === '1') $atts[$key] = 'true';
        if ($value === '0') $atts[$key] = 'false';
    }
    
    // Обрабатываем ID
    $exclude_posts = !empty($atts['exclude_posts']) ? array_map('intval', explode(',', $atts['exclude_posts'])) : array();
    $include_posts = !empty($atts['include_posts']) ? array_map('intval', explode(',', $atts['include_posts'])) : array();
    $exclude_categories = !empty($atts['exclude_categories']) ? array_map('intval', explode(',', $atts['exclude_categories'])) : array();
    $include_categories = !empty($atts['include_categories']) ? array_map('intval', explode(',', $atts['include_categories'])) : array();
    $posts_from_categories = !empty($atts['posts_from_categories']) ? array_map('intval', explode(',', $atts['posts_from_categories'])) : array();
    
    // Получаем информацию о категориях для заголовков
    $categories_info = array();
    if (!empty($posts_from_categories)) {
        foreach ($posts_from_categories as $category_id) {
            $category = get_category($category_id);
            if ($category && !is_wp_error($category)) {
                $categories_info[$category_id] = $category->name;
            }
        }
    }
    
    // Начинаем вывод
    ob_start();
    ?>
    <div class="html-sitemap-container">
        
        <?php if ($atts['show_pages'] === 'true'): ?>
        <div class="sitemap-section sitemap-pages">
            <h2 class="sitemap-section-title"><?php _e('Страницы', 'html-site-map-generator'); ?></h2>
            <ul class="sitemap-list">
                <?php
                $pages_args = array(
                    'post_type' => 'page',
                    'post_status' => 'publish',
                    'numberposts' => -1,
                    'orderby' => $atts['orderby'],
                    'order' => $atts['order'],
                    'exclude' => $exclude_posts
                );
                
                // Если указаны конкретные ID для включения
                if (!empty($include_posts)) {
                    $pages_args['include'] = $include_posts;
                    unset($pages_args['exclude']);
                }
                
                $pages = get_posts($pages_args);
                
                foreach ($pages as $page) {
                    echo '<li class="sitemap-item">';
                    echo '<a href="' . get_permalink($page->ID) . '" class="sitemap-link">' . esc_html($page->post_title) . '</a>';
                    
                    if ($atts['show_date'] === 'true') {
                        echo ' <span class="sitemap-date">(' . get_the_date('', $page->ID) . ')</span>';
                    }
                    
                    if ($atts['show_comments_count'] === 'true') {
                        $comments_count = get_comments_number($page->ID);
                        echo ' <span class="sitemap-comments">(' . $comments_count . ')</span>';
                    }
                    
                    echo '</li>';
                }
                ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php if ($atts['show_posts'] === 'true'): ?>
        <div class="sitemap-section sitemap-posts">
            <?php
            // ОПРЕДЕЛЯЕМ ЗАГОЛОВОК ДЛЯ РАЗДЕЛА ЗАПИСЕЙ
            $posts_title = __('Записи', 'html-site-map-generator');
            
            // Если указаны категории для показа записей и есть только одна категория
            if (!empty($posts_from_categories) && count($posts_from_categories) === 1) {
                $first_category_id = $posts_from_categories[0];
                if (isset($categories_info[$first_category_id])) {
                    $posts_title = $categories_info[$first_category_id];
                }
            }
            // Если указано несколько категорий
            elseif (!empty($posts_from_categories) && count($posts_from_categories) > 1) {
                $category_names = array();
                foreach ($posts_from_categories as $category_id) {
                    if (isset($categories_info[$category_id])) {
                        $category_names[] = $categories_info[$category_id];
                    }
                }
                if (!empty($category_names)) {
                    $posts_title = implode(', ', $category_names);
                }
            }
            ?>
            <h2 class="sitemap-section-title"><?php echo esc_html($posts_title); ?></h2>
            <ul class="sitemap-list">
                <?php
                // ОСНОВНАЯ ЛОГИКА ДЛЯ ЗАПИСЕЙ
                $posts_args = array(
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'numberposts' => $atts['posts_per_section'] > 0 ? intval($atts['posts_per_section']) : -1,
                    'orderby' => $atts['orderby'],
                    'order' => $atts['order'],
                );
                
                // ЕСЛИ УКАЗАНЫ КАТЕГОРИИ ДЛЯ ПОКАЗА ЗАПИСЕЙ - используем их
                if (!empty($posts_from_categories)) {
                    $posts_args['category__in'] = $posts_from_categories;
                }
                // Иначе если указаны конкретные ID записей для включения
                else if (!empty($include_posts)) {
                    $posts_args['include'] = $include_posts;
                }
                // Иначе если указаны ID для исключения
                else if (!empty($exclude_posts)) {
                    $posts_args['exclude'] = $exclude_posts;
                }
                
                $posts = get_posts($posts_args);
                
                foreach ($posts as $post) {
                    // Дополнительная проверка исключений (на случай если category__in не сработал)
                    if (!empty($exclude_posts) && in_array($post->ID, $exclude_posts)) {
                        continue;
                    }
                    
                    echo '<li class="sitemap-item">';
                    echo '<a href="' . get_permalink($post->ID) . '" class="sitemap-link">' . esc_html($post->post_title) . '</a>';
                    
                    if ($atts['show_date'] === 'true') {
                        echo ' <span class="sitemap-date">(' . get_the_date('', $post->ID) . ')</span>';
                    }
                    
                    if ($atts['show_comments_count'] === 'true') {
                        $comments_count = get_comments_number($post->ID);
                        echo ' <span class="sitemap-comments">(' . $comments_count . ')</span>';
                    }
                    
                    echo '</li>';
                }
                ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php if ($atts['show_categories'] === 'true'): ?>
        <div class="sitemap-section sitemap-categories">
            <h2 class="sitemap-section-title"><?php _e('Категории', 'html-site-map-generator'); ?></h2>
            <ul class="sitemap-list">
                <?php
                $categories_args = array(
                    'hide_empty' => true,
                );
                
                // Если указаны категории для показа записей - показываем только их
                if (!empty($posts_from_categories)) {
                    $categories_args['include'] = $posts_from_categories;
                }
                // Иначе если указаны конкретные ID категорий для включения
                else if (!empty($include_categories)) {
                    $categories_args['include'] = $include_categories;
                }
                // Иначе если указаны ID для исключения
                else if (!empty($exclude_categories)) {
                    $categories_args['exclude'] = $exclude_categories;
                }
                
                $categories = get_categories($categories_args);
                
                foreach ($categories as $category) {
                    echo '<li class="sitemap-item">';
                    echo '<a href="' . get_category_link($category->term_id) . '" class="sitemap-link">' . esc_html($category->name) . '</a>';
                    
                    if ($atts['show_comments_count'] === 'true') {
                        echo ' <span class="sitemap-comments">(' . $category->count . ')</span>';
                    }
                    
                    echo '</li>';
                }
                ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php
        // Произвольные типы записей
        if (!empty($atts['include_post_types'])) {
            foreach ($atts['include_post_types'] as $post_type) {
                if (post_type_exists($post_type)) {
                    $post_type_obj = get_post_type_object($post_type);
                    
                    echo '<div class="sitemap-section sitemap-custom-post-type sitemap-' . esc_attr($post_type) . '">';
                    echo '<h2 class="sitemap-section-title">' . esc_html($post_type_obj->labels->name) . '</h2>';
                    echo '<ul class="sitemap-list">';
                    
                    $custom_posts_args = array(
                        'post_type' => $post_type,
                        'post_status' => 'publish',
                        'numberposts' => $atts['posts_per_section'] > 0 ? intval($atts['posts_per_section']) : -1,
                        'orderby' => $atts['orderby'],
                        'order' => $atts['order'],
                    );
                    
                    // Если указаны конкретные ID для включения
                    if (!empty($include_posts)) {
                        $custom_posts_args['include'] = $include_posts;
                    }
                    // Иначе если указаны ID для исключения
                    else if (!empty($exclude_posts)) {
                        $custom_posts_args['exclude'] = $exclude_posts;
                    }
                    
                    $custom_posts = get_posts($custom_posts_args);
                    
                    foreach ($custom_posts as $custom_post) {
                        echo '<li class="sitemap-item">';
                        echo '<a href="' . get_permalink($custom_post->ID) . '" class="sitemap-link">' . esc_html($custom_post->post_title) . '</a>';
                        
                        if ($atts['show_date'] === 'true') {
                            echo ' <span class="sitemap-date">(' . get_the_date('', $custom_post->ID) . ')</span>';
                        }
                        
                        if ($atts['show_comments_count'] === 'true') {
                            $comments_count = get_comments_number($custom_post->ID);
                            echo ' <span class="sitemap-comments">(' . $comments_count . ')</span>';
                        }
                        
                        echo '</li>';
                    }
                    
                    echo '</ul>';
                    echo '</div>';
                }
            }
        }
        ?>
        
    </div>
    <?php
    
    return ob_get_clean();
}

    /**
     * Генерация содержимого для XML карты
     */
    public static function generate_xml_content() {
        // Получаем настройки
        $options = get_option('html_site_map_generator_options', array());
        
        // Используем те же параметры что и для HTML карты
        $show_pages = isset($options['show_pages']) ? $options['show_pages'] : 'true';
        $show_posts = isset($options['show_posts']) ? $options['show_posts'] : 'true';
        $show_categories = isset($options['show_categories']) ? $options['show_categories'] : 'true';
        $exclude_posts = isset($options['exclude_posts']) ? $options['exclude_posts'] : '';
        $include_posts = isset($options['include_posts']) ? $options['include_posts'] : '';
        $exclude_categories = isset($options['exclude_categories']) ? $options['exclude_categories'] : '';
        $include_categories = isset($options['include_categories']) ? $options['include_categories'] : '';
        $posts_from_categories = isset($options['posts_from_categories']) ? $options['posts_from_categories'] : '';
        $include_post_types = isset($options['include_post_types']) ? $options['include_post_types'] : array();
        $include_taxonomies = isset($options['include_taxonomies']) ? $options['include_taxonomies'] : array();
        $xml_change_frequency = isset($options['xml_change_frequency']) ? $options['xml_change_frequency'] : 'weekly';
        $xml_priority = isset($options['xml_priority']) ? $options['xml_priority'] : '0.7';
        
        // Обрабатываем ID
        $exclude_posts_ids = !empty($exclude_posts) ? array_map('intval', explode(',', $exclude_posts)) : array();
        $include_posts_ids = !empty($include_posts) ? array_map('intval', explode(',', $include_posts)) : array();
        $exclude_categories_ids = !empty($exclude_categories) ? array_map('intval', explode(',', $exclude_categories)) : array();
        $include_categories_ids = !empty($include_categories) ? array_map('intval', explode(',', $include_categories)) : array();
        $posts_from_categories_ids = !empty($posts_from_categories) ? array_map('intval', explode(',', $posts_from_categories)) : array();
        
        // Начинаем вывод XML
        $output = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // Главная страница
        $output .= self::generate_xml_url_entry(home_url('/'), '1.0', $xml_change_frequency);
        
        // Страницы
        if ($show_pages === 'true') {
            $pages_args = array(
                'post_type' => 'page',
                'post_status' => 'publish',
                'numberposts' => -1,
                'exclude' => $exclude_posts_ids
            );
            
            if (!empty($include_posts_ids)) {
                $pages_args['include'] = $include_posts_ids;
                unset($pages_args['exclude']);
            }
            
            $pages = get_posts($pages_args);
            
            foreach ($pages as $page) {
                $output .= self::generate_xml_url_entry(
                    get_permalink($page->ID),
                    $xml_priority,
                    $xml_change_frequency,
                    get_the_modified_time('Y-m-d\TH:i:s+00:00', $page->ID)
                );
            }
        }
        
        // Записи (СИНХРОНИЗИРОВАНО С HTML КАРТОЙ)
        if ($show_posts === 'true') {
            $posts_args = array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'numberposts' => -1,
            );
            
            // ЕСЛИ УКАЗАНЫ КАТЕГОРИИ ДЛЯ ПОКАЗА ЗАПИСЕЙ - используем их
            if (!empty($posts_from_categories_ids)) {
                $posts_args['category__in'] = $posts_from_categories_ids;
            }
            // Иначе если указаны конкретные ID записей для включения
            else if (!empty($include_posts_ids)) {
                $posts_args['include'] = $include_posts_ids;
            }
            // Иначе если указаны ID для исключения
            else if (!empty($exclude_posts_ids)) {
                $posts_args['exclude'] = $exclude_posts_ids;
            }
            
            $posts = get_posts($posts_args);
            
            foreach ($posts as $post) {
                // Дополнительная проверка исключений
                if (!empty($exclude_posts_ids) && in_array($post->ID, $exclude_posts_ids)) {
                    continue;
                }
                
                $output .= self::generate_xml_url_entry(
                    get_permalink($post->ID),
                    $xml_priority,
                    $xml_change_frequency,
                    get_the_modified_time('Y-m-d\TH:i:s+00:00', $post->ID)
                );
            }
        }
        
        // Категории (СИНХРОНИЗИРОВАНО С HTML КАРТОЙ)
        if ($show_categories === 'true') {
            $categories_args = array(
                'hide_empty' => true,
            );
            
            // Если указаны категории для показа записей - показываем только их
            if (!empty($posts_from_categories_ids)) {
                $categories_args['include'] = $posts_from_categories_ids;
            }
            // Иначе если указаны конкретные ID категорий для включения
            else if (!empty($include_categories_ids)) {
                $categories_args['include'] = $include_categories_ids;
            }
            // Иначе если указаны ID для исключения
            else if (!empty($exclude_categories_ids)) {
                $categories_args['exclude'] = $exclude_categories_ids;
            }
            
            $categories = get_categories($categories_args);
            
            foreach ($categories as $category) {
                $output .= self::generate_xml_url_entry(
                    get_category_link($category->term_id),
                    '0.6', // Более низкий приоритет для категорий
                    'weekly'
                );
            }
        }
        
        // Произвольные типы записей
        if (!empty($include_post_types)) {
            foreach ($include_post_types as $post_type) {
                if (post_type_exists($post_type)) {
                    $custom_posts_args = array(
                        'post_type' => $post_type,
                        'post_status' => 'publish',
                        'numberposts' => -1,
                    );
                    
                    // Если указаны конкретные ID для включения
                    if (!empty($include_posts_ids)) {
                        $custom_posts_args['include'] = $include_posts_ids;
                    }
                    // Иначе если указаны ID для исключения
                    else if (!empty($exclude_posts_ids)) {
                        $custom_posts_args['exclude'] = $exclude_posts_ids;
                    }
                    
                    $custom_posts = get_posts($custom_posts_args);
                    
                    foreach ($custom_posts as $custom_post) {
                        $output .= self::generate_xml_url_entry(
                            get_permalink($custom_post->ID),
                            $xml_priority,
                            $xml_change_frequency,
                            get_the_modified_time('Y-m-d\TH:i:s+00:00', $custom_post->ID)
                        );
                    }
                }
            }
        }
        
        $output .= '</urlset>';
        
        return $output;
    }
    
    /**
     * Генерация отдельной записи URL для XML карты
     */
    private static function generate_xml_url_entry($url, $priority, $change_frequency, $lastmod = null) {
        if (!$lastmod) {
            $lastmod = date('Y-m-d\TH:i:s+00:00');
        }
        
        $entry = "  <url>\n";
        $entry .= "    <loc>" . esc_url($url) . "</loc>\n";
        $entry .= "    <lastmod>" . $lastmod . "</lastmod>\n";
        $entry .= "    <changefreq>" . $change_frequency . "</changefreq>\n";
        $entry .= "    <priority>" . $priority . "</priority>\n";
        $entry .= "  </url>\n";
        
        return $entry;
    }
}