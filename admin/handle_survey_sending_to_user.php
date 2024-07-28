<?php
// Llamar al archivo config.php
require_once __DIR__ . '/../config.php';

// Now you can use your constants
require_once SUPPORT_FUNCTIONS_FILE;

// Correct way to include send_messages_to_sb_wa_others.php using MY_PLUGIN_PATH constant
require_once MY_PLUGIN_PATH . 'includes/send_messages_to_sb_wa_others.php';


// Renderiza el formulario para enviar la encuesta
function render_survey_sending_form($users, $surveys) {
    echo '<form method="post" action="">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="selected_user">Select User</label></th>
                        <td>
                            <select name="selected_user" id="selected_user">';
    foreach ($users as $user) {
        echo "<option value='{$user->conversation_id}'>{$user->first_name} {$user->last_name} ({$user->phone})</option>";
    }
    echo '              </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="selected_survey">Select Survey</label></th>
                        <td>
                            <select name="selected_survey" id="selected_survey">';
    foreach ($surveys as $survey) {
        echo "<option value='{$survey->survey_id}'>{$survey->survey_name}</option>";
    }
    echo '              </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <input type="submit" name="send_survey" class="button button-primary" value="Send Survey">
            </p>
          </form>';
}


// Maneja el envío de la encuesta
function handle_send_survey($selected_user, $selected_survey, $wpdb) {
    $current_status = $wpdb->get_row("SELECT current_question_id, status FROM 7c_survey_status WHERE conversation_id = $selected_user AND survey_id = $selected_survey AND status = 'active' LIMIT 1");

    if ($current_status) { // Si hay un estado actual y está 'active', entonces procede.
        $current_question_id = $current_status->current_question_id;
        $current_question = $wpdb->get_row("SELECT * FROM 7c_survey_questions WHERE question_id = $current_question_id LIMIT 1");
        
        if ($current_question) {
            error_log("Question Media URL in handle_send_survey: " . $current_question->media_url);
            $current_question_text = construct_message($current_question, 'question');
            $GLOBALS['SB_FORCE_ADMIN'] = true;
            $send_status_current_question = sb_messaging_platforms_send_message($current_question_text, $selected_user);
            $GLOBALS['SB_FORCE_ADMIN'] = false;
            
            if ($send_status_current_question) {
                sleep(2);
                $button_message = construct_buttons($current_question_id, $wpdb);
                $GLOBALS['SB_FORCE_ADMIN'] = true;
                $send_status_buttons = sb_messaging_platforms_send_message($button_message, $selected_user);
                $GLOBALS['SB_FORCE_ADMIN'] = false;
                
                if (!$send_status_buttons) {
                    echo '<div class="notice notice-error is-dismissible"><p><strong>Error: Could not send buttons for the current question.</strong></p></div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible"><p><strong>Error: Could not send the current question.</strong></p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p><strong>Error: Could not find the current question.</strong></p></div>';
        }
    }
}





// Función para construir el mensaje basado en los datos de la encuesta
function construct_message($data, $message_type) {
    $message = "";
    
    if ($message_type === 'survey') {
        $header = $data->header;
        $description = $data->survey_message;
        $media_url = $data->image; // Aquí asumo que la tabla '7c_survey_surveys' también tiene un campo 'media_url'. Ajusta si es necesario.
        $extra = $data->extra;
        
        // Aquí puedes agregar condiciones específicas para construir mensajes de tipo 'survey'.
        if ($header && $description) {
            $message = "[card";
            if ($media_url) {
                $message .= " image=\"$media_url\"";
            }
            $message .= " header=\"$header\" description=\"$description\"";
            if ($extra) {
                $message .= " extra=\"$extra\"";
            }
            $message .= "]";
        } elseif ($media_url) {
            $message = "[image url=\"$media_url\"]";
        } else {
            $message = $description;
        }
    } 
    elseif ($message_type === 'question') {
        $header = $data->title;
        $description = $data->question_text;
        $media_url = $data->media_url;
        $extra = $data->extra;
        
        // Aquí puedes agregar condiciones específicas para construir mensajes de tipo 'question'.
        if ($header && $description) {
            $message = "[card";
            if ($media_url) {
                $message .= " image=\"$media_url\"";
            }
            $message .= " header=\"$header\" description=\"$description\"";
            if ($extra) {
                $message .= " extra=\"$extra\"";
            }
            $message .= "]";
        } elseif ($media_url) {
            $message = "[image url=\"$media_url\"]";
        } else {
            $message = $description;
        }
    } 
    else {
        $message = "Formato no reconocido";
    }
    error_log("Constructed Message: $message");
    return $message;
}



// Función para construir la botonera de respuestas
function construct_buttons($question_id, $wpdb) {
    $answers = $wpdb->get_results("SELECT option_number, answer_text FROM 7c_possible_answers WHERE question_id = $question_id ORDER BY option_number ASC");
    $options_array = array();
    foreach ($answers as $answer) {
        $options_array[] = "$answer->option_number $answer->answer_text";
    }
    $options_string = implode(", ", $options_array);
    return "[buttons id=\"NPC parte $question_id\" message=\"Seleccione una opción\" options=\"$options_string\"]";
}





















// Muestra las opciones cuando una encuesta ya está activa
function show_active_survey_options($selected_user, $selected_survey) {
    echo '<div class="notice notice-warning is-dismissible">
            <p><strong>Ya hay una encuesta activa sobre esta conversación. ¿Qué te gustaría hacer?</strong></p>
            <form method="post" action="">
                <input type="hidden" name="selected_user" value="' . $selected_user . '">
                <input type="hidden" name="selected_survey" value="' . $selected_survey . '">
                <input type="submit" name="set_to_abandoned" class="button" value="Set to Abandoned and Start New Survey">
                <input type="submit" name="send_remainder" class="button" value="Send a Remainder">
                <input type="submit" name="cancel" class="button" value="Cancel">
            </form>
          </div>';
}




// Función que inicia el proceso de una encuesta o menu a un usuario
function render_send_survey_to_user() {
    global $wpdb;

    // Obtén todos los usuarios y sus detalles
    $query = "
        SELECT c.id AS conversation_id, c.user_id, u.first_name, u.last_name, ud.value AS phone
        FROM sb_conversations AS c
        JOIN sb_users AS u ON c.user_id = u.id
        JOIN sb_users_data AS ud ON ud.user_id = u.id AND ud.name = 'Phone'
    ";
    $users = $wpdb->get_results($query);

    // Obtén todos los surveys disponibles
    $surveys = $wpdb->get_results("SELECT * FROM 7c_survey_surveys");

    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">Send Survey to User</h1>';

    render_survey_sending_form($users, $surveys);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $selected_user = intval($_POST['selected_user'] ?? 0);
        $selected_survey = intval($_POST['selected_survey'] ?? 0);
    
        if (isset($_POST['send_survey'])) {
            // Verificar si hay una encuesta activa
            $active_survey = $wpdb->get_row("SELECT * FROM 7c_survey_status WHERE conversation_id = $selected_user AND status = 'active'");
            if ($active_survey) {
                show_active_survey_options($selected_user, $selected_survey);
            } else {
                start_new_survey($selected_user, $selected_survey, $wpdb); // Llamada a la nueva función aquí
            }
        }

        if (isset($_POST['set_to_abandoned'])) {
            // Marcar la encuesta activa como "abandoned"
            $wpdb->update(
                '7c_survey_status',
                array('status' => 'abandoned'),
                array('conversation_id' => $selected_user, 'status' => 'active')
            );
            start_new_survey($selected_user, $selected_survey, $wpdb); // Llamada a la nueva función aquí también
        }

        if (isset($_POST['send_remainder'])) {
            // TODO: Implementar el envío del recordatorio aquí.
            echo '<div class="notice notice-info is-dismissible">
                    <p><strong>Reminder to be sent. (To be implemented)</strong></p>
                  </div>';
        }

        if (isset($_POST['cancel'])) {
            // No hacer nada, simplemente volver al formulario
        }
    }

    echo '</div>'; // Fin del div wrap
}


// Función que envía una nueva encuesta o menu en el caso de que el usuario no tenga una activa o se fuerce el reinicio
function start_new_survey($selected_user, $selected_survey, $wpdb) {
    // Obtén todos los question_id de la encuesta seleccionada.
    $results = $wpdb->get_results("SELECT question_id FROM 7c_survey_questions WHERE survey_id = $selected_survey");
    
    // Si hay resultados, encuentra el valor mínimo de question_id.
    if ($results) {
        $min_question_id = min(array_column($results, 'question_id'));
        
        // Si no hay una encuesta activa, crea una nueva entrada en la tabla '7c_survey_status' con "status" = "active" y el 'current_question_id' adecuado
        $wpdb->insert(
            '7c_survey_status',
            array(
                'conversation_id' => $selected_user,
                'survey_id' => $selected_survey,
                'current_question_id' => $min_question_id,
                'status' => 'active' 
            )
        );

        $survey_data = $wpdb->get_row("SELECT * FROM 7c_survey_surveys WHERE survey_id = $selected_survey LIMIT 1");
        error_log("Survey Media URL in start_new_survey: " . $survey_data->image);
        $message_to_send = construct_message($survey_data, 'survey');
        
        // Ahora, procede a enviar el mensaje de la encuesta.
        $GLOBALS['SB_FORCE_ADMIN'] = true;
        $send_status1 = sb_messaging_platforms_send_message($message_to_send, $selected_user);
        $GLOBALS['SB_FORCE_ADMIN'] = false;
        sleep(2);
        
        if ($send_status1) {
            // Insertar la etiqueta de que este usuario ha recibido un survey o menu
            update_tags_on_new_sent_survey($selected_user, $selected_survey, $wpdb);
 
            // Obtén el nombre de la encuesta.
            $survey = $wpdb->get_row($wpdb->prepare("SELECT * FROM 7c_survey_surveys WHERE survey_id = %d", $selected_survey));
            $survey_name = $survey ? $survey->survey_name : 'Encuesta desconocida';
                
            // Construye el mensaje para el chat del help desk.
            $helpdesk_message = "Se inició la encuesta $survey_name";
                
                // Verifica si el archivo existe antes de requerirlo
                $file_path = MY_PLUGIN_PATH . 'includes/send_messages_to_sb_wa_others.php';
                if (file_exists($file_path)) {
                    require_once($file_path);
        
                    // Verifica si la función existe antes de llamarla
                    /*if (function_exists('send_generic_message')) { // Comentado temporalmente
                        $result = send_generic_message($helpdesk_message, $selected_user); // Asegúrate de que $helpdesk_conversation_id esté definido y sea correcto
                        if (!$result) {
                            error_log('Error: No se pudo enviar el mensaje al help desk.');
                        }
                    } else {
                        error_log('Error: La función send_generic_message no existe.');
                    }*/
                } else {
                    error_log('Error: El archivo send_messages_to_sb_wa_others.php no existe en la ruta especificada.');
                }
        
                // Continúa con el proceso de envío de la encuesta.
            handle_send_survey($selected_user, $selected_survey, $wpdb);
        } else {
            echo '<div class="notice notice-error is-dismissible"><p><strong>Error: No se pudo enviar el mensaje de la encuesta.</strong></p></div>';
        }
    } else {
        echo '<div class="notice notice-error is-dismissible"><p><strong>Error: No hay preguntas para la encuesta seleccionada.</strong></p></div>';
    }
}




// Función que agrega los tags a las conversaciones en los casos en los que se active una encuesta o formulario
function update_tags_on_new_sent_survey($conversation_id, $survey_id, $wpdb) {
    // Paso 2: Obtener survey_name de 7c_survey_surveys donde survey_id es igual al proporcionado
    $survey = $wpdb->get_row($wpdb->prepare("SELECT * FROM 7c_survey_surveys WHERE survey_id = %d", $survey_id));
    if(!$survey) {
        error_log('Error: No se pudo obtener el survey_name porque no existe un survey con el ID proporcionado.');
        return;
    }
    $survey_name = $survey->survey_name;
    
    // Paso 3: Revisar y actualizar (si es necesario) tags en sb_settings
    $tags_row = $wpdb->get_row("SELECT * FROM sb_settings WHERE name = 'tags'");
    if($tags_row) {
        $tags = json_decode($tags_row->value, true);
        if(!in_array($survey_name, $tags)) {
            // 3.1: Agregar el nuevo tag si no existe
            $tags[] = $survey_name;
            $updated_tags = json_encode($tags);
            $wpdb->update('sb_settings', array('value' => $updated_tags), array('name' => 'tags'));
        }
    } else {
        error_log('Error: No se encuentra la fila de tags en sb_settings.');
        return;
    }
    
    // Paso 4: Actualizar tags en sb_conversations
    $conversation_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM sb_conversations WHERE id = %d", $conversation_id));
    if($conversation_row) {
        $conversation_tags = explode(',', $conversation_row->tags);
        if(!in_array($survey_name, $conversation_tags)) {
            // 4.2: Agregar el nuevo tag si no existe
            $conversation_tags[] = $survey_name;
            $updated_conversation_tags = implode(',', $conversation_tags);
            $wpdb->update('sb_conversations', array('tags' => $updated_conversation_tags), array('id' => $conversation_id));
        }
    } else {
        error_log('Error: No se encuentra la fila de conversación en sb_conversations.');
        return;
    }
}





// Funcion que envia "mensajes" pero a un archivo de texto (es demo hasta que funcione)
function write_messages_to_file($current_question_id, $survey_message, $file_path) {
    global $wpdb;

    // Obtener el texto de la pregunta actual de la base de datos
    $query = "SELECT question_text FROM 7c_survey_questions WHERE question_id = $current_question_id LIMIT 1";
    $result = $wpdb->get_row($query);
    $question_text = $result->question_text ?? 'Unknown Question';

    // Escribir los mensajes en el archivo
    $file = fopen($file_path, 'a');
    if ($file) {
        fwrite($file, "Survey Message: $survey_message\n");
        fwrite($file, "Question: $question_text\n");
        fclose($file);
    } else {
        echo '<div class="notice notice-error is-dismissible">
                <p><strong>Error: Could not write to the file.</strong></p>
              </div>';
    }
}

?>