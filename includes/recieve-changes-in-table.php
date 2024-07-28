<?php
// Llamar al archivo config.php
require_once __DIR__ . '/../config.php';
require_once 'verify_new_messages_to_see_the_content.php';


// Ahora puedes usar las constantes
require_once SUPPORT_FUNCTIONS_FILE;


// Ruta completa al archivo debug.txt
$debug_file_path = MY_PLUGIN_PATH . 'includes/debug.txt';

// Incluye el archivo que contiene la función send_messages
require_once(MY_PLUGIN_PATH . 'includes/send_messages_to_sb_wa_others.php');

// Incluir las funciones de wordpress
// require_once('/home/<account>/public_html/wp-load.php');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Debugging: Check if the file is being included
//file_put_contents("debug_include.txt_include.txt", "The receive-changes-in-table.php file was included.\n", FILE_APPEND);



// Funcion para procesar nuevos mensajes y ver si cumplen con el criterio de tipo de contenido en el mensaje
function get_new_messages($original_line_count, $new_line_count) {
    global $wpdb;
    $sql = $wpdb->prepare(
        "SELECT id, conversation_id, message FROM sb_messages LIMIT %d, %d",
        $original_line_count, $new_line_count
    );
    $result = $wpdb->get_results($sql);
    //file_put_contents("debug_include.txt.txt", "Fetched new messages: " . print_r($result, true) . "\n", FILE_APPEND);
    return $result;
}

function check_active_survey($conversation_id) {
    global $wpdb;
    $sql = $wpdb->prepare("SELECT * FROM 7c_survey_status WHERE conversation_id = %s AND status = 'active'", $conversation_id);
    $result = $wpdb->get_results($sql);
    //file_put_contents("debug_include.txt.txt", "Checked active survey for conversation_id $conversation_id: " . print_r($result, true) . "\n", FILE_APPEND);
    return $result;
}



/**
 * La función process_new_messages procesa cada nuevo mensaje en la tabla sb_messages, verificando el tipo de usuario que envió el mensaje,
 * y si es un 'user', evalúa el tiempo transcurrido entre el mensaje actual y el último mensaje enviado por el usuario en la misma conversación.
 * Si ha transcurrido más de 24 horas, envía un mensaje de bienvenida de vuelta.
 * Además, realiza comprobaciones adicionales para determinar si el mensaje está asociado con una encuesta activa y si
 * la respuesta del usuario es válida según el tipo de respuesta esperado para la pregunta de la encuesta, registrando la respuesta
 * del usuario o registrando el error si la respuesta es inválida.
 */
// Incluye el archivo que contiene la función send_messages
require_once("send_messages_to_sb_wa_others.php");



//*******************************************************************************************\\
//*******************************************************************************************\\
//*********************VALIDACIONES DE TIPOS DE MENSAJE PARA FORMULARIOS*********************\\
//*******************************************************************************************\\
//*******************************************************************************************\\


function mapLineCountToConversationId($new_line_count) {
    global $wpdb;
    
    // Fetch the latest message's ID based on the total message count
    $latestMessageId = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM sb_messages ORDER BY id DESC LIMIT 1 OFFSET %d",
            $new_line_count - 1 // Adjust for zero-based index if needed
        )
    );

    if ($latestMessageId) {
        // Fetch the conversation ID for the latest message
        $conversationId = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT conversation_id FROM sb_messages WHERE id = %d",
                $latestMessageId
            )
        );

        if ($conversationId) {
            return $conversationId;
        } else {
            // Log or handle the error scenario where conversation ID could not be found
            $logMessage = "No conversation ID found for message with ID: " . $latestMessageId . "\n";
            file_put_contents(__DIR__ . '/process_new_messages_log.txt', $logMessage, FILE_APPEND);
            return false;
        }
    } else {
        // Log or handle the error scenario where latest message could not be fetched
        $logMessage = "No latest message found for line count: " . $new_line_count . "\n";
        file_put_contents(__DIR__ . '/process_new_messages_log.txt', $logMessage, FILE_APPEND);
        return false;
    }
}


function checkMessageSourceAndAuthorize($conversationId, $messageId) {
    global $wpdb;

    // Initialize log file path
    $logFilePath = __DIR__ . '/checkMessageSourceAndAuthorize_log.txt';

    // Log the function call with conversationId and messageId
    $logMessage = "Called checkMessageSourceAndAuthorize with ConversationID: {$conversationId}, MessageID: {$messageId}\n";
    file_put_contents($logFilePath, $logMessage, FILE_APPEND);

    // Fetch 'extra' from 'sb_conversations'
    $extra = $wpdb->get_var($wpdb->prepare("SELECT extra FROM sb_conversations WHERE id = %d", $conversationId));

    // Log the retrieved 'extra' value
    $logMessage = "Retrieved 'extra' for ConversationID {$conversationId}: {$extra}\n";
    file_put_contents($logFilePath, $logMessage, FILE_APPEND);

    if ($extra === "250236584839493") {
        // Log that 'extra' matches the condition
        $logMessage = "'extra' matches the specified condition for ConversationID {$conversationId}. Calling verifyNewMessages with MessageID: {$messageId}\n";
        file_put_contents($logFilePath, $logMessage, FILE_APPEND);

        // Call verifyNewMessages and pass $messageId
        verifyNewMessages($messageId);

        // Log that further processing for this message is halted
        $logMessage = "Further processing halted for MessageID: {$messageId} due to matching 'extra'.\n";
        file_put_contents($logFilePath, $logMessage, FILE_APPEND);

        return false; // Indicate that the main processing should not continue for this message
    }

    // Log that processing will continue as 'extra' did not match the condition
    $logMessage = "Processing will continue for MessageID: {$messageId} as 'extra' did not match the condition.\n";
    file_put_contents($logFilePath, $logMessage, FILE_APPEND);

    return true; // Continue with the main processing if the condition is not met
}




function process_new_messages($original_line_count, $new_line_count) {
    global $wpdb;

    // Block 1: Check for 10-second interval
    $last_run = get_transient('last_process_new_messages_run');
    if ($last_run) return;
    set_transient('last_process_new_messages_run', true, 10);

    // Block 2 & 3: Fetch messages and process each
    $sql = $wpdb->prepare("SELECT id, user_id, conversation_id, creation_time, message FROM sb_messages LIMIT %d, %d", $original_line_count, $new_line_count);
    $new_messages = $wpdb->get_results($sql);

    foreach ($new_messages as $msg) {
        // Verify if the sender is a 'user'
        $user_type = $wpdb->get_var($wpdb->prepare("SELECT user_type FROM sb_users WHERE id = %d", $msg->user_id));
        if ($user_type !== 'user') continue; // Skip if not a 'user'

        // Block 2 Continued: Special handling check based on 'extra'
        $extra = $wpdb->get_var($wpdb->prepare("SELECT extra FROM sb_conversations WHERE id = %d", $msg->conversation_id));
        if ($extra === "250236584839493") {
            verifyNewMessages($msg->id); // Call special function and stop processing this message
            continue;
        }

        $sql = $wpdb->prepare(
            "SELECT creation_time FROM sb_messages WHERE conversation_id = %d AND user_id = %d ORDER BY creation_time DESC LIMIT 1, 1",
            $msg->conversation_id, $msg->user_id
        );
        $last_message_time = $wpdb->get_var($sql);

        if ($last_message_time) {
            $last_message_timestamp = strtotime($last_message_time);
            $current_message_timestamp = strtotime($msg->creation_time);
            $time_difference_hours = ($current_message_timestamp - $last_message_timestamp) / 3600;

            if ($time_difference_hours > 24) {
                $welcome_back_message = '¡Hola nuevamente! Apreciamos su retorno. En breve, un asesor se pondrá en contacto con usted para atender cualquier solicitud o requerimiento que pueda tener.';
                send_messages($welcome_back_message, $msg->conversation_id);
            }
        }

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM 7c_survey_user_responses WHERE sb_message_id = %d",
            $msg->id
        );
        $exists = $wpdb->get_var($sql);

        if ($exists) continue;

        $sql = $wpdb->prepare(
            "SELECT * FROM 7c_survey_status WHERE conversation_id = %s AND status = 'active'",
            $msg->conversation_id
        );
        $status_result = $wpdb->get_results($sql);

        if (count($status_result) > 0) {
            $current_survey_id = $status_result[0]->survey_id;
            $current_question_id = $status_result[0]->current_question_id;

            $sql = $wpdb->prepare(
                "SELECT type_id, answer_type FROM 7c_survey_questions WHERE question_id = %d",
                $current_question_id
            );
            $question_info = $wpdb->get_row($sql);
            $type_id = $question_info->type_id;
            $answer_type = $question_info->answer_type;

            $user_answer = $msg->message;

            if (is_valid_answer($type_id, $answer_type, $user_answer, $current_question_id, $msg->conversation_id, $msg->id)) {
                $insert_successful = insert_user_response($msg->conversation_id, $current_survey_id, $current_question_id, $user_answer, $msg->id);

                if (!$insert_successful) {
                    // Handle the case when the insertion fails if necessary.
                }
            } else {
                // Check if the previous message has attachments
                $sql = $wpdb->prepare(
                    "SELECT attachments FROM sb_messages 
                     WHERE conversation_id = %s 
                     AND creation_time < %s 
                     ORDER BY creation_time DESC LIMIT 1",
                    $msg->conversation_id, $msg->creation_time
                );
                
                $previous_message_attachments = $wpdb->get_var($sql);
                
                // If the previous message has attachments, don’t send the error message.
                if ($previous_message_attachments) continue;
                
                send_generic_message("Esa respuesta no es válida, por favor, elige entre las opciones existentes.", $msg->conversation_id);
                file_put_contents("invalid_answers.txt", "Invalid answer for conversation_id {$msg->conversation_id}: {$user_answer}\n", FILE_APPEND);
            }
        }
    }
}




//Esta función valida que lo que responda el usuario esté en un formato aceptable
function is_valid_answer($type_id, $answer_type, $user_answer, $current_question_id, $conversation_id, $sb_message_id) {
    global $wpdb;
    
    // Registrar el inicio de la función
    //file_put_contents("log_is_valid_answer_y_process_new_message.txt", "Function is_valid_answer called with type_id: $type_id, answer_type: $answer_type, user_answer: $user_answer, current_question_id: $current_question_id\n", FILE_APPEND);

    // Si type_id es 2 o 3, cualquier respuesta del usuario es válida
    if($type_id == 2) {
        // Llama a la función para manejar respuestas de texto y retorna su resultado
        return handle_text_response($type_id, $user_answer, $current_question_id, $conversation_id, $sb_message_id);
    }
    
    if($type_id == 3) {
        // Llama a la función para manejar respuestas de archivo y retorna su resultado
        return handle_file_response($type_id, $user_answer, $current_question_id, $conversation_id, $sb_message_id);
    }
    
    // Si type_id es 1 o 4, entonces se realiza la validación
    if($type_id == 1 || $type_id == 4) {
        
        // Validación para respuestas que comienzan con un número (seguido opcionalmente por un espacio)
        if (preg_match('/^[0-9]+\s?/', $user_answer, $matches)) {
            $user_option_number = intval($matches[0]); // Convertir a int el número extraído
            
            // Obtener el valor máximo de option_number para la pregunta actual
            $sql = $wpdb->prepare(
                "SELECT MAX(option_number) as max_option_number FROM 7c_possible_answers WHERE question_id = %d",
                $current_question_id
            );
            $max_option_number = $wpdb->get_var($sql);
            
            // Registrar los valores de user_option_number y max_option_number
            //file_put_contents("log_is_valid_answer_y_process_new_message.txt", "user_option_number: $user_option_number, max_option_number: $max_option_number\n", FILE_APPEND);

            if($user_option_number <= $max_option_number) {
                //file_put_contents("log_is_valid_answer_y_process_new_message.txt", "Returning true for user_option_number: $user_option_number\n", FILE_APPEND);
                return true; // La respuesta del usuario es válida
            } else {
                // Log para respuestas inválidas
                //file_put_contents("log_is_valid_answer_y_process_new_message.txt", "Invalid answer for question_id $current_question_id: $user_answer\n", FILE_APPEND);
                return false; // La respuesta del usuario no es válida
            }
        }
        
        // Si no se cumple la condición de número al inicio, registrar el fallo
        //file_put_contents("log_is_valid_answer_y_process_new_message.txt", "Failed number condition for user_answer: $user_answer\n", FILE_APPEND);

        // Condición original para respuestas específicas
        $valid_answers_1_5 = ['1', '2', '3', '4', '5', '1 ⭐', '2 ⭐⭐', '3 ⭐⭐⭐', '4 ⭐⭐⭐⭐', '5 ⭐⭐⭐⭐⭐'];
        $valid_answers_A_E = ['A', 'B', 'C', 'D', 'E'];
        
        if ($answer_type == '1-5') {
            $isValid = in_array($user_answer, $valid_answers_1_5);
            //file_put_contents("log_is_valid_answer_y_process_new_message.txt", "Returning $isValid for user_answer: $user_answer in valid_answers_1_5\n", FILE_APPEND);
            return $isValid;
        } elseif ($answer_type == 'A-E') {
            $isValid = in_array(strtoupper($user_answer), $valid_answers_A_E);
            //file_put_contents("log_is_valid_answer_y_process_new_message.txt", "Returning $isValid for user_answer: $user_answer in valid_answers_A_E\n", FILE_APPEND);
            return $isValid;
        }
    }
    
    // En caso de que no se cumpla ninguna de las condiciones anteriores
    //file_put_contents("log_is_valid_answer_y_process_new_message.txt", "Returning false as no condition met for user_answer: $user_answer\n", FILE_APPEND);
    return false;
}


// Funcion de manejo de las condiciones si el mensaje corresponde a una respuesta de texto / Función para manejar respuestas de texto (type_id == 2)
function handle_text_response($type_id, $user_answer, $current_question_id, $conversation_id, $sb_message_id) {
    global $wpdb;
    
    // Obtén el survey_id de la tabla 7c_survey_status para el conversation_id dado
    $sql = $wpdb->prepare(
        "SELECT survey_id FROM 7c_survey_status WHERE conversation_id = %s AND current_question_id = %d",
        $conversation_id, $current_question_id
    );
    $survey_id = $wpdb->get_var($sql);
    
    if(!$survey_id) {
        // Maneja el caso en el que no se encuentra survey_id para el conversation_id dado
        return false;
    }
    
    // Verifica si ya existe una fila con el mismo conversation_id y question_id en 7c_survey_user_responses
    $sql = $wpdb->prepare(
        "SELECT response_id FROM 7c_survey_user_responses WHERE conversation_id = %s AND question_id = %d",
        $conversation_id, $current_question_id
    );
    $response_id = $wpdb->get_var($sql);
    
    if($response_id) {
        // Si existe, actualiza la fila existente
        $sql = $wpdb->prepare(
            "UPDATE 7c_survey_user_responses 
             SET user_text_response = %s, updated_at = NOW()
             WHERE response_id = %d",
            $user_answer, $response_id
        );
    } else {
        // Si no existe, inserta una nueva fila
        $sql = $wpdb->prepare(
            "INSERT INTO 7c_survey_user_responses (conversation_id, survey_id, question_id, user_text_response, sb_message_id, created_at, updated_at) 
             VALUES (%s, %d, %d, %s, %d, NOW(), NOW())",
            $conversation_id, $survey_id, $current_question_id, $user_answer, $sb_message_id
        );
    }
    
    // Ejecuta la consulta y retorna si fue exitosa o no
    $result = $wpdb->query($sql);
    return $result !== false;
}



// Funcion de manejo de las condiciones si el mensaje corresponde a una respuesta de carga de archivos / Función para manejar respuestas de archivo (type_id == 3)
function handle_file_response($type_id, $user_answer, $current_question_id, $conversation_id, $sb_message_id) {
    global $wpdb;
    
    $log_file = 'log_handle_file_response.txt';
    
    // file_put_contents($log_file, "Starting function\n", FILE_APPEND);
    
    $sql = $wpdb->prepare(
        "SELECT survey_id FROM 7c_survey_status WHERE conversation_id = %s AND current_question_id = %d",
        $conversation_id, $current_question_id
    );
    $survey_id = $wpdb->get_var($sql);
    
    if(!$survey_id) {
        // file_put_contents($log_file, "No survey_id found\n", FILE_APPEND);
        return false;
    }
    
    // file_put_contents($log_file, "survey_id: $survey_id\n", FILE_APPEND);
    
    $sql = $wpdb->prepare(
        "SELECT id, message, user_id FROM sb_messages WHERE id = %d",
        $sb_message_id
    );
    $first_message = $wpdb->get_row($sql);
    $first_message_id = $first_message->id;
    $first_message_text = $first_message->message;
    $user_id = $first_message->user_id;
    
    // file_put_contents($log_file, "sb_message_id: $first_message_id\n", FILE_APPEND);
    // file_put_contents($log_file, "first_message_text: $first_message_text\n", FILE_APPEND);
    
    $total_wait_time = 2;
    $interval = 0.5;
    $message_ids = [$first_message_id];
    
    for ($i = 0; $i < $total_wait_time / $interval; $i++) {
        usleep($interval * 1000000);
        
        $timestamp = date('Y-m-d H:i:s');
        // file_put_contents($log_file, "Checking for new messages at: $timestamp\n", FILE_APPEND);
        
        $sql = $wpdb->prepare(
            "SELECT id FROM sb_messages 
             WHERE conversation_id = %s 
             AND id > %d 
             AND user_id = %s",
            $conversation_id, max($message_ids), $user_id
        );
        
        $new_message_ids = $wpdb->get_col($sql);
        
        if ($new_message_ids) {
            $message_ids = array_merge($message_ids, $new_message_ids);
            // file_put_contents($log_file, "Found Message IDs: " . implode(', ', $new_message_ids) . "\n", FILE_APPEND);
        }
    }
    
    if($message_ids) {
        $placeholders = implode(', ', array_fill(0, count($message_ids), '%d'));
        $sql = $wpdb->prepare("SELECT id, message FROM sb_messages WHERE id IN ($placeholders)", $message_ids);
        $messages_list = $wpdb->get_results($sql, ARRAY_A);
        
        foreach ($messages_list as $message) {
            if (!empty(trim($message['message']))) {
                $first_message_text = $message['message'];
                break;
            }
        }
        
        // file_put_contents($log_file, "Final message_text: $first_message_text\n", FILE_APPEND);
        
        $sql = $wpdb->prepare("SELECT attachments FROM sb_messages WHERE id IN ($placeholders)", $message_ids);
        $attachments_list = $wpdb->get_results($sql, ARRAY_A);
        
        $files = [];
        foreach ($attachments_list as $attachment) {
            $attach_data = json_decode($attachment['attachments']);
            foreach ($attach_data as $file) {
                $files[] = ["name" => $file[0], "url" => $file[1]];
            }
        }
        
        $files_json = json_encode($files);
        // file_put_contents($log_file, "files_json: $files_json\n", FILE_APPEND);
    } else {
        $files_json = json_encode([]);
    }
    
    $sql = $wpdb->prepare(
        "SELECT response_id FROM 7c_survey_user_responses WHERE conversation_id = %s AND question_id = %d",
        $conversation_id, $current_question_id
    );
    $response_id = $wpdb->get_var($sql);
    
    if($response_id) {
        $sql = $wpdb->prepare(
            "UPDATE 7c_survey_user_responses 
             SET user_text_response = %s, user_file_links = %s, updated_at = NOW()
             WHERE response_id = %d",
            $first_message_text, $files_json, $response_id
        );
    } else {
        $sql = $wpdb->prepare(
            "INSERT INTO 7c_survey_user_responses (conversation_id, survey_id, question_id, user_text_response, user_file_links, sb_message_id, created_at, updated_at) 
             VALUES (%s, %d, %d, %s, %s, %d, NOW(), NOW())",
            $conversation_id, $survey_id, $current_question_id, $first_message_text, $files_json, $sb_message_id
        );
    }
    
    $result = $wpdb->query($sql);
    // file_put_contents($log_file, "SQL Query result: " . ($result !== false ? "Success" : "Failed") . "\n", FILE_APPEND);
    
    return $result !== false;
}








//*******************************************************************************************\\
//*******************************************************************************************\\
//*******************************************************************************************\\
//*******************************************************************************************\\













//*******************************************************
// Funcion para insertar la respuesta de la encuesta/cuestionario
function insert_user_response($conversation_id, $survey_id, $question_id, $user_answer, $sb_message_id) {
    global $wpdb;
    $debug_file_path = "debug_file.txt";
    
    // Extraer el número del inicio del string de la respuesta del usuario.
    // Esto es útil cuando la respuesta del usuario contiene información adicional después del número,
    // como "4 ⭐⭐⭐⭐" y queremos solo el "4".
    preg_match('/^(\d+)/', $user_answer, $matches);
    $user_answer = $matches[1];  // Ahora $user_answer solo contiene el número extraído.
    
    // Paso 1: Preparar los datos para insertar.
    $insert_data = array(
        'conversation_id' => $conversation_id,
        'survey_id'       => $survey_id,
        'question_id'     => $question_id,
        'user_answer'     => $user_answer,  // $user_answer solo contiene el número.
        'sb_message_id'   => $sb_message_id  // Asegúrate de pasar este valor cuando llames a la función
    );

    // Paso 2: Verificar registros coincidentes en 7c_survey_status.
    $sql = $wpdb->prepare(
        "SELECT 1 FROM 7c_survey_status WHERE conversation_id = %d AND survey_id = %d AND current_question_id = %d",
        $conversation_id,
        $survey_id,
        $question_id
    );
    $matching_status = $wpdb->get_var($sql);

    // Proceder solo si hay un registro coincidente en 7c_survey_status.
    if ($matching_status) {

        // Paso 4: Verificar la restricción de tiempo.
        $sql = $wpdb->prepare(
            "SELECT UNIX_TIMESTAMP(updated_at) as updated_at FROM 7c_survey_user_responses WHERE conversation_id = %d AND question_id = %d ORDER BY updated_at DESC LIMIT 1",
            $conversation_id,
            $question_id
        );
        $last_update_time = $wpdb->get_var($sql);
        $should_skip = ($last_update_time && (time() - $last_update_time < 5));

        // Saltar la operación de base de datos si no ha pasado suficiente tiempo.
        if ($should_skip) {
            // file_put_contents($debug_file_path, "Skipping database operation due to time constraint.\n", FILE_APPEND);
        } else {

            // Paso 3 & 5: Insertar o Actualizar user_answer en 7c_survey_user_responses.
            $sql = $wpdb->prepare(
                "SELECT response_id FROM 7c_survey_user_responses WHERE conversation_id = %d AND question_id = %d",
                $conversation_id,
                $question_id
            );
            $existing_id = $wpdb->get_var($sql);

            if ($existing_id) {
                $where = array('response_id' => $existing_id);
                $wpdb->update('7c_survey_user_responses', $insert_data, $where);
            } else {
                $wpdb->insert('7c_survey_user_responses', $insert_data);
            }
        }

        // Paso 6: Siempre avanzar a la siguiente pregunta.
        // file_put_contents($debug_file_path, "Advancing to next question.\n", FILE_APPEND);
        advance_to_next_question($conversation_id, $question_id, $survey_id);
    }
}





//*******************************************************
// Funcion para pasar a la siguiente pregunta
function advance_to_next_question($conversation_id, $current_question_id, $survey_id) {
    global $wpdb;

    // Inicio de la función
    //file_put_contents("debug_include.txt_advance.txt", "\n\n**************** START Function advance_to_next_question ***************\n", FILE_APPEND);
    //file_put_contents("debug_include.txt_advance.txt", "Called with: conversation_id=$conversation_id, current_question_id=$current_question_id, survey_id=$survey_id\n\n", FILE_APPEND);

    // Paso 1: Verificar el estado más reciente de la encuesta para esa conversación
    //file_put_contents("debug_include.txt_advance.txt", "1. Fetching current status...\n", FILE_APPEND);
    $sql = $wpdb->prepare(
        "SELECT status, UNIX_TIMESTAMP(updated_at) as updated_at FROM 7c_survey_status WHERE conversation_id = %d AND survey_id = %d AND status = 'active' ORDER BY updated_at DESC LIMIT 1",
        $conversation_id,
        $survey_id
    );
    $current_status = $wpdb->get_row($sql);
    //file_put_contents("debug_include.txt_advance.txt", "Current status fetched: " . print_r($current_status, true) . "\n\n", FILE_APPEND);

    // Paso 2: Verificar el estado 'active'
    if ($current_status === null || $current_status->status !== 'active') {
        //file_put_contents("debug_include.txt_advance.txt", "2. Status is not active. Exiting...\n**************** END Function advance_to_next_question ***************\n\n", FILE_APPEND);
        return;
    }
    //file_put_contents("debug_include.txt_advance.txt", "2. Status is active. Proceeding...\n", FILE_APPEND);

    // Paso 3: Evitar actualizar demasiado rápido
    if ($current_status->updated_at && (time() - $current_status->updated_at < 5)) {
        //file_put_contents("debug_include.txt_advance.txt", "3. Time check failed. Exiting...\n**************** END Function advance_to_next_question ***************\n\n", FILE_APPEND);
        return;
    }
    //file_put_contents("debug_include.txt_advance.txt", "3. Time check passed. Proceeding...\n", FILE_APPEND);

    // Paso 4: Buscar todas las preguntas de la encuesta
    //file_put_contents("debug_include.txt_advance.txt", "4. Fetching all question IDs...\n", FILE_APPEND);
    $sql = $wpdb->prepare(
        "SELECT question_id FROM 7c_survey_questions WHERE survey_id = %d ORDER BY question_id ASC",
        $survey_id
    );
    $all_question_ids = $wpdb->get_col($sql);
    //file_put_contents("debug_include.txt_advance.txt", "Fetched all question IDs: " . print_r($all_question_ids, true) . "\n\n", FILE_APPEND);

    // Paso 5: Buscar el índice de la pregunta actual
    //file_put_contents("debug_include.txt_advance.txt", "5. Finding next question...\n", FILE_APPEND);
    $current_index = array_search($current_question_id, $all_question_ids);
    $next_index = $current_index + 1;
    //file_put_contents("debug_include.txt_advance.txt", "Current question index: $current_index, Next question index: $next_index\n\n", FILE_APPEND);

    // Paso 6: Actualizar a la siguiente pregunta o marcar la encuesta como completada
    if (isset($all_question_ids[$next_index])) {
        // Hay una siguiente pregunta
        //file_put_contents("debug_include.txt_advance.txt", "6. Next question exists. Updating to next question.\n", FILE_APPEND);
        $next_question_id = $all_question_ids[$next_index];
        $wpdb->update(
            '7c_survey_status',
            array('current_question_id' => $next_question_id),
            array(
                'conversation_id' => $conversation_id,
                'survey_id' => $survey_id,
                'status' => 'active'
            )
        );
        // Llamar a la función para enviar la nueva pregunta
        handle_send_survey($conversation_id, $survey_id, $wpdb);
    } else {
        // No hay más preguntas
        //file_put_contents("debug_include.txt_advance.txt", "6. No more questions. Checking if last question was answered.\n", FILE_APPEND);
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM 7c_survey_user_responses WHERE conversation_id = %d AND question_id = %d",
            $conversation_id,
            $current_question_id
        );
        $last_question_answered = $wpdb->get_var($sql);

        if ($last_question_answered > 0) {
            //file_put_contents("debug_include.txt_advance.txt", "Last question was answered. Marking survey as completed.\n", FILE_APPEND);
            $wpdb->update(
                '7c_survey_status',
                array('status' => 'completed'),
                array(
                    'conversation_id' => $conversation_id,
                    'survey_id' => $survey_id,
                    'status' => 'active'
                )
            );
            // Enviar mensaje de agradecimiento por completar la encuesta
            temp_survey_report($conversation_id, $survey_id);
            // send_generic_message("¡Gracias por su tiempo! Su opinión es importante para mejorar y ofrecerle experiencias aún más excepcionales en el futuro.", $conversation_id);
        } else {
            //file_put_contents("debug_include.txt_advance.txt", "Last question was NOT answered. Keeping survey as active.\n", FILE_APPEND);
        }
    }

    // Fin de la función
    //file_put_contents("debug_include.txt_advance.txt", "\n**************** END Function advance_to_next_question ***************\n\n", FILE_APPEND);
}


// FUNCION TEMPORAL PARA DEVOLVER AL USUARIO LAS RESPUESTAS QUE DIO
function temp_survey_report($conversation_id, $survey_id) {
    global $wpdb;
    $debug_file_path = "debug_temp_survey_report.txt";
    
    // Obtener el nombre de la encuesta
    $sql = $wpdb->prepare("SELECT survey_name FROM 7c_survey_surveys WHERE survey_id = %d", $survey_id);
    $survey_info = $wpdb->get_row($sql);
    
    // Mensaje de agradecimiento
    send_generic_message("Agradecemos el que haya respondido la encuesta " . $survey_info->survey_name, $conversation_id);

    // El código a continuación está comentado y no se ejecutará.
    // Si en el futuro se necesita habilitar nuevamente, simplemente quitar los comentarios.
    /*
    // Log: Inicio de la función
    file_put_contents($debug_file_path, "\n\n**************** START Function temp_survey_report ***************\n", FILE_APPEND);

    // Paso 1: Obtener la intención de la encuesta (Ya no es necesario)
    // $sql = $wpdb->prepare("SELECT intent FROM 7c_survey_surveys WHERE survey_id = %d", $survey_id);
    // $survey_info = $wpdb->get_row($sql);
    // Log: Información de la encuesta
    file_put_contents($debug_file_path, "Survey Info: " . print_r($survey_info, true) . "\n", FILE_APPEND);

    // Paso 2: Obtener todas las respuestas del usuario para esta encuesta
    $sql = $wpdb->prepare("SELECT * FROM 7c_survey_user_responses WHERE conversation_id = %d AND survey_id = %d", $conversation_id, $survey_id);
    $user_responses = $wpdb->get_results($sql);
    // Log: Respuestas del usuario
    file_put_contents($debug_file_path, "User Responses: " . print_r($user_responses, true) . "\n", FILE_APPEND);

    foreach ($user_responses as $response) {
        // Paso 3: Obtener la pregunta relacionada con esta respuesta
        $sql = $wpdb->prepare("SELECT title, question_text, extra FROM 7c_survey_questions WHERE question_id = %d", $response->question_id);
        $question_info = $wpdb->get_row($sql);
        // Log: Información de la pregunta
        file_put_contents($debug_file_path, "Question Info for question_id " . $response->question_id . ": " . print_r($question_info, true) . "\n", FILE_APPEND);

        // Paso 4: Construir y enviar el mensaje (Ya no es necesario)
        // [Código para construir y enviar el mensaje]
        // Log: Mensaje construido
        // [Código para log del mensaje construido]
        // send_generic_message($message, $conversation_id);
    }

    // Log: Fin de la función
    file_put_contents($debug_file_path, "\n**************** END Function temp_survey_report ***************\n\n", FILE_APPEND);
    */
}





?>