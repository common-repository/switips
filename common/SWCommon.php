<?php
if ( !class_exists('switips_common') ) {
    class switips_common {

        private $pluginAbbrev;
        private $pluginAlias;
        private $pluginTitle;
        private $pluginBaseFile;

        private function __construct($pluginAlias,
                                     $pluginTitle,
                                     $pluginBaseFile,
                                     $pluginAbbrev) {

            $this->pluginAlias = $pluginAlias;
            $this->pluginTitle = $pluginTitle;
            $this->pluginBaseFile = $pluginBaseFile;
            $this->pluginAbbrev = $pluginAbbrev;
        }

        public function getPluginAlias() {
            return $this->pluginAlias;
        }

        public function getPluginAbbrev() {
            return $this->pluginAbbrev;
        }

        public function getPluginBaseFile() {
            return $this->pluginBaseFile;
        }

        public function getPluginTitle() {
            return $this->pluginTitle;
        }

        public static function initialize($pluginAlias,
                                            $pluginAbbrev,
                                            $pluginBaseFile,
                                            $pluginTitle) {

            $common = new self($pluginAlias, $pluginTitle, $pluginBaseFile, $pluginAbbrev);

            include_once( dirname(__FILE__) . '/SWSettings.php' );
            $settings = new switips_settings($pluginAlias, $pluginAbbrev, $pluginTitle, $pluginBaseFile, $common);

        }
    }
}