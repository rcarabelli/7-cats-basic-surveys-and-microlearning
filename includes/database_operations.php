<?php
// Llamar al archivo config.php
require_once __DIR__ . '/../config.php';

add_action('wp_ajax_insert_template_log', 'handle_insert_template_log');

function handle_insert_template_log() {
    $response = insert_template_log($_POST);
    wp_send_json($response);
}


//***********************************************************************************************************************************************//
//***********************************************************************************************************************************************//
//* Funciones de administración, edición y creación de las plantillas de message templates*//
//***********************************************************************************************************************************************//
//***********************************************************************************************************************************************//


// Función para insertar los mensajes via message templates que se han enviado o se van a enviar
function insert_template_log($data) {
    global $wpdb;
    
    // Sanitiza los datos recibidos
    $template_id = sanitize_text_field($data['template_id']);
    $user_ids_array = explode(',', sanitize_text_field($data['user_ids'])); // Convertir la cadena a un array
    $header_variables = sanitize_text_field($data['header_variables']);
    $body_variables = sanitize_text_field($data['body_variables']);
    $language = sanitize_text_field($data['language']);
    
    // Nombre de tu tabla
    $table_name = '7c_send_message_template_logs';

    // Resultados de las inserciones
    $results = [];

    // Iterar sobre cada user_id e insertarlo individualmente
    foreach($user_ids_array as $user_id) {
        $result = $wpdb->insert(
            $table_name,
            array(
                'message_template_id' => $template_id,
                'users_ids' => $user_id, // Solo insertar un user_id
                'header_variables' => $header_variables,
                'body_variables' => $body_variables,
                'language' => $language,
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );

        // Agregar el resultado de la inserción al array de resultados
        $results[] = $result;
    }
    
    // Comprobar si todas las inserciones fueron exitosas
    if(!in_array(false, $results, true)) {
        return array('success' => true, 'message' => 'Data successfully inserted into the database for all user IDs.');
    } else {
        return array('success' => false, 'message' => $wpdb->last_error);
    }
}




//***********************************************************************************************************************************************//
//***********************************************************************************************************************************************//
//* Lista de funciones que sirven para recuperar datos del sb para diferentes transacciones, cosas como recuperar user id y conversaciones, etc.*//
//***********************************************************************************************************************************************//
//***********************************************************************************************************************************************//


// Función para recuperar el sb_conversation_id usando un user_id
function recover_sb_conversation_ids_from_user_id($user_id) {
    global $wpdb;
    
    $table_name = 'sb_conversations';
    $status_codes = array(1, 2, 3);
    
    $sql = $wpdb->prepare(
        "SELECT id FROM $table_name WHERE user_id = %d AND status_code IN (%s, %s, %s)",
        $user_id,
        $status_codes[0],
        $status_codes[1],
        $status_codes[2]
    );
    
    $result = $wpdb->get_col($sql);
    
    return $result;
}


// Función para recuperar la conversación en la cual se conversó más recientemente de un user_id
function recover_sb_more_recent_conversation_id_from_message_id($user_id) {
    global $wpdb;
    
    // Obtenemos todos los conversation_ids para el user_id
    $conversation_ids = recover_sb_conversation_ids_from_user_id($user_id);
    
    // Si solo hay una conversación, simplemente la retornamos
    if (count($conversation_ids) === 1) {
        return $conversation_ids[0];
    }
    
    $table_name = 'sb_messages';
    $most_recent_date = '0000-00-00 00:00:00'; // Una fecha inicial
    $most_recent_conversation_id = null;
    
    foreach ($conversation_ids as $conversation_id) {
        $sql = $wpdb->prepare(
            "SELECT creation_time FROM $table_name WHERE conversation_id = %d ORDER BY creation_time DESC LIMIT 1",
            $conversation_id
        );
        
        $latest_date = $wpdb->get_var($sql);
        
        if ($latest_date && $latest_date > $most_recent_date) {
            $most_recent_date = $latest_date;
            $most_recent_conversation_id = $conversation_id;
        }
    }
    
    return $most_recent_conversation_id;
}


/**
 * Función: recover_sb_user_data_user_phone_number
 * 
 * Descripción:
 * Esta función recupera el número de teléfono del usuario de la tabla 'sb_users_data'.
 * 
 * @param int $user_id - ID del usuario.
 * 
 * @return string - Número de teléfono si es válido, o "no hay un número válido" si no lo es o no se encuentra.
 */
function recover_sb_user_data_user_phone_number($user_id) {
    global $wpdb;

    // Nombre de la tabla
    $table_name = 'sb_users_data';

    // Consulta para obtener el valor de 'Phone' para el user_id dado
    $sql = $wpdb->prepare(
        "SELECT value 
         FROM $table_name 
         WHERE user_id = %d AND name = 'Phone'", 
         $user_id
    );

    $phone = $wpdb->get_var($sql);

    // Verifica si se encontró un número de teléfono
    if (!$phone) {
        return "no hay un número válido";
    }

    // Valida el formato del número de teléfono
    if (preg_match("/^\+[0-9]{8,15}$/", $phone)) {
        return $phone;
    } else {
        return "no hay un número válido";
    }
}




//***********************************************************************************************************************************************//
//***********************************************************************************************************************************************//
//* Lista de funciones que sirven para preparar al sistema para envio de message templates*//
//***********************************************************************************************************************************************//
//***********************************************************************************************************************************************//


/**
 * Función: message_template_prepare_data_from_db_to_send_wa_message_template
 * Descripción: Esta función busca en la tabla 7c_send_message_template_logs la primera ocurrencia 
 * con status "pending" y recopila los datos necesarios para enviar el message template.
 * 
 * @return array Asociativo con los valores necesarios para el envío del message template.
 */
function message_template_prepare_data_from_db_to_send_wa_message_template() {
    global $wpdb;
    
    // Nombre de la tabla
    $table_name = '7c_send_message_template_logs';

    // Consulta para obtener la primera fila con status "pending"
    $sql = "SELECT id AS template_log_id, message_template_id, users_ids, header_variables, body_variables, language 
            FROM $table_name 
            WHERE status = 'pending' 
            LIMIT 1";
    
    $row = $wpdb->get_row($sql, ARRAY_A);

    return $row; // Devuelve un array asociativo con los datos
}




/**
 * Actualiza el estado de un message template en la base de datos.
 * 
 * Esta función se encarga de actualizar el estado ("status") de un message template específico
 * en la tabla `7c_send_message_template_logs`. Los estados posibles son "sent", "pending" y "failed".
 * 
 * @param int $message_template_id El ID único del message template que se desea actualizar.
 * @param string $status El nuevo estado para el message template. Puede ser "sent", "pending" o "failed".
 * 
 * @return void
 */
function update_template_status_in_db($row_id, $status) {
    global $wpdb;
    
    // Nombre de la tabla
    $table_name = '7c_send_message_template_logs';

    // Actualiza el status
    $wpdb->update(
        $table_name,
        array('status' => $status),
        array('id' => $row_id),
        array('%s'),
        array('%d')
    );
}



/**
 * Recupera el nombre del template basado en su ID.
 * 
 * @param int $template_id El ID del template.
 * @return string|null El nombre del template o null si no se encuentra.
 */
function message_template_send_get_name_of_template($template_id) {
    global $wpdb;
    $table_name = '7c_whatsapp_template_configurations';
    return $wpdb->get_var($wpdb->prepare("SELECT template_name FROM $table_name WHERE id = %d", $template_id));
}




/**
 * Función: message_template_find_final_data_from_user_conversation_to_send
 * 
 * Descripción: 
 * Esta función realiza tres tareas principales:
 * 1. Consulta la tabla '7c_send_message_template_logs' para encontrar la primera entrada con status "pending".
 * 2. Extrae el 'user_id' (users_ids) de esa entrada.
 * 3. Busca y devuelve el 'conversation_id' más reciente asociado con ese 'user_id'.
 * 4. Extrae el número telefónico llamando a recover_sb_user_data_user_phone_number
 * 
 * Como resultado, la función devuelve un array que contiene la entrada completa de '7c_send_message_template_logs' 
 * y el 'conversation_id' más reciente. Esta combinación de datos se necesita para enviar el message template 
 * a través de WhatsApp.
 * 
 * Si no hay más entradas "pending" en '7c_send_message_template_logs', devuelve un estado 'no_more_pending'.
 * Si no se encuentra un 'conversation_id' asociado con el 'user_id', devuelve un estado 'no_conversation_found'.
 * 
 * Devuelve un array asociativo con un estado y, si es exitoso, los datos necesarios para enviar el message template.
 * 
 * @return array - Contiene el estado de la operación y los datos recuperados.
 */
function message_template_find_final_data_from_user_conversation_to_send() {
    // Paso i) Recuperar datos del template
    $data = message_template_prepare_data_from_db_to_send_wa_message_template();
    
    // Verificar si la función anterior devolvió datos
    if (!$data) {
        return array('status' => 'no_more_pending');
    }

    // Paso ii) Recuperar user_id y buscar número de teléfono
    $user_id = $data['users_ids'];
    $phone = recover_sb_user_data_user_phone_number($user_id);
    if ($phone === "no hay un número válido") {
        return array('status' => 'invalid_phone', 'data' => $data);
    }

    // Añadir el número de teléfono a la data
    $data['phone'] = $phone;

    // Paso iii) Recuperar el ID de conversación más reciente
    $conversation_id = recover_sb_more_recent_conversation_id_from_message_id($user_id);
    if (!$conversation_id) {
        return array('status' => 'no_conversation_found', 'data' => $data);
    }

    // Añadir el ID de conversación a la data
    $data['conversation_id'] = $conversation_id;

    // Recuperar el nombre del template usando el message_template_id
    $template_name = message_template_send_get_name_of_template($data['message_template_id']);
    if ($template_name) {
        $data['template_name'] = $template_name;
    }

    // Todo está listo para enviar el message template
    return array('status' => 'success', 'data' => $data);
}
