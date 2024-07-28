<?php
// File to send WA messages through Board Support
require_once __DIR__ . '/send_messages_to_sb_wa_others.php';


// Función para agregar un +1 en la tabla Table: mc_validador_en_comercio_whatsapp_numbers para saber cuantas consultas hizo cada numero de whatsapp
function incrementQueryCount($phoneNumber) {
    global $wpdb;

    // Preparar la consulta para incrementar el contador de consultas
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE mc_validador_en_comercio_whatsapp_numbers SET query_count = query_count + 1 WHERE whatsapp_number = %s",
            $phoneNumber
        )
    );
}




/**
 * Verifies new messages to determine if they are authorized and processes them accordingly.
 *
 * This function acts as a central point for handling incoming messages, determining their
 * authorization status, and initiating appropriate actions based on that status. It performs
 * several key operations as part of its workflow:
 * 
 * 1. Authorization Check:
 *    It starts by calling `checkMessageAuthorization` with the message ID to determine if the
 *    sender's phone number is authorized to make requests. This check involves verifying if
 *    the phone number associated with the message ID is listed as approved in the database.
 * 
 * 2. Fetching User Details:
 *    If the message is authorized, the function fetches the sender's user ID and phone number
 *    from the database. These details are crucial for further processing and logging.
 * 
 * 3. Recovering Conversation ID:
 *    The function attempts to recover the conversation ID associated with the message. This ID
 *    is essential for sending responses back to the correct WhatsApp conversation.
 * 
 * 4. Message Content Analysis:
 *    With the conversation ID successfully retrieved, the function proceeds to analyze the message
 *    content by calling `analyzeMessageContent`. This step involves categorizing the message based
 *    on its content (e.g., checking if it matches known patterns for DNI or CE queries) and
 *    taking appropriate actions based on the analysis (e.g., verification against the database).
 * 
 * 5. Incrementing Query Count:
 *    For each authorized message, the function increments a query count for the sender's phone
 *    number, keeping track of how many requests each number has made. This is useful for monitoring
 *    and potentially limiting the number of requests per number.
 * 
 * 6. Handling Unauthorized Messages:
 *    If a message is found to be unauthorized, the function retrieves the conversation ID (if
 *    available) and sends a generic message indicating that the number is not authorized to make
 *    requests. This ensures that senders of unauthorized messages receive feedback about their
 *    request status.
 * 
 * Error Handling:
 *    The function includes error handling for scenarios where the conversation ID cannot be
 *    retrieved, logging errors to help with debugging and monitoring.
 * 
 * This function is a critical component of the message verification and processing workflow,
 * ensuring that only authorized requests are processed and that all interactions are logged
 * and accounted for. It exemplifies a comprehensive approach to handling, analyzing, and responding
 * to messages in a system where authorization and content analysis are pivotal.
 *
 * @param int $messageId The ID of the incoming message to be verified and processed.
 */

// Example function definition within verify_new_messages_to_see_the_content.php
function verifyNewMessages($messageId) {
    global $wpdb;
    
    // Call the authorization check function for the message
    $isAuthorized = checkMessageAuthorization($messageId);

    if ($isAuthorized) {
        // Fetch user's phone number
        $userId = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM sb_messages WHERE id = %d", $messageId));
        $phoneNumber = $wpdb->get_var($wpdb->prepare("SELECT value FROM sb_users_data WHERE user_id = %d AND name = 'Phone'", $userId));

        // Recover conversation_id
        $conversationId = recoverConversationId($messageId);

        // Proceed with message content analysis and pass the phone number and conversation_id
        if ($conversationId !== null) {
            analyzeMessageContent($messageId, $phoneNumber, $conversationId);
            incrementQueryCount($phoneNumber);

        } else {
            // Handle the case where conversation_id couldn't be retrieved
            error_log("Error: No se pudo recuperar el conversation_id para el messageId $messageId.");
        }
    } else {
        // Send unauthorized message scenario
        $conversationId = recoverConversationId($messageId); // Assuming you also want to send this message through SB/WhatsApp
        if ($conversationId !== null) {
            send_generic_message("Este número no está autorizado para hacer consultas", $conversationId);
        } else {
            // Handle the case where conversation_id couldn't be retrieved
            error_log("Error: No se pudo enviar el mensaje de no autorización porque no se pudo recuperar el conversation_id para el messageId $messageId.");
        }
    }
}






/**
 * Checks if a message is authorized based on the sender's phone number.
 *
 * Originally, this function was designed to verify if the sender's phone number is listed
 * as approved in the database, thereby determining if the sender is authorized to proceed
 * with certain actions, such as requesting DNI confirmation. It involved several steps:
 * 1. Fetching the user_id associated with the message ID.
 * 2. Retrieving the sender's phone number using the fetched user_id.
 * 3. Checking if the sender's phone number is marked as approved in the 'mc_validador_en_comercio_whatsapp_numbers' table.
 *
 * However, this behavior has been altered to streamline the process and bypass the phone number
 * approval verification. This was achieved by always returning `true` at the point where
 * approval verification would typically take place. The modification ensures that all senders
 * are considered authorized without actually checking their approval status in the database.
 * 
 * This change was implemented to deactivate the verification of numbers asking for DNI confirmation,
 * effectively allowing all numbers to proceed without the need for explicit approval. While this
 * modification simplifies the authorization logic under specific circumstances, it's crucial to
 * be aware that it skips an essential security check. The function still performs initial steps like
 * fetching the user_id and phone number, and it logs these details for auditing or debugging purposes.
 *
 * Important Modification:
 * - The actual approval check against the database (the part that queries the database to check if
 *   the phone number is approved) has been bypassed by directly returning `true`, regardless of
 *   the phone number's approval status in the database. This is a significant deviation from the
 *   function's original intent and should be revisited if the requirements change or if there's
 *   a need to reinstate the approval verification process in the future.
 *
 * @param int $messageId The ID of the message being checked for authorization.
 * @return bool Always returns true, indicating the message is authorized, as part of the
 *              temporary modification to bypass phone number approval checks.
 */

function checkMessageAuthorization($messageId) {
    global $wpdb;

    // Step 1: Get the user_id for the message
    $userId = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM sb_messages WHERE id = %d", $messageId));
    if (is_null($userId)) {
        logMessageAuthorization("Error: Message ID {$messageId} does not have an associated user or does not exist.");
        return false; // Keep this to handle cases where the messageId is invalid.
    }

    // Step 2: Fetch the user's phone number where name equals "Phone"
    $phoneNumber = $wpdb->get_var($wpdb->prepare("SELECT value FROM sb_users_data WHERE user_id = %d AND name = 'Phone'", $userId));
    if (empty($phoneNumber)) {
        logMessageAuthorization("Error: No phone number (matching 'Phone') found for user ID {$userId}.");
        return false; // Keep this to handle cases where there is no phone number associated.
    }

    // Log fetched phone number for audit or debug purposes
    logMessageAuthorization("Fetched phone number for user ID {$userId}: {$phoneNumber}");

    // Bypass the phone number approval check and always approve
    return true;
}




/**
 * Logs messages related to the authorization process to a dedicated log file.
 *
 * This helper function is designed to facilitate logging for the message authorization
 * process, making it easier to track, audit, and debug the flow of authorization checks.
 * Each log entry includes a timestamp and the specific message related to the authorization
 * process, providing a chronological record of events for review.
 *
 * The function writes to a log file named `message_authorization_log.txt`, located in the same
 * directory as this script. It appends each new log message at the end of the file, ensuring that
 * no existing data is overwritten, and that the log entries are preserved in the order they were added.
 *
 * Log Format:
 * Each entry in the log file is formatted as follows:
 * YYYY-MM-DD HH:MM:SS - [Log Message]
 * - YYYY-MM-DD HH:MM:SS represents the timestamp when the log entry was added, formatted in the
 *   standard year-month-day hour:minute:second format. This timestamp is crucial for understanding
 *   the sequence of events and troubleshooting issues based on when they occurred.
 * - [Log Message] is the specific message passed to the function, detailing the event or status
 *   related to message authorization that needs to be logged.
 *
 * Example Usage:
 * This function can be called with various messages throughout the authorization process, such as:
 * - Notifying when a message ID does not have an associated user or does not exist.
 * - Indicating when a fetched phone number is approved or not approved for proceeding with a request.
 * - Logging any errors or exceptional scenarios encountered during the process.
 *
 * By providing a centralized mechanism for logging authorization-related events, this function
 * aids in maintaining a clear and accessible record of the authorization logic's operation, making
 * it easier to monitor the system's behavior and address any issues that arise.
 *
 * @param string $logMessage The specific message to be logged, detailing the event or status in the
 *                           authorization process.
 */

// Helper function to log messages
function logMessageAuthorization($logMessage) {
    $logFilePath = __DIR__ . '/message_authorization_log.txt';
    file_put_contents($logFilePath, date("Y-m-d H:i:s") . " - " . $logMessage . "\n", FILE_APPEND);
}





/**
 * Analyzes the content of a WhatsApp message to determine the type of query it represents.
 *
 * This function categorizes messages based on their length and content, specifically focusing on numeric sequences that represent:
 * - A combination of a DNI number and the last 4 digits of a credit card (12 digits in total).
 * - A combination of a CE number and the last 4 digits of a credit card (13 digits in total).
 * - A standalone DNI number (8 digits).
 * - A standalone CE number (9 digits).
 *
 * Depending on the message content's length and numeric composition, the function sets an action type that indicates the nature of the query:
 * - `DNI_CARD_QUERY`: Indicates a query involving a DNI number combined with a 4-digit credit card sequence.
 * - `CE_CARD_QUERY`: Indicates a query involving a CE number combined with a 4-digit credit card sequence.
 * - `DNI_QUERY`: Indicates a query involving a standalone DNI number.
 * - `CE_QUERY`: Indicates a query involving a standalone CE number.
 * - `OTHER`: Indicates any message that does not match the above patterns, treated as an unrecognized query.
 *
 * After determining the type of query, the function logs the initial result ('POSITIVE' for recognized types, 'NEGATIVE' for 'OTHER') 
 * and proceeds with specific verification steps based on the query type. This includes calling different functions to handle the verification 
 * of DNI/CE numbers with or without credit card numbers, and logging the outcome.
 *
 * Modifications:
 * - Added checks for standalone DNI (8 digits) and CE (9 digits) numbers to accommodate new scenarios where these numbers are verified
 *   independently of any associated credit card numbers. This change addresses the introduction of a new database table 
 *   (`mc_validador_en_comercio_approved_dni_and_ce`) that stores these numbers separately, reflecting an expanded scope of verification
 *   to include not just combinations of identity numbers and credit card sequences but also the identity numbers on their own.
 *
 * @param int $messageId The ID of the message being analyzed, used to fetch the message content from the database.
 * @param string $phoneNumber The phone number of the sender, used for logging and verification purposes.
 * @param string $conversationId The conversation ID associated with the message, used for sending replies based on the verification outcome.
 */

function analyzeMessageContent($messageId, $phoneNumber, $conversationId) {
    global $wpdb;

    // Fetch the message content
    $messageContent = $wpdb->get_var($wpdb->prepare("SELECT message FROM sb_messages WHERE id = %d", $messageId));

    // Determine the type of query and its initial result
    if (preg_match('/^[a-zA-Z0-9]{11,14}$/', $messageContent)) {
        $action_type = 'DNI_CARD_QUERY';
    } elseif (preg_match('/^[a-zA-Z0-9]{11,14}$/', $messageContent)) {
        $action_type = 'CE_CARD_QUERY';
    } elseif (preg_match('/^[a-zA-Z0-9]{7,10}$/', $messageContent)) {
        $action_type = 'DNI_QUERY';
    } elseif (preg_match('/^[a-zA-Z0-9]{7,10}$/', $messageContent)) {
        $action_type = 'CE_QUERY';
    } else {
        $action_type = 'OTHER';
    }

    $result = ($action_type == 'OTHER') ? 'NEGATIVE' : 'POSITIVE';

    // Insert initial log and capture its ID
    $queryLogId = insertInitialQueryLog($phoneNumber, $action_type, $result, $messageId, $messageContent);

    // Adjust function calls to pass $queryLogId and $conversationId
    switch ($action_type) {
        case 'DNI_CARD_QUERY':
        case 'CE_CARD_QUERY':
            verifyIdentityAndCard($messageId, $messageContent, $phoneNumber, $queryLogId, $conversationId);
            break;
        case 'DNI_QUERY':
        case 'CE_QUERY':
            verifyDNIorCEOnly($messageId, $messageContent, $phoneNumber, $queryLogId, $conversationId);
            break;
        default:
            send_messages("Solo puedo verificar combinaciones de DNI o CE con los últimos 4 dígitos de tarjetas, o un DNI o CE solo. Por favor, vuelve a escribir con esos datos exclusivamente en el mensaje.", $conversationId);
            updateQueryLogResult($queryLogId, 'NEGATIVE');
            break;
    }
}







/**
 * Verifies a combined DNI or CE number with the last four digits of a credit card against the approved DNIs table.
 * 
 * This function checks if a given combination of an identification number (DNI/CE) and the last four digits of a credit card
 * exists in the `mc_validador_en_comercio_approved_dnis` table. The purpose is to validate whether such a combination has been
 * previously approved for further processing or actions. This check is crucial for operations requiring verified identity and
 * card information, ensuring that only authorized combinations proceed.
 * 
 * Parameters:
 * - $messageId (int): The ID of the message being processed, used for logging and tracking.
 * - $identityAndCardNumber (string): The combined DNI or CE number with the last four digits of a credit card.
 * - $telephoneNumber (string): The phone number associated with the message, used for sending responses and logging.
 * - $queryLogId (int): The ID of the initial query log entry, used for updating the log with the verification result.
 * - $conversationId (string): The ID of the WhatsApp conversation, used for sending verification results back to the user.
 * 
 * The function first determines the type of the combined number based on its length (12 for DNI, 13 for CE) and then checks
 * for its existence in the database. Depending on the verification outcome, it sends a corresponding message to the user and
 * updates the verification log and query log with the result.
 */

function verifyIdentityAndCard($messageId, $identityAndCardNumber, $telephoneNumber, $queryLogId, $conversationId) {
    global $wpdb;

    // Verificar si el número coincide en la tabla de DNIs aprobados (usada para ambos, DNI y CE, junto con CC)
    $matchCount = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM mc_validador_en_comercio_approved_dnis WHERE dni_number = %s", $identityAndCardNumber));

    // Determinar el tipo de consulta basado en la longitud del número
    $type = (strlen($identityAndCardNumber) >= 11 && strlen($identityAndCardNumber) <= 14) ? 'DNI + Tarjeta' : 'CE + Tarjeta';

    if ($matchCount > 0) {
        $message = "La combinación de {$type} $identityAndCardNumber está autorizada.";
        send_messages($message, $conversationId);
        insertVerificationLog($telephoneNumber, $type, 'VALIDATED');
    } else {
        $message = "La combinación de {$type} $identityAndCardNumber no está autorizada.";
        send_messages($message, $conversationId);
        insertVerificationLog($telephoneNumber, $type, 'NOT_VALIDATED');
        updateQueryLogResult($queryLogId, 'NEGATIVE');
    }
}





/**
 * Verifies a standalone DNI or CE number against the approved DNI and CE numbers table.
 * 
 * This function is responsible for checking if a provided DNI or CE number, without any associated credit card information,
 * is listed in the `mc_validador_en_comercio_approved_dni_and_ce` table. It's designed to support scenarios where only the
 * identity number needs to be verified, without the context of a credit card. This verification ensures that the provided
 * identity number is authorized for certain actions or processes within the system.
 * 
 * Parameters:
 * - $messageId (int): The ID of the message being processed. This is used for logging purposes and to associate the verification result with a specific message.
 * - $identityNumber (string): The DNI or CE number being verified. This can be either 8 digits (DNI) or 9 digits (CE).
 * - $telephoneNumber (string): The sender's phone number. This is used for identifying the user in logs and for sending back the verification results.
 * - $queryLogId (int): An identifier for the initial query log. This ID is used to update the log entry with the result of this verification.
 * - $conversationId (string): The WhatsApp conversation ID, which allows the system to send the verification result directly to the user's conversation.
 * 
 * Upon determining the identity number type based on its length, the function queries the database to check for approval status. If the number
 * is found in the approved list, a positive response is sent to the user, and the verification is logged as validated. Otherwise, a negative
 * response is issued, and the verification is logged as not validated, with the query log being updated to reflect a negative outcome.
 */

function verifyDNIorCEOnly($messageId, $identityNumber, $telephoneNumber, $queryLogId, $conversationId) {
    global $wpdb;

    // Check if the DNI/CE number exists in the newly added table
    $matchCount = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM mc_validador_en_comercio_approved_dni_and_ce WHERE dni_or_ce_number = %s",
        $identityNumber
    ));

    // Determine the type of query based on the length of the number
    $type = (strlen($identityNumber) >= 7 && strlen($identityNumber) <= 10) ? 'DNI' : 'CE';

    if ($matchCount > 0) {
        $message = "El número de {$type} {$identityNumber} está autorizado.";
        send_messages($message, $conversationId);
        insertVerificationLog($telephoneNumber, $type, 'VALIDATED');
    } else {
        $message = "El número de {$type} {$identityNumber} no está autorizado.";
        send_messages($message, $conversationId);
        insertVerificationLog($telephoneNumber, $type, 'NOT_VALIDATED');
        updateQueryLogResult($queryLogId, 'NEGATIVE');
    }
}







/******************************************************************************/
/******************************************************************************/
/******************************************************************************/
/******************************************************************************/
/******************************************************************************/
/******************************************************************************/

/* Este es el codigo para verificar SOLO DNI o SOLO tarjeta, ya no se está usando
function verifyDNI($messageId, $dni, $telephoneNumber, $queryLogId, $conversationId) {
    global $wpdb;
    
    // Assume $telephoneNumber is the phone number of the user making the verification attempt
    $matchCount = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM mc_validador_en_comercio_approved_dnis WHERE dni_number = %s", $dni));
    
    if ($matchCount > 0) {
        $message = "El DNI $dni está autorizado.";
        send_messages($message, $conversationId);
        insertVerificationLog($telephoneNumber, 'DNI', 'VALIDATED');
        // No need to update the query log, as it's already marked positive by default
    } else {
        $message = "El DNI $dni no está autorizado.";
        send_messages($message, $conversationId);
        // Here we log that the verification was not successful, but this function already does the insertion with a negative result
        insertVerificationLog($telephoneNumber, 'DNI', 'NOT_VALIDATED');
        // Now, we update the mc_validador_en_comercio_query_logs entry to reflect the negative result
        updateQueryLogResult($queryLogId, 'NEGATIVE'); // Correctly use $queryLogId to update the log
    }
}


function verifyCreditCard($messageId, $cardNumber, $telephoneNumber, $queryLogId, $conversationId) {
    global $wpdb;
    
    // Assume $telephoneNumber is the phone number of the user making the verification attempt
    $matchCount = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM mc_validador_en_comercio_approved_credit_cards WHERE card_number = %s", $cardNumber));
    
    if ($matchCount > 0) {
        $message = "Los últimos 4 dígitos de la tarjeta $cardNumber están autorizados.";
        send_messages($message, $conversationId);
        insertVerificationLog($telephoneNumber, 'CREDIT_CARD', 'VALIDATED');
    } else {
        $message = "Los últimos 4 dígitos de la tarjeta $cardNumber no están autorizados.";
        send_messages($message, $conversationId);
        // Here we log that the verification was not successful, but this function already does the insertion with a negative result
        insertVerificationLog($telephoneNumber, 'CREDIT_CARD', 'NOT_VALIDATED');
        // Now, we update the mc_validador_en_comercio_query_logs entry to reflect the negative result
        updateQueryLogResult($queryLogId, 'NEGATIVE'); // Correctly use $queryLogId to update the log
    }
}

*/
/******************************************************************************/
/******************************************************************************/
/******************************************************************************/
/******************************************************************************/
/******************************************************************************/
/******************************************************************************/




function insertVerificationLog($telephoneNumber, $type, $result) {
    global $wpdb;
    
    // Prepare data for insertion
    $data = array(
        'date_time' => current_time('mysql', 1), // Use WordPress function for current time in MySQL format
        'number' => $telephoneNumber,
        'type' => $type,
        'result' => $result,
    );
    
    // Corresponding format types
    $format = array('%s', '%s', '%s', '%s');
    
    // Attempt to insert data into the database
    $success = $wpdb->insert('mc_validador_en_comercio_approved_logs', $data, $format);
    
    // Check if the insertion was successful
    if ($success === false) {
        // If insertion failed, log the error to a file in the same directory as this script
        $logFilePath = __DIR__ . '/insert_verification_log_error.txt'; // Define the log file path
        $errorMessage = "Failed to insert verification log: " . $wpdb->last_error . " at " . date("Y-m-d H:i:s") . "\n";
        
        // Append the error message to the log file
        file_put_contents($logFilePath, $errorMessage, FILE_APPEND);
    }
}




function insertInitialQueryLog($phoneNumber, $action_type, $result, $messageId) {
    global $wpdb;

    // Recuperar el cuerpo del mensaje
    $message = $wpdb->get_var($wpdb->prepare("SELECT message FROM sb_messages WHERE id = %d", $messageId));
    
    $wpdb->insert(
        'mc_validador_en_comercio_query_logs',
        [
            'date_time' => current_time('mysql', 1),
            'action_type' => $action_type,
            'result' => $result,
            'whatsapp_number' => $phoneNumber, // Añadir el número de teléfono
            'message' => $message, // Añadir el cuerpo del mensaje
        ],
        [
            '%s', '%s', '%s', '%s', '%s', // Asegúrate de tener el formato correcto para cada campo
        ]
    );
    
    $queryLogId = $wpdb->insert_id;
    logMessage("Inserted query log ID for message ID {$messageId} with phone {$phoneNumber} and message: {$message}");
    return $queryLogId; // Return the ID of the newly inserted log entry.
}





function updateQueryLog($queryLogId, $result) {
    global $wpdb;
    
    // Prepare the data for update
    $data = ['result' => $result == 'VALIDATED' ? 'POSITIVE' : 'NEGATIVE'];
    $where = ['id' => $queryLogId];
    
    // Attempt to update the database
    $success = $wpdb->update('mc_validador_en_comercio_query_logs', $data, $where);
    
    // Check if the update was successful
    if ($success === false) {
        // Log the error to a file
        $logFilePath = __DIR__ . '/update_query_log_error.txt';
        $errorMessage = "Failed to update query log: " . $wpdb->last_error . " for Log ID: $queryLogId at " . date("Y-m-d H:i:s") . "\n";
        file_put_contents($logFilePath, $errorMessage, FILE_APPEND);
    } else {
        // Optionally, log the successful update
        $logMessage = "Successfully updated query log for Log ID: $queryLogId with result: $result at " . date("Y-m-d H:i:s") . "\n";
        file_put_contents($logFilePath, $logMessage, FILE_APPEND);
    }
}


function updateQueryLogResult($logId, $result) {
    global $wpdb;
    $wpdb->update(
        'mc_validador_en_comercio_query_logs', // Table name
        ['result' => $result], // Data to update
        ['id' => $logId] // WHERE condition
    );
}



function recoverConversationId($messageId) {
    global $wpdb; // Asegúrate de tener acceso a la variable global $wpdb para interactuar con la base de datos de WordPress

    // Prepara y ejecuta la consulta para obtener el conversation_id
    $conversationId = $wpdb->get_var($wpdb->prepare("SELECT conversation_id FROM sb_messages WHERE id = %d", $messageId));

    if ($conversationId !== null) {
        // Si se encontró un conversation_id, lo devuelve
        return $conversationId;
    } else {
        // Si no se encontró, devuelve null o maneja el error como prefieras
        return null;
    }
}



// Helper function for logging
function logMessage($logMessage) {
    $logFilePath = __DIR__ . '/message_verification_log.txt';
    file_put_contents($logFilePath, date("Y-m-d H:i:s") . " - " . $logMessage . "\n", FILE_APPEND);
}
