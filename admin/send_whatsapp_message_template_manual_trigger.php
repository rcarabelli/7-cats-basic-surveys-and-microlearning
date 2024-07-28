<?php
// /home/<account>/public_html/wp-content/plugins/7-ktz-basic-surveys-and-microlearning/admin/send_message_template_manual.trigger.php 

// Llamar al archivo config.php
require_once __DIR__ . '/../config.php';

// Ahora puedes usar las constantes
require_once SUPPORT_FUNCTIONS_FILE;

// Agregar solicitudes AJAX
require_once plugin_dir_path(__FILE__) . '../includes/process_ajax_request.php';


if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_due_templates'])) {
    // Llamada a la función para enviar los templates pendientes
    message_template_send_template_with_all_parameters();
}



// Función para enviar los message templates pendientes de la tabla  7c_send_message_template_logs 
function message_template_send_template_with_all_parameters() {
    // 1. Recuperar el array con todos los valores
    $data = message_template_find_final_data_from_user_conversation_to_send();
    
    // Verificar que se haya recuperado la data correctamente y que contenga la clave 'template_log_id'
    if ($data['status'] !== 'success' || !isset($data['data']['template_log_id'])) {
        error_log('Error al obtener data para enviar el message template: ' . print_r($data, true));
        return "Error al obtener data para enviar el message template: " . print_r($data, true);
    }

    // 2. Disparar function send_whatsapp_template_message($data)
    $send_response = send_whatsapp_template_message($data['data']);
    error_log('Inicio de send_whatsapp_template_message');

    // Verificar si el envío fue exitoso
    if ($send_response['status'] !== "sent") {
        error_log('Error al enviar el message template: ' . print_r($send_response['rawResponse'], true));
        return 'Error al enviar el message template: ' . print_r($send_response['rawResponse'], true);
    }

    // 3. Si tiene éxito disparar esto para marcar como enviada la línea de la tabla de pendientes
    update_template_status_in_db($data['data']['template_log_id'], 'sent');

    // 4. Registrar un log al servidor con todo lo realizado
    error_log('Message template enviado exitosamente.');
    return 'Message template enviado exitosamente.';
}






function send_whatsapp_message_template_manual_trigger_page() {
    ?>
    <div class="wrap"> <!-- Clase wrap para darle estilos básicos de WP -->
        <h1 class="wp-heading-inline">Module to send messages one by one</h1>
        
        <!-- Botón para enviar templates pendientes -->
        <div style="margin-top: 20px;">
            <form method="post" action="">
                <button type="submit" name="send_due_templates" class="button button-secondary">Enviar Templates Pendientes</button>
            </form>
        </div>
    </div>
    <?php
}


