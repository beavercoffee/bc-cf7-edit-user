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

        private $file = '', $response_message = '', $user_id = 0;

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

    	private function get_type($contact_form = null){
            if(null === $contact_form){
                $contact_form = wpcf7_get_current_contact_form();
            }
            if(null === $contact_form){
                return '';
            }
            $type = $contact_form->pref('bc_type');
            if(null === $type){
                return '';
            }
            return $type;
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

    	private function upload_file($tmp_name = '', $post_id = 0){
            $file = bc_move_uploaded_file($tmp_name);
            if(is_wp_error($file)){
                return $file;
            }
            return bc_upload($file, $post_id);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function do_shortcode_tag($output, $tag, $attr, $m){
			if('contact-form-7' !== $tag){
                return $output;
            }
            $contact_form = wpcf7_get_current_contact_form();
            if('edit-user' !== $this->get_type($contact_form)){
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
            add_filter('do_shortcode_tag', [$this, 'do_shortcode_tag'], 10, 4);
            add_filter('shortcode_atts_wpcf7', [$this, 'shortcode_atts_wpcf7'], 10, 3);
            add_action('wpcf7_before_send_mail', [$this, 'wpcf7_before_send_mail'], 10, 3);
            add_filter('wpcf7_feedback_response', [$this, 'wpcf7_feedback_response'], 15, 2);
            add_filter('wpcf7_form_hidden_fields', [$this, 'wpcf7_form_hidden_fields'], 15);
            if(!has_filter('wpcf7_verify_nonce', 'is_user_logged_in')){
                add_filter('wpcf7_verify_nonce', 'is_user_logged_in');
            }
            add_action('wpcf7_mail_failed', [$this, 'wpcf7_mail_failed']);
			add_action('wpcf7_mail_sent', [$this, 'wpcf7_mail_sent']);
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
            if('edit-user' !== $this->get_type($contact_form)){
                return;
            }
            if(!$submission->is('init')){
                return;
            }
            $user_id = $this->get_user_id($contact_form, $submission);
            if(is_wp_error($user_id)){
                $abort = true;
                $submission->set_response($user_id->get_error_message());
                $submission->set_status('aborted');
            }
            $this->user_id = $user_id;
			$posted_data = $submission->get_posted_data();
            if($posted_data){
				foreach($posted_data as $key => $value){
					if(is_array($value)){
						delete_user_meta($user_id, $key);
						foreach($value as $single){
							add_user_meta($user_id, $key, $single);
						}
					} else {
						update_user_meta($user_id, $key, $value);
					}
				}
			}
            $error = new WP_Error;
            $uploaded_files = $submission->uploaded_files();
            if($uploaded_files){
                foreach($uploaded_files as $key => $value){
                    delete_user_meta($user_id, $key . '_id');
                    delete_user_meta($user_id, $key . '_filename');
                    foreach((array) $value as $single){
                        $attachment_id = $this->upload_file($single);
                        if(is_wp_error($attachment_id)){
                            $error->merge_from($attachment_id);
                        } else {
                            add_user_meta($user_id, $key . '_id', $attachment_id);
                            add_user_meta($user_id, $key . '_filename', wp_basename($single));
                        }
                    }
                }
            }
            do_action('bc_cf7_edit_user', $user_id, $contact_form, $error);
            if($error->has_errors()){
                $abort = true;
                $message = $error->get_error_message();
                $message .=  ' ' . bc_last_p(__('Application passwords are not available for your account. Please contact the site administrator for assistance.'));
                $submission->set_response($message);
                $submission->set_status('aborted');
                return;
            }
            $this->response_message = __('User updated.');
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_feedback_response($response, $result){
            if(isset($response['bc_uniqid']) and '' !== $response['bc_uniqid']){
                if(0 !== $this->user_id){
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
            if('edit-user' !== $this->get_type($contact_form)){
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

        public function wpcf7_mail_failed($contact_form){
            if('edit-user' !== $this->get_type($contact_form)){
                return;
            }
            $submission = WPCF7_Submission::get_instance();
            if(null === $submission){
                return;
            }
            if(!$submission->is('mail_failed')){
                return;
            }
            if('' !== $this->response_message){
                if($contact_form->message('mail_sent_ng') === $submission->get_response()){
                    $submission->set_response($this->response_message);
                }
            }
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_mail_sent($contact_form){
            if('edit-user' !== $this->get_type($contact_form)){
                return;
            }
            $submission = WPCF7_Submission::get_instance();
            if(null === $submission){
                return;
            }
            if(!$submission->is('mail_sent')){
                return;
            }
            if('' !== $this->response_message){
                if($contact_form->message('mail_sent_ok') === $submission->get_response()){
                    $submission->set_response($this->response_message);
                }
            }
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    }
}
