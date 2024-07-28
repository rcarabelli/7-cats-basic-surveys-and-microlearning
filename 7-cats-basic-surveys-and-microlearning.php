<?php
/**
 * Plugin Name: 7 Cats Basic Surveys and Microlearning
 * Description: A plugin to manage basic surveys and microlearning.
 * Version: 1.0
 * Author: Renato Carabelli
 * License: GPL2
 */

// Incluir otros archivos del plugin
include(plugin_dir_path(__FILE__) . 'admin/menu.php');
include(plugin_dir_path(__FILE__) . 'admin/handle-form-submission.php');
include(plugin_dir_path(__FILE__) . 'admin/create_and_manage_survey.php');
include(plugin_dir_path(__FILE__) . 'admin/add_questions_to_survey.php');
include(plugin_dir_path(__FILE__) . 'admin/handle_survey_sending_to_user.php');
include(plugin_dir_path(__FILE__) . 'admin/add_answers_to_survey_question.php');
include(plugin_dir_path(__FILE__) . 'admin/main_whatsapp_functions.php');
include(plugin_dir_path(__FILE__) . 'admin/send_whatsapp_message_templates.php');
include(plugin_dir_path(__FILE__) . 'admin/manage_whatsapp_message_templates.php');
include(plugin_dir_path(__FILE__) . 'admin/send_whatsapp_message_template_choose_template.php');
include(plugin_dir_path(__FILE__) . 'admin/send_whatsapp_message_template_manual_trigger.php');

// Más código aquí

?>