<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * CSP_Widget
 * Simple widget to render the "Ajouter vos mensurations" button and popup
 */
class CSP_Widget extends WP_Widget
{

    public function __construct()
    {
        parent::__construct(
            'csp_widget',
            __('CSP - Ajouter mensurations', 'custom-size-plugin'),
            array('description' => __('Affiche un bouton qui ouvre le popup de mensurations.', 'custom-size-plugin'))
        );
    }

    public function widget($args, $instance)
    {
        echo $args['before_widget'];
        $title = ! empty($instance['title']) ? $instance['title'] : __('Ajouter vos mensurations', 'custom-size-plugin');
        if (! empty($title)) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }
        echo '<button class="csp-open-modal button">' . esc_html($title) . '</button>';
        CSP_Popup_Render::render_popup_template();
        echo $args['after_widget'];
    }

    public function form($instance)
    {
        $title = isset($instance['title']) ? $instance['title'] : __('Ajouter vos mensurations', 'custom-size-plugin');
?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Titre:', 'custom-size-plugin'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text"
                value="<?php echo esc_attr($title); ?>" />
        </p>
<?php
    }
}
