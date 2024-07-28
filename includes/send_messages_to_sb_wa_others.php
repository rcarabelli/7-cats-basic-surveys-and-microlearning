<?php
// Llamar al archivo config.php
require_once __DIR__ . '/../config.php';

function send_messages($message, $conversation_id, $webhookPayload = null) {
    $GLOBALS['SB_FORCE_ADMIN'] = true;  // Elevar privilegios

    // Si webhookPayload no es null, incluirlo en la solicitud a la API de WhatsApp
    $response_wa = sb_messaging_platforms_send_message($message, $conversation_id, $webhookPayload);
    $response_sb = sb_send_message(2, $conversation_id, $message);  // sender_id es 1 (superadministrador)

    // Depuración
    // error_log("Response from WhatsApp: " . print_r($response_wa, true));
    // error_log("Response from Support Board: " . print_r($response_sb, true));
    if ($webhookPayload !== null) {
        // error_log("Webhook Payload: " . print_r($webhookPayload, true));
    }

    $GLOBALS['SB_FORCE_ADMIN'] = false;  // Reducir privilegios

    if ($response_wa && $response_sb) {
        return true;
    } else {
        return false;
    }
}

// Función genérica para enviar mensajes
function send_generic_message($message_text, $conversation_id, $message_type = 'text') {
    $GLOBALS['SB_FORCE_ADMIN'] = true;  // Elevar privilegios

    // Aquí puedes agregar lógica para manejar diferentes tipos de mensajes
    // basados en $message_type.
    // Por ahora, solo manejamos mensajes de texto simples.
    if ($message_type === 'text') {
        $result = send_messages($message_text, $conversation_id);  // Usamos la función original send_messages
        if (!$result) {
            error_log('Error: Falló send_messages en send_generic_message.');
        }
    } else {
        error_log('Error: Tipo de mensaje no soportado: ' . $message_type);
    }

    // Si se añaden más tipos de mensajes en el futuro, este es el lugar
    // para manejarlos.
    
    $GLOBALS['SB_FORCE_ADMIN'] = false;  // Reducir privilegios

    return $result ?? false;  // Si llegamos aquí, el mensaje no se pudo enviar.
}


/**
 * Función: send_whatsapp_template_message
 * 
 * Descripción:
 * Esta función se encarga de enviar un message template de WhatsApp a través de Support Board.
 * Para que la función funcione correctamente, es necesario que se le pase un array asociativo
 * con la siguiente estructura y datos:
 * 
 * $data = array(
 *     'phone' => '+51956031565',                 // Número de teléfono del destinatario, debe comenzar con '+'
 *     'conversation_id' => '3',                 // ID de la conversación en Support Board. Es crítico para rastrear el chat específico
 *     'message_template_id' => 'nombre_template', // Nombre exacto del template registrado en WhatsApp Business API
 *     'header_variables' => 'valor_header',      // Valor(es) que remplazarán a los placeholders del encabezado del template
 *     'body_variables' => 'valor_body',          // Valor(es) que remplazarán a los placeholders del cuerpo del template
 *     'language' => 'es'                        // Idioma del template (por ejemplo, 'es' para español). Cada template puede tener variaciones de idioma
 * );
 * 
 * Es fundamental que todos estos datos estén presentes en el array y sean válidos para que el message template
 * pueda ser enviado correctamente. Esta función asume que todos los datos requeridos están presentes en el array.
 * 
 * @param array $data - Array asociativo con toda la información necesaria para enviar el message template.
 * @return string - Estado de la operación.
 */
function send_whatsapp_template_message($data) {
    // Elevar privilegios
    $GLOBALS['SB_FORCE_ADMIN'] = true;

    // Extraer los datos
    $phone = $data['phone'];
    $user_language = 'es'; // Mantener siempre en "es" como lo mencionaste
    $conversation_url_parameter = $data['conversation_id'];
    $template_name = $data['template_name']; // Tomando 'template_name' desde $data
    $phone_number_id = false; // Support Board maneja esto internamente

    // Parámetros del template
    $header_parameters = isset($data['header_variables']) ? $data['header_variables'] : ''; 
    $body_parameters = isset($data['body_variables']) ? $data['body_variables'] : '';
    $parameters = array($header_parameters, $body_parameters);
    
    $template_languages = $data['language'];

    // Llamar a la función sb_whatsapp_send_template
    $rawResponse = sb_whatsapp_send_template(
        $phone,
        $user_language,
        $conversation_url_parameter,
        '',  // $user_name no se utiliza, lo pasamos como una cadena vacía
        '',  // $user_email no se utiliza, lo pasamos como una cadena vacía
        $template_name,
        $phone_number_id,
        $parameters,
        $template_languages
    );

    // Desactivar modo administrador
    $GLOBALS['SB_FORCE_ADMIN'] = false;

    // Construir la respuesta combinada
    $combinedResponse = array(
        'status' => $rawResponse ? "sent" : "failed",
        'rawResponse' => $rawResponse
    );

    return $combinedResponse;
}

?>