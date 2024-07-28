<?php
// /home/<account>/public_html/wp-content/plugins/7-ktz-basic-surveys-and-microlearning/includes/search_engine_functions.php

// Llamar al archivo config.php
require_once __DIR__ . '/../config.php';


//*********************************************************************************************************//
//*********************************************************************************************************//
//********************************* FUNCIONES DEL FORMULARIO DEL BUSCADOR *********************************//
//*********************************************************************************************************//
//*********************************************************************************************************//


/**
 * Función que procesa la cadena de búsqueda proporcionada, filtrando y devolviendo solo las palabras que tienen 4 o más caracteres.
 *
 * Esta función realiza las siguientes operaciones:
 * 1. Convierte toda la cadena de búsqueda a letras minúsculas para asegurar la consistencia en el procesamiento posterior.
 * 2. Elimina caracteres especiales y numéricos para quedarse solo con palabras alfanuméricas.
 * 3. Divide la cadena procesada en palabras individuales.
 * 4. Filtra las palabras para retener solo aquellas que tienen una longitud de 4 caracteres o más.
 * 5. Devuelve las palabras filtradas como un array.
 *
 * @param string $raw_search_string La cadena de búsqueda original proporcionada por el usuario.
 * @return array $filtered_words Un array de palabras filtradas que tienen 4 o más caracteres.
 */
function process_and_search_surveys($raw_search_string) {
    // echo 'Debug: Ingresando a process_and_search_surveys<br>';
    // echo "Debug: raw_search_string: $raw_search_string<br>";
    
    // Convertir la cadena a minúsculas
    $raw_search_string = strtolower($raw_search_string);
    
    // Eliminar caracteres especiales y numéricos
    $raw_search_string = preg_replace('/[^a-z0-9\s]/i', '', $raw_search_string);
    
    // Dividir la cadena de búsqueda en palabras
    $words = explode(' ', $raw_search_string);
    
    // Filtrar palabras que tengan menos de 4 caracteres
    $filtered_words = array_filter($words, function($word) {
        return strlen($word) >= 4;
    });
    
    // echo 'Debug: Palabras filtradas: <pre>';
    // print_r($filtered_words);
    // echo '</pre><br>';
    ;
    
    return $filtered_words;
}



/**
 * Función principal que gestiona el proceso de búsqueda.
 *
 * Esta función es el punto de entrada para el sistema de búsqueda. Comienza procesando la cadena de búsqueda recibida, 
 * luego invoca una serie de funciones de búsqueda y finalmente devuelve una lista de user_id que cumplen con los criterios 
 * de búsqueda y tienen un número de WhatsApp válido.
 *
 * Pasos que realiza la función:
 * 1. Procesa la cadena de búsqueda con la función `process_and_search_surveys`, lo que da como resultado palabras que tienen 4 o más caracteres.
 * 2. Para cada palabra procesada, llama a una serie de funciones de búsqueda y almacena sus resultados.
 * 3. Combina todos los resultados y elimina los duplicados.
 * 4. Aplica filtros adicionales y restricciones, en caso de que se hayan definido, para refinar los resultados.
 * 5. Valida si cada user_id resultante tiene asociado un número de WhatsApp válido.
 * 6. (Opcional) Obtiene información adicional del usuario.
 * 7. Devuelve una lista de user_id válidos o información adicional del usuario, dependiendo de las necesidades.
 *
 * @param string $searchString La cadena de búsqueda proporcionada por el usuario.
 * @param array $statusFilter (Opcional) Una lista de estados para filtrar los resultados.
 * @return array $user_ids_valid_whatsapp Una lista de user_id que cumplen con los criterios de búsqueda y tienen un número de WhatsApp válido.
 */
function main_search_function($searchString, $statusFilter = [], $bookingStatusFilter = []) {
    
    // Inicializar la variable que almacenará los resultados
    $all_results = [];

    if (empty($searchString) && (!empty($statusFilter) || !empty($bookingStatusFilter))) {
        // Si la cadena de búsqueda está vacía pero hay filtros

        $user_ids_from_bookings = search_booking_filter_return_all_results_by_status($bookingStatusFilter);
        $user_ids_from_surveys = search_surveys_filter_return_all_results_by_status($statusFilter);

        $all_results = array_merge($user_ids_from_bookings, $user_ids_from_surveys);
        $all_results = array_unique($all_results);

    } else {
        // Si la cadena de búsqueda no está vacía

        error_log("[LOG] Cadena de búsqueda: " . $searchString);
        error_log("[LOG] Filtro de Estado de Menús y Encuestas recibido en main_search_function: " . json_encode($statusFilter));

        $processed_strings = process_and_search_surveys($searchString);
        error_log("[LOG] Cadenas procesadas para búsqueda: " . json_encode($processed_strings));

        foreach($processed_strings as $string) {
            $all_results = array_merge($all_results, search_surveys($string, $statusFilter));
            $all_results = array_merge($all_results, search_match_users_data($string));
            $all_results = array_merge($all_results, search_on_sb_conversations($string));
            $all_results = array_merge($all_results, search_on_sb_users($string));
            $all_results = array_merge($all_results, search_bravo_bookings($string, $statusFilter));
        }
        
        error_log("[LOG] User IDs encontrados hasta ahora: " . json_encode($all_results));
        $all_results = array_unique($all_results);

        if (!empty($bookingStatusFilter)) {
            $all_results = search_verify_if_user_has_status_in_bravo_bookings($all_results, $bookingStatusFilter);
        }
        
        error_log("[LOG] User IDs después de verificar el estado en bravo_bookings: " . json_encode($all_results));
        $all_results = validate_search_filters_and_restrictions($all_results, $statusFilter);
        error_log("[LOG] User IDs después de verificar el estado en la tabla de surveys activas: " . json_encode($all_results));
    }

    // Esta parte es común para ambos caminos
    $user_ids_valid_whatsapp = validate_if_user_id_has_a_valid_phone_whatsapp($all_results);
    error_log("[LOG] User IDs con número de WhatsApp válido: " . json_encode($user_ids_valid_whatsapp));

    // Recuperar información detallada del usuario, si es necesario
    $user_information = get_user_information($user_ids_valid_whatsapp);
    
    return $user_ids_valid_whatsapp; // O $user_information, según las necesidades
}







//*********************************************************************************************************//
//*********************************************************************************************************//
//********************************* FUNCIONES DE OPERACIONES DE BUSQUEDA **********************************//
//*********************************************************************************************************//
//*********************************************************************************************************//


//*********************************************************************************************************//
//************************** FUNCIONES AUXILIARES O LLAMADAS POR OTRAS FUNCIONES **************************//
//*********************************************************************************************************//

/**
 * Convierte un conversation_id en user_id.
 * 
 * Esta función toma un conversation_id como entrada y busca en la base de datos de WordPress 
 * para encontrar el user_id asociado en la tabla sb_conversations. 
 * La función es modular y puede ser utilizada por cualquier módulo del sistema.
 * Si se encuentra una coincidencia válida, devuelve el user_id correspondiente.
 * Si no encuentra una coincidencia, devuelve NULL.
 *
 * @param  int $conversation_id El ID de la conversación que debe buscarse.
 * @return int|null El user_id asociado a la conversación o NULL si no se encuentra una coincidencia.
 */
function search_match_conversation_id_to_user_id($conversation_id) {
    global $wpdb; // Objeto de base de datos de WordPress
    
    // Sanear el conversation_id para prevenir inyecciones SQL
    $conversation_id = (int) $conversation_id; // Asegurándose de que es un número entero
    
    // Construir la consulta SQL
    $sql = "SELECT user_id 
            FROM sb_conversations 
            WHERE id = $conversation_id";
    
    // Ejecutar la consulta y obtener el resultado
    $user_id = $wpdb->get_var($sql); // get_var retorna un solo valor de la consulta
    
    return $user_id; // Devolver el user_id encontrado, o NULL si no se encuentra ningún resultado
}


/**
 * Busca el número de WhatsApp asociado a un user_id.
 * 
 * Esta función toma un user_id como entrada y busca en la base de datos de WordPress 
 * para encontrar un número de WhatsApp (o telefónico) asociado a dicho user_id.
 * La función devuelve un array asociativo que contiene el user_id y el whatsapp_number
 * si encuentra una coincidencia válida en la tabla sb_users_data. 
 * Si no encuentra una coincidencia, devuelve false.
 *
 * @param  int $user_id El ID del usuario que debe buscarse.
 * @return array|false Un array asociativo con user_id y whatsapp_number si se encuentra una coincidencia, o false si no se encuentra.
 */
 function search_for_whatsapp_number($user_id) {
    global $wpdb; // Objeto de base de datos de WordPress
    
    // Sanear el user_id para prevenir inyecciones SQL
    $user_id = (int) $user_id; // Asegurándose de que es un número entero
    
    // Construir la consulta SQL
    $sql = "SELECT user_id, value as whatsapp_number
            FROM sb_users_data
            WHERE user_id = $user_id 
                  AND name = 'Phone'
                  AND value REGEXP '^[+]\\\\d{7,}$'";
    
    // Ejecutar la consulta y obtener el resultado
    $result = $wpdb->get_row($sql, ARRAY_A); // get_row retorna un solo registro como un array asociativo
    
    if($result) {
        return $result; // Retorna un array asociativo con user_id y whatsapp_number si se encuentra una coincidencia
    } else {
        return false; // Retorna false si no se encuentra una coincidencia
    }
}


/**
 * Valida si los user_id proporcionados tienen un número de WhatsApp válido.
 * 
 * Esta función toma una lista de user_ids y verifica si cada uno tiene asociado 
 * un número de WhatsApp válido en la base de datos de WordPress. 
 * La función devuelve una lista de user_ids que tienen un número de WhatsApp válido.
 * Si no encuentra números válidos para ningún user_id, devuelve un array vacío.
 *
 * @param  array $user_ids Lista de user_ids a validar.
 * @return array Lista de user_ids que tienen un número de WhatsApp válido.
 */
function validate_if_user_id_has_a_valid_phone_whatsapp($user_ids) {
    global $wpdb; // Objeto de base de datos de WordPress
    
    $user_ids_valid_whatsapp = [];
    
    if (empty($user_ids)) {
        return $user_ids_valid_whatsapp; // Si no hay user_ids, retorna un array vacío
    }

    // Asegurarse de que todos los IDs sean números enteros y remover valores no válidos
    $filtered_user_ids = array_filter($user_ids, function($id) {
        return is_numeric($id) && $id > 0;
    });
    
    if (empty($filtered_user_ids)) {
        return $user_ids_valid_whatsapp; // Si después de filtrar, no hay user_ids válidos, retorna un array vacío
    }

    $ids_string = implode(",", $filtered_user_ids);

    $sql = "SELECT DISTINCT ud.user_id
            FROM sb_users_data ud
            INNER JOIN sb_users u ON u.id = ud.user_id
            WHERE ud.name = 'Phone'
            AND ud.value REGEXP '^[+]\\\\d{7,}$'
            AND ud.user_id IN ($ids_string)";
            
    $results = $wpdb->get_col($sql); // get_col retorna una columna de resultados

    if (!empty($results)) $user_ids_valid_whatsapp = $results;

    return $user_ids_valid_whatsapp; // Devolver los user_id que cumplen con el criterio
}




/**
 * Convert user_id(s) to conversation_id(s) from the sb_conversations table.
 *
 * @param  mixed $user_ids Either a single user_id or an array of user_ids.
 * @return array An array of matching conversation_ids.
 */
function search_match_user_id_to_conversation_id($user_ids) {
    global $wpdb;

    // Log inicial para ver los user_ids proporcionados
    error_log("[LOG - search_match_user_id_to_conversation_id] User IDs iniciales: " . json_encode($user_ids));

    $userIdsIn = implode(",", $user_ids);
    $sql = "SELECT user_id, id as conversation_id FROM sb_conversations WHERE user_id IN ($userIdsIn)";

    // Log de la consulta SQL
    error_log("[LOG - search_match_user_id_to_conversation_id] Consulta SQL: {$sql}");

    $results = $wpdb->get_results($sql, ARRAY_A);

    // Log para ver qué resultados retorna la consulta SQL
    error_log("[LOG - search_match_user_id_to_conversation_id] Resultados SQL: " . json_encode($results));

    $mapping = [];
    foreach($results as $row) {
        // Si el user_id ya existe en el mapeo, simplemente añade el nuevo conversation_id
        // Si no, crea un nuevo arreglo y añade el conversation_id
        if (isset($mapping[$row['user_id']])) {
            $mapping[$row['user_id']][] = $row['conversation_id'];
        } else {
            $mapping[$row['user_id']] = [$row['conversation_id']];
        }
    }

    // Log final para ver el mapeo de user_id a conversation_id
    error_log("[LOG - search_match_user_id_to_conversation_id] Mapeo final: " . json_encode($mapping));

    return $mapping;
}







/**
 * Función validate_search_filters_and_restrictions
 * 
 * Esta función tiene como objetivo finalizar y restringir el conjunto de resultados de una búsqueda de usuarios
 * basándose en filtros adicionales, como el estado de una conversación. Esencialmente, actúa como una capa adicional
 * de validación que depura un conjunto inicial de identificadores de usuario basándose en criterios más específicos.
 * 
 * Proceso:
 * 1. Verifica si se proporcionaron filtros de estado. Si no se proporcionó ningún filtro, devuelve los identificadores de usuario tal como están.
 * 2. Transforma cada identificador de usuario en su correspondiente identificador de conversación.
 * 3. Utiliza el identificador de conversación para verificar si ese usuario en particular cumple con los criterios de filtro de estado.
 * 4. Si el usuario cumple con el criterio, se agrega a la lista final de identificadores de usuario que se devolverán.
 * 
 * Input:
 * - $user_ids (array): Una lista de identificadores de usuarios que se obtuvieron de una búsqueda anterior.
 * - $statusFilter (array): Una lista de estados que se usarán para filtrar aún más los resultados. Por ejemplo, si solo estamos interesados en 
 *   usuarios cuyo estado de conversación es 'activo' o 'inactivo', $statusFilter podría ser ['activo', 'inactivo'].
 * 
 * Salida:
 * - (array) Una lista filtrada de identificadores de usuarios que no solo coinciden con la búsqueda original, sino que también cumplen con 
 *   los criterios de filtro de estado.
 * 
 * Nota:
 * Es importante entender que esta función no realiza la búsqueda inicial de usuarios. Actúa sobre un conjunto ya existente de identificadores 
 * de usuario y simplemente depura esa lista basándose en criterios adicionales.
 */
function validate_search_filters_and_restrictions($user_ids, $statusFilter) {
    global $wpdb;
    
    error_log("[LOG - validate_search_filters_and_restrictions] User IDs iniciales: " . json_encode($user_ids));
    error_log("[LOG - validate_search_filters_and_restrictions] Filtros de estado: " . json_encode($statusFilter));

    if (empty($statusFilter)) return $user_ids;

    $statusFilter = array_map('esc_sql', $statusFilter);
    $statusIn = implode("','", $statusFilter);

    $mapping = search_match_user_id_to_conversation_id($user_ids);
    error_log("[LOG - validate_search_filters_and_restrictions] Mapeo de user_id a conversation_id: " . json_encode($mapping));

    $filteredUserIds = [];
    foreach($mapping as $userId => $conversations) {
        foreach ($conversations as $conversation_id) {
            $sql = "SELECT conversation_id FROM 7c_survey_status WHERE conversation_id = '$conversation_id' AND status IN ('$statusIn')";
            error_log("[LOG - validate_search_filters_and_restrictions] Consulta SQL: {$sql}");
            
            if ($wpdb->get_var($sql)) {
                // Agrega el userId cada vez que se encuentra una coincidencia
                $filteredUserIds[] = $userId;
            }
        }
    }
    
    // Utilice array_unique para asegurarse de que no haya duplicados en $filteredUserIds
    $filteredUserIds = array_unique($filteredUserIds);
    
    error_log("[LOG - validate_search_filters_and_restrictions] User IDs después del filtrado: " . json_encode($filteredUserIds));
    
    return $filteredUserIds;
}






/**
 * Busca y asocia el identificador BBVA a un User ID de Support Board.
 *
 * Esta función tiene tres puntos de búsqueda principales:
 *   1) En la tabla mc_asociacion_whatsapp_clave_usuario o 7c_asociacion_whatsapp_clave_usuario
 *   2) En la tabla sb_messages
 *   3) En la tabla sb_conversations
 * En caso de discrepancias entre las tres búsquedas, se prioriza el resultado 
 * de mc_asociacion_whatsapp_clave_usuario o 7c_asociacion_whatsapp_clave_usuario.
 * 
 * @param  string $identifier El identificador BBVA que debe buscarse.
 * @return int|false El sb_user_id asociado o false si no se encuentra una coincidencia.
 */
function search_match_bbva_identifier_to_sb_user_id($identifier, $validator = null) {
    global $wpdb; // Objeto de base de datos de WordPress
    
    // Invocar la función para transformar y validar el identificador
    $identifier = transform_email_into_identifier_and_verify_it($identifier, $validator);
    if (!$identifier) {
        return false; // Si no es un identificador válido o no pasa la validación, regresar falso
    }

    // Sanear el identificador para prevenir inyecciones SQL
    $identifier = esc_sql($identifier);
    
    $priorityTable = $wpdb->get_var("SHOW TABLES LIKE '7c_asociacion_whatsapp_clave_usuario'") ? '7c_asociacion_whatsapp_clave_usuario' : 'mc_asociacion_whatsapp_clave_usuario';

    // 1) Buscar en mc_asociacion_whatsapp_clave_usuario o 7c_asociacion_whatsapp_clave_usuario
    $sql1 = "SELECT support_board_user_id as sb_user_id 
             FROM {$priorityTable} 
             WHERE clave_o_dni = '{$identifier}'";

    $result1 = $wpdb->get_col($sql1); // Retorna una lista con todos los sb_user_id que coincidan

    // 2) Buscar en sb_messages
    $sql2 = "SELECT user_id as sb_user_id 
             FROM sb_messages 
             WHERE message LIKE '%{$identifier}%'";

    $result2 = $wpdb->get_col($sql2); // Retorna una lista con todos los sb_user_id que coincidan

    // 3) Buscar en sb_conversations por tags
    $sql3 = "SELECT user_id as sb_user_id 
             FROM sb_conversations 
             WHERE tags LIKE '%{$identifier}%'";

    $result3 = $wpdb->get_col($sql3); // Retorna una lista con todos los sb_user_id que coincidan
    
    // Procesar resultados y determinar cuál usar
    $returnValue = null; // Valor de retorno por defecto
    if (!empty($result1)) {
        $returnValue = $result1[0];
    } elseif (!empty($result2) && !empty($result3)) {
        $lastMessage = $wpdb->get_var("SELECT id FROM sb_messages WHERE message LIKE '%{$identifier}%' ORDER BY id DESC LIMIT 1");
        $lastConversation = $wpdb->get_var("SELECT id FROM sb_conversations WHERE tags LIKE '%{$identifier}%' ORDER BY id DESC LIMIT 1");
        if ($lastMessage > $lastConversation) {
            $returnValue = $result2[0];
        } else {
            $returnValue = $result3[0];
        }
    } elseif (!empty($result2)) {
        $returnValue = $result2[0];
    } elseif (!empty($result3)) {
        $returnValue = $result3[0];
    } else {
        $returnValue = false; // No hay coincidencias
    }

    // Log final antes de devolver el valor
    return $returnValue;
}



/**
 * Transforma un email o un array de emails en un identificador o un array de identificadores y los verifica.
 * 
 * Esta función toma un email o un conjunto de emails como entrada, extrae la parte anterior a la última arroba (@)
 * de cada uno y la devuelve como identificador. Si se proporciona un validador opcional, la función
 * comprobará si cada identificador contiene esa cadena (sin distinguir entre mayúsculas y minúsculas).
 * Si algún input no tiene una arroba, se devuelve directamente.
 * En caso de recibir un array de emails, devolverá un array de identificadores. Si se proporciona un solo email,
 * se devolverá un único identificador.
 *
 * @param  string|array $email El email o los emails a transformar en identificador(es).
 * @param  string|null $validator Cadena opcional para validar el o los identificador(es).
 * @return string|array|false El identificador o los identificadores extraídos o false si el validador no coincide con alguno de ellos.
 */

function transform_email_into_identifier_and_verify_it($emails, $validator = null) {
    // Si el input no es un array, convertirlo en uno para unificar el proceso
    if (!is_array($emails)) {
        $emails = [$emails];
    }

    $identifiers = [];

    foreach ($emails as $email) {
        error_log('Inicio de transform_email_into_identifier_and_verify_it. Email inicial: ' . $email);

        // Verificar si email es una cadena
        if (!is_string($email)) {
            error_log('Error: Se esperaba una cadena para $email pero se recibió otro tipo: ' . gettype($email));
            continue; // Saltar a la siguiente iteración
        }

        // Encuentra la última arroba
        $lastAtPos = strrpos($email, '@');

        if ($lastAtPos === false) {
            // Si no hay arroba, considerar que el email ya está limpio y añadir al array de resultados
            $identifiers[] = $email;
        } else {
            // Extraer el identificador y añadir al array de resultados
            $identifiers[] = substr($email, 0, $lastAtPos);
        }

        error_log('Identificador extraído: ' . end($identifiers));

        // Si se proporciona un validador, comprobar si el identificador contiene la cadena del validador
        if ($validator !== null && stripos(end($identifiers), $validator) === false) {
            error_log('El validador no coincide con el identificador.');
            // Remover el último identificador agregado ya que no pasó la validación
            array_pop($identifiers);
        }
    }

    // Si solo se proporcionó un email como string, devolver un string. Si se proporcionó un array, devolver un array.
    return (count($emails) === 1) ? $identifiers[0] : $identifiers;
}





/**
 * Valida que los `user_ids` proporcionados tengan una coincidencia en la columna `email` de la tabla `bravo_bookings` basándose en las claves o DNIs.
 *
 * @param array $user_ids Lista de IDs de usuario para validar.
 * @return array $validUserIds Lista de user_ids que tienen una coincidencia en `bravo_bookings`.
 */
function search_match_sb_user_id_to_bbva_identifier($user_ids) {
    global $wpdb;

    // Determinar qué tabla usar: 7c_asociacion_whatsapp_clave_usuario o mc_asociacion_whatsapp_clave_usuario.
    $priorityTable = $wpdb->get_var("SHOW TABLES LIKE '7c_asociacion_whatsapp_clave_usuario'") ? '7c_asociacion_whatsapp_clave_usuario' : 'mc_asociacion_whatsapp_clave_usuario';

    $validUserIds = [];
    foreach($user_ids as $user_id) {
        $clave = $wpdb->get_var("SELECT clave_o_dni FROM {$priorityTable} WHERE support_board_user_id = '$user_id'");
        $sql = "SELECT email FROM bravo_bookings WHERE email LIKE '%$clave%'";

        if ($wpdb->get_var($sql)) {
            $validUserIds[] = $user_id;
        }
    }

    return $validUserIds;
}



/**
 * Verifica si los `user_ids` proporcionados tienen un estado específico en la columna `status` de la tabla `bravo_bookings`.
 *
 * @param array $user_ids Lista de IDs de usuario para validar.
 * @param array $statusFilter Lista de estados para filtrar en `bravo_bookings`.
 * @return array $userIdsWithValidStatus Lista de user_ids que cumplen con el criterio de estado.
 */
function search_verify_if_user_has_status_in_bravo_bookings($user_ids, $bookingStatusFilter) {
    global $wpdb;
    
    $priorityTable = $wpdb->get_var("SHOW TABLES LIKE '7c_asociacion_whatsapp_clave_usuario'") ? '7c_asociacion_whatsapp_clave_usuario' : 'mc_asociacion_whatsapp_clave_usuario';

    error_log("[LOG - search_verify_if_user_has_status_in_bravo_bookings] Inicio de función.");

    $userIdsWithValidStatus = [];
    $statusIn = implode("','", array_map('esc_sql', $bookingStatusFilter));

    error_log("[LOG - search_verify_if_user_has_status_in_bravo_bookings] Estados a validar en bravo_bookings: [" . implode(", ", $bookingStatusFilter) . "]");

    $validUserIds = search_match_sb_user_id_to_bbva_identifier($user_ids);
    error_log("[LOG - search_verify_if_user_has_status_in_bravo_bookings] User IDs validados por email: [" . implode(", ", $validUserIds) . "]");

    foreach($validUserIds as $user_id) {
        $clave = $wpdb->get_var("SELECT clave_o_dni FROM {$priorityTable} WHERE support_board_user_id = '$user_id'");
        error_log("[LOG - search_verify_if_user_has_status_in_bravo_bookings] Clave extraída para user_id {$user_id}: {$clave}");

        $sql = "SELECT email, status FROM bravo_bookings WHERE email LIKE '%$clave%' AND status IN ('$statusIn')";
        error_log("[LOG - search_verify_if_user_has_status_in_bravo_bookings] Consulta SQL: {$sql}");

        $emails = $wpdb->get_results($sql, ARRAY_A);
        foreach ($emails as $email_row) {
            error_log("[LOG - search_verify_if_user_has_status_in_bravo_bookings] Email encontrado: {$email_row['email']} con estado: {$email_row['status']}");
        }

        if (count($emails) > 0) {
            $userIdsWithValidStatus[] = $user_id;
        }
    }

    error_log("[LOG - search_verify_if_user_has_status_in_bravo_bookings] IDs validados por estado: [" . implode(", ", $userIdsWithValidStatus) . "]");
    return $userIdsWithValidStatus;
}













//*********************************************************************************************************//
//*************************** FUNCIONES DE BÚSQUEDA POR TABLA O GRUPO DE TABLAS ***************************//
//*********************************************************************************************************//

// Función para buscar cualquier cadena de texto en la tabla de 7c_survey_surveys y encontrar una encuesta especifica y que me devuelva todos los conversation_id que tienen esa encuesta en el perfil
// Incluye la opción de agregar un selector para filtros adicionales (por ejemplo, si quiero solo encuestas activas)
// Finalmente llama a la función search_match_conversation_id_to_user_id para convertir estos conversation_id en user_id
function search_surveys($searchString, $statusFilter = []) {
    global $wpdb; // Objeto de base de datos de WordPress
    
    // Sanear la cadena de búsqueda para prevenir inyecciones SQL
    $searchString = esc_sql($wpdb->esc_like($searchString));
    
    // Construir la consulta SQL base
    $sql = "SELECT sstatus.conversation_id
            FROM 7c_survey_status sstatus
            INNER JOIN 7c_survey_surveys ssurveys
            ON sstatus.survey_id = ssurveys.survey_id
            WHERE (
                    ssurveys.survey_name LIKE '%$searchString%' 
                    OR ssurveys.header LIKE '%$searchString%' 
                    OR ssurveys.survey_message LIKE '%$searchString%' 
                    OR ssurveys.extra LIKE '%$searchString%'
                  )";
    
    // Si se proporcionaron filtros de estado, añadirlos a la consulta SQL
    if(!empty($statusFilter)) {
        $statusFilter = array_map('esc_sql', $statusFilter); // Sanear los valores del filtro
        $statusIn = implode("','", $statusFilter); // Convertir el array a una cadena para el SQL IN
        $sql .= " AND sstatus.status IN ('$statusIn')";
    }
    
    // Ejecutar la consulta y obtener los conversation_id
    $conversation_ids = $wpdb->get_col($sql);
    
    // Convertir los conversation_id a user_id
    $user_ids = [];
    foreach($conversation_ids as $conversation_id) {
        $user_id = search_match_conversation_id_to_user_id($conversation_id);
        if($user_id) $user_ids[] = $user_id; // Añadir el user_id al array solo si se encontró
    }
    
    return $user_ids; // Devolver los user_id encontrados
}


//*********************************************************************************************************//

// Función que busca en sb_users_data y devuelve el user_id cuando cumple la condición de búsqueda
function search_match_users_data($searchString) {
    global $wpdb; // Objeto de base de datos de WordPress
    
    // Sanear la cadena de búsqueda para prevenir inyecciones SQL
    $searchString = $wpdb->esc_like($searchString);
    
    // Construir la consulta SQL
    $sql = "SELECT user_id
            FROM sb_users_data
            WHERE slug LIKE '%$searchString%' 
               OR name LIKE '%$searchString%' 
               OR value LIKE '%$searchString%'";
    
    // Ejecutar la consulta y obtener los resultados
    $results = $wpdb->get_col($sql); // get_col retorna solo una columna de resultados, los user_id
    
    // Eliminar user_ids duplicados
    $results = array_unique($results);
    
    return $results; // Devolver los user_id encontrados
}








//*********************************************************************************************************//

// Función que busca coincidencias en sb_conversations, el input es el search string y la devolución es el user_id
function search_on_sb_conversations($searchString) {
    global $wpdb; // Objeto de base de datos de WordPress
    
    // Sanear la cadena de búsqueda para prevenir inyecciones SQL
    $searchString = esc_sql($wpdb->esc_like($searchString));
    
    // Construir la consulta SQL
    $sql = "SELECT user_id
            FROM sb_conversations
            WHERE source LIKE '%$searchString%'
               OR tags LIKE '%$searchString%'";
    
    // Ejecutar la consulta y obtener los resultados
    $user_ids = $wpdb->get_col($sql); // get_col retorna una columna de resultados, los user_ids en este caso
    
    return $user_ids; // Devolver los user_ids encontrados
}


//*********************************************************************************************************//

// Función que busca en la tabla de sb_users usando el searchstring y devuelve el user_id
function search_on_sb_users($searchString) {
    global $wpdb; // Objeto de base de datos de WordPress
    
    // Sanear la cadena de búsqueda para prevenir inyecciones SQL
    $searchString = esc_sql($wpdb->esc_like($searchString));
    
    // Construir la consulta SQL
    $sql = "SELECT id
            FROM sb_users
            WHERE user_type = 'user'
              AND (
                first_name LIKE '%$searchString%'
                OR last_name LIKE '%$searchString%'
                OR email LIKE '%$searchString%'
              )";
    
    // Ejecutar la consulta y obtener los resultados
    $user_ids = $wpdb->get_col($sql); // get_col retorna una columna de resultados, los id (user_ids) en este caso
    
    return $user_ids; // Devolver los user_ids encontrados
}


//*********************************************************************************************************//



/**
 * Busca coincidencias en la tabla bravo_bookings.
 * 
 * Esta función toma una cadena de búsqueda y busca coincidencias en varios campos 
 * de la tabla bravo_bookings. Luego, utiliza la función search_match_bbva_identifier_to_sb_user_id
 * para transformar los correos electrónicos encontrados en user_ids.
 *
 * @param  string $searchString Cadena de búsqueda.
 * @param  array $statusFilter Filtro opcional para el campo status.
 * @return array Lista de user_ids que coinciden con la búsqueda.
 */
function search_bravo_bookings($searchString, $statusFilter = []) {
    global $wpdb; // Objeto de base de datos de WordPress

    // Buscar en la tabla bravo_bookings
    $sql = "SELECT email 
            FROM bravo_bookings 
            WHERE object_model LIKE '%$searchString%' 
            OR status LIKE '%$searchString%'
            OR email LIKE '%$searchString%'
            OR first_name LIKE '%$searchString%'
            OR last_name LIKE '%$searchString%'
            OR phone LIKE '%$searchString%'";

    // Aplicar el filtro de status si se proporciona
    if (!empty($statusFilter)) {
        $statusList = "'" . implode("','", array_map('esc_sql', $statusFilter)) . "'";
        $sql .= " AND status IN ($statusList)";
    }

    $emails = $wpdb->get_col($sql);

    $user_ids = [];
    foreach ($emails as $email) {
        $user_id_from_email = search_match_bbva_identifier_to_sb_user_id($email);
        if ($user_id_from_email) {
            $user_ids[] = $user_id_from_email;
        }
    }

    return $user_ids;
}




/**
 * Busca y devuelve todos los user_ids en función del filtro de estado proporcionado para las reservas.
 *
 * @param array $bookingStatusFilter Una lista de estados de reservas para filtrar. 
 *                                   Ejemplo: ["paid", "pending", ...]
 *
 * @return array Una lista de user_ids que corresponden a las reservas que coinciden 
 *               con los estados proporcionados. Si no se encuentran resultados, 
 *               devolverá un array vacío.
 *
 * Descripción:
 * La función comienza construyendo una cadena de estados para la consulta SQL.
 * Luego, busca todos los emails en la tabla 'bravo_bookings' que coinciden con 
 * los estados proporcionados. Para cada email encontrado, busca el user_id correspondiente
 * y lo agrega a la lista de resultados.
 */
function search_booking_filter_return_all_results_by_status($bookingStatusFilter) {
    global $wpdb;

    $statusIn = implode("','", array_map('esc_sql', $bookingStatusFilter));
    $emails = $wpdb->get_col("SELECT email FROM bravo_bookings WHERE status IN ('$statusIn')");

    $user_ids = [];
    foreach ($emails as $email) {
        $user_ids[] = search_match_bbva_identifier_to_sb_user_id([$email]);
    }

    return $user_ids;
}



/**
 * Busca y devuelve todos los user_ids en función del filtro de estado proporcionado para las encuestas.
 *
 * @param array $statusFilter Una lista de estados de encuestas para filtrar.
 *                            Ejemplo: ["active", "completed", ...]
 *
 * @return array Una lista de user_ids que corresponden a las encuestas que coinciden 
 *               con los estados proporcionados. Si no se encuentran resultados, 
 *               devolverá un array vacío.
 *
 * Descripción:
 * La función comienza construyendo una cadena de estados para la consulta SQL.
 * Luego, busca todos los conversation_ids en la tabla '7c_survey_status' que coinciden 
 * con los estados proporcionados. Para cada conversation_id encontrado, busca el user_id 
 * correspondiente y lo agrega a la lista de resultados.
 */
function search_surveys_filter_return_all_results_by_status($statusFilter) {
    global $wpdb;

    $statusIn = implode("','", array_map('esc_sql', $statusFilter));
    $conversation_ids = $wpdb->get_col("SELECT conversation_id FROM 7c_survey_status WHERE status IN ('$statusIn')");

    $user_ids = [];
    foreach ($conversation_ids as $conversation_id) {
        $user_ids[] = search_match_conversation_id_to_user_id($conversation_id);
    }

    return $user_ids;
}



//*********************************************************************************************************//
//*********************************************************************************************************//
//********************************* FUNCIONES PARA LA DEVOLUCIÓN DE DATOS *********************************//
//*********************************************************************************************************//
//*********************************************************************************************************//

//Función que devuelve los datos de los usuarios cuando la alimentas con los user_id, incluye nombre, apellido, whatsapp y email
function get_user_information($user_ids) {
    global $wpdb; // Objeto de base de datos de WordPress
    
    // Convertir cada elemento del array a un entero para prevenir inyecciones SQL
    $user_ids = array_map('intval', $user_ids);
    
    // Convertir el array a una cadena para usar en la cláusula IN de SQL
    $user_ids_string = implode(', ', $user_ids);
    
    // Si la cadena está vacía, retornar un array vacío
    if(empty($user_ids_string)) return [];
    
    // Construir la consulta SQL
    // permite cualquier caracter no numérico después del '+'
    $sql = "SELECT 
                u.id AS user_id,
                u.first_name,
                u.last_name,
                u.email,
                ud.value AS whatsapp_number
            FROM sb_users u
            INNER JOIN sb_users_data ud 
            ON u.id = ud.user_id 
            AND ud.name = 'Phone' 
            AND ud.value REGEXP '^[+]\\\\d{7,}$' 
            WHERE u.id IN ($user_ids_string)";
    
    // Ejecutar la consulta y obtener el resultado
    $results = $wpdb->get_results($sql, ARRAY_A); // get_results retorna todos los registros como un array de arrays asociativos
    
    return $results; // Devolver la información de los usuarios encontrados
}

