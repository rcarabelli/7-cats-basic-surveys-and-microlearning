<?php

add_action('wp_ajax_choose_template', function() {
    global $wpdb;
    
    // Verificar si el templateId se ha enviado
    if(!isset($_POST['templateId'])) {
        wp_send_json_error(['message' => 'Template ID is missing']);
    }

    $templateId = intval($_POST['templateId']); // Obtén el ID del template
    $selectedUsers = isset($_POST['selectedUsers']) ? $_POST['selectedUsers'] : [];

    // Obtén el template de la base de datos
    $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM 7c_whatsapp_template_configurations WHERE id = %d", $templateId), ARRAY_A);
    
    // Añadir registro de depuración aquí
    error_log(print_r($template, true));

    if(!$template) {
        wp_send_json_error(['message' => 'Template not found']);
    }
    
    error_log(print_r($_POST, true));
    
    // Puedes enviar más datos si lo necesitas
    wp_send_json_success(['template' => $template, 'selectedUsers' => $selectedUsers]); 
});

// Handler para la página /home/<account>/public_html/wp-content/plugins/7-ktz-basic-surveys-and-microlearning/admin/send_whatsapp_message_template_manual_trigger.php
add_action('wp_ajax_send_due_templates', function() {
    $responseMessage = message_template_send_template_with_all_parameters();
    wp_send_json_success(['message' => $responseMessage]);
});

// Mensaje Popup para envio exitoso
add_action('wp_ajax_send_due_templates', function() {
    $responseMessage = message_template_send_template_with_all_parameters();
    wp_send_json_success(['message' => "First message template in database sent"]);
});






//**************** Funciones para el formulario en Java/AJAX ******************//

// function handle_template_choice() {
//     global $wpdb;
//
//     $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : '';
//     $selected_users = isset($_POST['selected_users']) ? $_POST['selected_users'] : [];
//
//     // Obtener el template completo de la base de datos
//     $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM 7c_whatsapp_template_configurations WHERE id = %d", $template_id), ARRAY_A);
//
//     // Verificar si el template existe
//     if(!$template) {
//         wp_send_json_error(['message' => 'Template not found']);
//     }
//
//     // Preparar la respuesta
//     $response = [
//         'template' => $template,
//         'selected_users' => $selected_users
//     ];
//
//     wp_send_json_success($response);
// }
//
// add_action('wp_ajax_handle_template_choice', 'handle_template_choice'); // Si el usuario está logueado
// add_action('wp_ajax_nopriv_handle_template_choice', 'handle_template_choice'); // Si el usuario no está logueado (opcional)
