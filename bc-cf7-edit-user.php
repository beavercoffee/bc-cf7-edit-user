<?php
/*
Author: Beaver Coffee
Author URI: https://beaver.coffee
Description: Edit Users with Contact Form 7.
Domain Path:
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Network: true
Plugin Name: BC CF7 Edit User
Plugin URI: https://github.com/beavercoffee/bc-cf7-edit-user
Requires at least: 5.7
Requires PHP: 5.6
Text Domain: bc-cf7-edit-user
Version: 1.7.12.1
*/

if(defined('ABSPATH')){
    require_once(plugin_dir_path(__FILE__) . 'classes/class-bc-cf7-edit-user.php');
    BC_CF7_Edit_User::get_instance(__FILE__);
}
