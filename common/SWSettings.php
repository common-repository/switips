<?php

if (!class_exists('Switips')) {
  require_once( 'switips-core.php' );
}

if ( !class_exists('switips_settings') ) {
    class switips_settings {
        private $pluginAlias;
        private $pluginTitle;
        private $pluginAbbrev;

        /**
         * @var switips_common
         */
        private $common;

        public function __construct($pluginAlias, $pluginAbbrev, $pluginTitle, $pluginBaseFile, $common) {
            $this->pluginAlias = $pluginAlias;
            $this->pluginAbbrev = $pluginAbbrev;
            $this->pluginTitle = $pluginTitle;
            $this->pluginBaseFile = $pluginBaseFile;
            $this->common = $common;

            // do hooks and filters
            add_filter('plugin_action_links_' . plugin_basename($pluginBaseFile), array($this, 'settings_plugin_links') );
            add_action('admin_menu', array($this, 'settings_menu'), 59 );
            add_filter("mh_{$pluginAbbrev}_setting_value", array($this, 'get_option'));
            add_filter("mh_{$pluginAbbrev}_all_options", array($this, 'all_options'));
        }

        public function settings_menu() {
            $title = str_replace('WooCommerce ', '', $this->common->getPluginTitle());

            add_submenu_page(
                'woocommerce',
                $title,
                $title,
                'manage_woocommerce',
                $this->pluginAlias,
                array($this, 'settings_page')
            );
        }

        public function settings_plugin_links($links) {
            $action_links = array(
                'settings' => sprintf('<a href="%s">%s</a>', $this->admin_url(), esc_html__('Settings', 'woocommerce')),
            );
    
            return array_merge( $action_links, $links );
        }
        
        public function settings_save() {
            $opts = $this->merge_settings_post();            
            
            if($opts['url_api'] == ''){
              $SwitipsCls = new Switips('empty');            
              $opts['url_api'] = $SwitipsCls->getDefaultDomain();
            }
    
            // treat boolean values
            foreach ( $this->default_settings() as $name => $val ) {
                if ( isset($opts[$name]) && isset($_POST[$name]) && in_array($val, array('yes', 'no'))) {
                    $opts[$name] = ( !empty($_POST[$name]) ? 'yes' : 'no' );
                }
            }
    
            update_option($this->pluginAbbrev . '_settings', $opts);
        }
        
        public function merge_settings_post() {
            $defaultSettings = $this->default_settings();
            $allOptions = $this->all_options();
    
            foreach ( $_POST as $key => $val ) {
                if ( in_array($key, array_keys($defaultSettings)) ) {
                    if ( is_numeric($defaultSettings[$key]) ) {
                        $allOptions[$key] = (int) sanitize_text_field($val);
                    }
                    else if ( is_array($val) ) {
                        $allOptions[$key] = array_map('esc_attr', $val);
                    }
                    else {
                        $allOptions[$key] = sanitize_text_field($val);
                    }
                }
            }
    
            return $allOptions;
        }

        public function save_option($option, $value) {
            $opts = $this->all_options();
            $opts[$option] = $value;
    
            update_option($this->pluginAbbrev . '_settings', $opts);
        }
        
        public function all_options() {
            return array_merge($this->default_settings(),
                               get_option($this->pluginAbbrev . '_settings',
                               array()));
        }
        
        public function get_option($name, $default = null) {
            $options = $this->all_options();
            $value = isset($options[$name]) ? $options[$name] : $default;
    
            return $value;
        }
    
        public function admin_url() {
            return admin_url('admin.php?page=' . $this->pluginAlias);
        }
        
        public function admin_url_current() {
            return $this->tab_url($this->active_tab());
        }
    
        public function tab_url($tab) {
            return $this->admin_url().'&tab='.$tab;
        }

        public function setting_tab($tab, $label) {
            $class = ($tab == $this->active_tab()) ? 'nav-tab-active' : '';
            $tab = '<span class="nav-tab '.$class.'">'.$label.'</span>';
    
            return $tab;
        }
    
        public function active_tab() {
            $tab = basename(sanitize_file_name( isset($_GET['tab']) ? $_GET['tab'] : null ));
    
            if ( preg_match('/^tab-(.*)$/', $tab) ) {
                return $tab;
            }
    
            return 'tab-general.php';
        }
        
        public function admin_tab() {
            return esc_html__(str_replace('.php', '', $this->active_tab()));
        }

        public function settings_page() {
            if ( !current_user_can( 'manage_woocommerce' ) ) {
                exit("invalid permissions");
            }
    
            // Save settings if data has been posted
            if ( ! empty( $_POST ) && check_admin_referer('mh_nonce') ) {
                $btnClick = sanitize_text_field($_POST['save']);

                switch( $btnClick ) {
                    case esc_html__( 'Save', 'switips' ):
                        $this->settings_save();
                    break;
                    case esc_html__( 'Reset all settings' ):
                        update_option($this->pluginAbbrev . '_settings', $this->default_settings());
                    break;
                    default:
                        do_action("mh_{$this->pluginAbbrev}_trigger_save", $btnClick);
                    break;
                }
            }
            $title = esc_html__($this->common->getPluginTitle());

            ?>
            <div class="wrap">
                <h2>
                    <?php echo $title; ?>
                </h2>
                <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
                    <?php foreach ( $this->build_tabs() as $name => $label ): ?>
                        <?php echo $this->setting_tab($name, $label) ?>
                    <?php endforeach; ?>
                </nav>
                <div class="mh-settings-page">
                    <?php $this->render_active_tab() ?>
                </div>
            </div>
            <?php
        }

        public function render_active_tab() {
            $tab = $this->active_tab();

            $settings = array();

            foreach ( $this->get_plugin_settings_definitions() as $name => $conf ) {
                $confTab = 'tab-' . strtolower($conf['tab']) . '.php';

                //if ( $tab == $confTab ) {
                    $settings[$name] = $conf;
                //}
            }
            ?>
            <?php $this->form_header() ?>
            <table class="form-table">
	            <?php foreach ( $settings as $name => $conf ): ?>
	                <?php $this->render_field($settings, $name, $conf) ?>
	            <?php endforeach; ?>
	        </table>
            <?php $this->form_footer() ?>
            <?php
        }

        public function render_field($settings, $name, $conf) {
            switch($conf['type']) {
                case 'text':
                    $size = !empty($conf['size']) ? $conf['size'] : 20;
                    $this->text($name, $conf['label'], $size);
                    break;
                case 'number':
                    $this->number($name, $conf['label'], $conf['min'], $conf['max']);
                    break;
                case 'select':
                    $this->select($name, $conf['label'], $conf['options']);
                    break;
            }
        }

        public function build_tabs() {
            $arrSettings = $this->get_plugin_settings_definitions();
            $tabs = array();

            foreach ( $arrSettings as $conf ) {
                $name = 'tab-' . strtolower($conf['tab']) . '.php';
                $tabs[$name] = $conf['tab'];
            }

            return $tabs;
        }

        public function default_settings() {
            $arrSettings = $this->get_plugin_settings_definitions();
            $arr = array();

            foreach ( $arrSettings as $key => $conf ) {
                $arr[ $key ] = $conf['default'];
            }
    
            return $arr;
        }

        public function get_plugin_settings_definitions() {
            return apply_filters("mh_{$this->pluginAbbrev}_settings", array());
        }
        
        public function form_header() {
            ?>
            <form method="post" id="mainform" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('mh_nonce'); ?>
            <?php
        }
        
        public function form_footer() {
            ?>
                <hr/>
                <input name="save" class="button-primary" type="submit" value="<?php echo esc_html__( 'Save', 'switips' ); ?>" />
                <?php do_action("mh_{$this->pluginAbbrev}_admin_buttons"); ?>
            </form>
            <?php
        }

        private function parse_field_options($options) {
            if ( is_callable($options) ) {
                return call_user_func($options);
            }

            return $options;
        }

        public function text($name, $label, $size = null) {
            $value = $this->get_option($name);
            
            ?>
            <tr valign="top">
            	<th scope="row">
	            	<label><?php echo esc_html__($label); ?>:</label>
	            </th>
	            <td>
		            <input type="text"
		                    value="<?php echo $value; ?>"
		                    name="<?php echo $name; ?>"
		                    class="regular-text" />
		        </td>
	        </tr>
            <?php
        }

        public function number($name, $label, $min, $max) {
            $value = $this->get_option($name);
            
            ?>
            <tr valign="top">
            	<th scope="row">
	            	<label><?php echo esc_html__($label); ?>:</label>
	            </th>
	            <td>
		            <input type="number"
		                    value="<?php echo $value; ?>"
		                    name="<?php echo $name; ?>"
		                    min="<?php echo $min; ?>"
		                    max="<?php echo $max; ?>" />
		        </td>
		    </tr>
            <?php
        }

        public function select($name, $label, $options) {
            $value = $this->get_option($name);
            
            $obj = new Switips('empty');
            $api_url = $obj->getDefaultDomain();
            $available = $obj->getAvailableCurrencies($api_url);

            ?>
            <tr valign="top">
            	<th scope="row">
	            	<label><?php echo esc_html__($label); ?>:</label>
	            </th>
	        	<td>
		            <select name="<?php echo $name ?>">
		                <?php foreach ( (array) $options as $optVal => $optLabel ): ?>
                    
                        <?php if(in_array($optVal, $available)):?>
                    
                    
		                    <option <?php if ( $value == $optVal ): ?>selected<?php endif; ?>
		                            value="<?php echo $optVal ?>"><?php echo $optLabel; ?></option>
                        <?php endif; ?>
		                <?php endforeach; ?>
		            </select>
		        </td>
		    </tr>
            <?php
        }

    }
}