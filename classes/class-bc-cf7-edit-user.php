<?php

if(!class_exists('BC_CF7_Edit_User')){
    final class BC_CF7_Edit_User {

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// private static
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private static $instance = null;

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public static
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public static function get_instance($file = ''){
            if(null !== self::$instance){
                return self::$instance;
            }
            if('' === $file){
                wp_die(__('File doesn&#8217;t exist?'));
            }
            if(!is_file($file)){
                wp_die(sprintf(__('File &#8220;%s&#8221; doesn&#8217;t exist?'), $file));
            }
            self::$instance = new self($file);
            return self::$instance;
    	}

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// private
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private $additional_data = [], $file = '', $user_id = 0;

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __clone(){}

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __construct($file = ''){
            $this->file = $file;
            add_action('plugins_loaded', [$this, 'plugins_loaded']);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private function get_user_id($contact_form = null, $submission = null){
            if(null === $contact_form){
                return new WP_Error('bc_error', __('The requested contact form was not found.', 'contact-form-7'));
            }
            $type = $contact_form->pref('bc_type');
            if(null === $type){
                return new WP_Error('bc_error', sprintf(__('Missing parameter(s): %s'), 'bc_type') . '.');
            }
            if($type !== 'edit-user'){
                return new WP_Error('bc_error', sprintf(__('%1$s is not of type %2$s.'), $type, 'edit-user'));
            }
            $missing = [];
            if(null === $submission){
                $nonce = null;
                $user_id = $contact_form->shortcode_attr('bc_user_id');
            } else {
                $nonce = $submission->get_posted_data('bc_nonce');
                if(null === $nonce){
                    $missing[] = 'bc_nonce';
                }
                $user_id = $submission->get_posted_data('bc_user_id');
            }
            if(null === $user_id){
                $missing[] = 'bc_user_id';
            }
            if($missing){
                return new WP_Error('bc_error', sprintf(__('Missing parameter(s): %s'), implode(', ', $missing)) . '.');
            }
            if(null !== $nonce and !wp_verify_nonce($nonce, 'bc-edit-user_' . $user_id)){
                $message = __('The link you followed has expired.');
                $message .=  ' ' . bc_last_p(__('An error has occurred. Please reload the page and try again.'));
                return new WP_Error('bc_error', $message);
            }
            $user_id = $this->sanitize_user_id($user_id);
            if(0 === $user_id){
                return new WP_Error('bc_error', __('Invalid user ID.'));
            }
            if(!current_user_can('edit_user', $user_id)){
                $message = __('Sorry, you are not allowed to edit this user.');
                $message .=  ' ' . __('You need a higher level of permission.');
                return new WP_Error('bc_error', $message);
			}
            return $user_id;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function output($output, $user_id, $attr, $content, $tag){
			$html = bc_str_get_html($output);
			$wp_nonce = $html->find('[name="_wpnonce"]', 0);
			$original_wp_nonce = $wp_nonce->value;
			$bc_nonce = $html->find('[name="bc_nonce"]', 0);
			$original_bc_nonce = $bc_nonce->value;
            $current_user_id = get_current_user_id();
            wp_set_current_user($user_id);
            $output = wpcf7_contact_form_tag_func($attr, $content, $tag);
            wp_set_current_user($current_user_id);
			$html = bc_str_get_html($output);
			$wp_nonce = $html->find('[name="_wpnonce"]', 0);
			$wp_nonce->value = $original_wp_nonce;
			$bc_nonce = $html->find('[name="bc_nonce"]', 0);
			$bc_nonce->value = $original_bc_nonce;
			$output = $html->save();
			return $output;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function sanitize_user_id($user_id){
            $user = false;
            if(is_numeric($user_id)){
                $user = get_userdata($user_id);
            } else {
                if('current' === $user_id){
                    if(is_user_logged_in()){
                        $user = wp_get_current_user();
                    }
                }
            }
            if(!$user){
                return 0;
            }
            return $user->ID;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function bc_cf7_free_text_value($value, $tag){
            if('' !== $value){
                return $value;
            }
            $contact_form = wpcf7_get_current_contact_form();
            if('edit-user' !== bc_cf7_type($contact_form)){
                return $value;
            }
            $user_id = $this->get_user_id($contact_form);
            if(is_wp_error($user_id)){
                return $value;
            }
            return get_user_meta($user_id, $tag->name . '_free_text', true);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function do_shortcode_tag($output, $tag, $attr, $m){
			if('contact-form-7' !== $tag){
                return $output;
            }
            $contact_form = wpcf7_get_current_contact_form();
            if('edit-user' !== bc_cf7_type($contact_form)){
                return $output;
            }
            $user_id = $this->get_user_id($contact_form);
            if(is_wp_error($user_id)){
                return '<div class="alert alert-danger" role="alert">' . $user_id->get_error_message() . '</div>';
            }
            $content = isset($m[5]) ? $m[5] : null;
            $output = $this->output($output, $user_id, $attr, $content, $tag);
            return $output;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function plugins_loaded(){
            if(!defined('BC_FUNCTIONS')){
        		return;
        	}
            if(!defined('WPCF7_VERSION')){
        		return;
        	}
            add_action('wpcf7_before_send_mail', [$this, 'wpcf7_before_send_mail'], 10, 3);
            add_filter('bc_cf7_free_text_value', [$this, 'bc_cf7_free_text_value'], 10, 2);
            add_filter('do_shortcode_tag', [$this, 'do_shortcode_tag'], 10, 4);
            add_filter('shortcode_atts_wpcf7', [$this, 'shortcode_atts_wpcf7'], 10, 3);
            add_filter('wpcf7_feedback_response', [$this, 'wpcf7_feedback_response'], 15, 2);
            add_filter('wpcf7_form_hidden_fields', [$this, 'wpcf7_form_hidden_fields'], 15);
            add_filter('wpcf7_posted_data', [$this, 'wpcf7_posted_data']);
            add_filter('wpcf7_posted_data_checkbox', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_checkbox*', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_radio', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_radio*', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_select', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_select*', [$this, 'wpcf7_posted_data_type'], 10, 3);
            if(!has_filter('wpcf7_verify_nonce', 'is_user_logged_in')){
                add_filter('wpcf7_verify_nonce', 'is_user_logged_in');
            }
            bc_build_update_checker('https://github.com/beavercoffee/bc-cf7-edit-user', $this->file, 'bc-cf7-edit-user');
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function shortcode_atts_wpcf7($out, $pairs, $atts){
            if(isset($atts['bc_user_id'])){
                $out['bc_user_id'] = $atts['bc_user_id'];
            }
            return $out;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_before_send_mail($contact_form, &$abort, $submission){
            if('edit-user' !== bc_cf7_type($contact_form)){
                return;
            }
            if(!$submission->is('init')){
                return; // prevent conflicts with other plugins
            }
            $abort = true; // prevent mail_sent and mail_failed actions
            $user_id = $this->get_user_id($contact_form, $submission);
            if(is_wp_error($user_id)){
                $submission->set_response($user_id->get_error_message());
                $submission->set_status('aborted'); // try to prevent conflicts with other plugins
                return;
            }
            $this->user_id = $user_id;
            $response = __('User updated.');
            if(bc_cf7_skip_mail($contact_form)){
                $submission->set_response($response);
                $submission->set_status('mail_sent');
            } else {
                if(bc_cf7_mail($contact_form)){
                    $submission->set_response($response . ' ' . $contact_form->message('mail_sent_ok'));
                    $submission->set_status('mail_sent');
                } else {
                    $submission->set_response($response . ' ' . $contact_form->message('mail_sent_ng'));
                    $submission->set_status('mail_failed');
                }
            }
            bc_cf7_update_meta_data(bc_cf7_meta_data($contact_form, $submission), $user_id, 'user');
            bc_cf7_update_posted_data($submission->get_posted_data(), $user_id, 'user');
            bc_cf7_update_uploaded_files($submission->uploaded_files(), $user_id, 'user');
            do_action('bc_cf7_edit_user', $user_id, $contact_form, $submission);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_feedback_response($response, $result){
            if(0 !== $this->user_id){
                if(isset($response['bc_uniqid']) and '' !== $response['bc_uniqid']){
                    $uniqid = get_user_meta($this->user_id, 'bc_uniqid', true);
                    if('' !== $uniqid){
                        $response['bc_uniqid'] = $uniqid;
                    }
                }
            }
            return $response;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_form_hidden_fields($hidden_fields){
            $contact_form = wpcf7_get_current_contact_form();
            if('edit-user' !== bc_cf7_type($contact_form)){
                return $hidden_fields;
            }
            $user_id = $this->get_user_id($contact_form);
            if(is_wp_error($user_id)){
                return $hidden_fields;
            }
            $hidden_fields['bc_user_id'] = $user_id;
            $hidden_fields['bc_nonce'] = wp_create_nonce('bc-edit-user_' . $user_id);
            if(isset($hidden_fields['bc_uniqid'])){
                $uniqid = get_user_meta($user_id, 'bc_uniqid', true);
                if('' !== $uniqid){
                    $hidden_fields['bc_uniqid'] = $uniqid;
                }
            }
            return $hidden_fields;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_posted_data($posted_data){
            if($this->additional_data){
                $posted_data = array_merge($posted_data, $this->additional_data);
            }
            return $posted_data;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_posted_data_type($value, $value_orig, $tag){
			$name = $tag->name;
            $pipes = $tag->pipes;
            $type = $tag->type;
			if(wpcf7_form_tag_supports($type, 'selectable-values')){
                $value = (array) $value;
                $value_orig = (array) $value_orig;
				if($tag->has_option('free_text')){
        			$last_val = array_pop($value);
					list($tied_item) = array_slice(WPCF7_USE_PIPE ? $tag->pipes->collect_afters() : $tag->values, -1, 1);
					$tied_item = html_entity_decode($tied_item, ENT_QUOTES, 'UTF-8');
					if(strpos($last_val, $tied_item) === 0){
						$value[] = $tied_item;
						$this->additional_data[$name . '_free_text'] = trim(str_replace($tied_item, '', $last_val));
					} else {
						$value[] = $last_val;
						$this->additional_data[$name . '_free_text'] = '';
					}
                }
            }
			if(WPCF7_USE_PIPE and $pipes instanceof WPCF7_Pipes and !$pipes->zero()){
				$this->additional_data[$name . '_value'] = $value;
				$value = $value_orig;
            }
            return $value;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    }
}
