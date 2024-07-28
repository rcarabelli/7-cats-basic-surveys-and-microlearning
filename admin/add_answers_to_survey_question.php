<?php
// /home/<account>/public_html/wp-content/plugins/7-cats-basic-surveys-and-microlearning/admin/add_answers_to_survey_question.php

// Llamar al archivo config.php
require_once __DIR__ . '/../config.php';

// Now you can use your constants
require_once SUPPORT_FUNCTIONS_FILE;

add_action('admin_head', 'render_add_answer_styles');


function render_add_answer_styles() {
    ?>
    <style>
        .answer-group {
            display: flex;
            align-items: top;
            margin-bottom: 10px;
            width: auto; /* Para que ocupe solo el espacio necesario */
            max-width: 320px; /* Si deseas limitar aun más su anchura */
        }
    
        .answer-input {
            flex: 1;
            margin-right: 10px;
            width: 150px; /* Ajusta segun prefieras */
            box-sizing: border-box; /* Asegura que el padding y border se incluyan en el ancho total */
            height: 28px; /* Ajusta la altura */
        }
    
        .answer-dropdown {
            width: 150px; /* Ajusta segun prefieras */
            height: 28px; /* Asegura que tiene la misma altura que el input */
            margin-right: 10px;
            box-sizing: border-box; /* Asegura que el padding y border se incluyan en el ancho total */
        }
        
        .delete-answer {
            background-color: #f44336;
            border: none;
            color: white;
            padding-top: 1px;
            padding-bottom: 6px;
            padding-left: 9px;
            padding-right: 9px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            margin-left: 5px;
            border-radius: 50%;
            width: 36px;
            height: 26px;
            line-height: 16px;
            cursor: pointer;
            position: relative;
            top: 3px;  /* Ajusta este valor segun la necesidad */
        }


        .delete-answer:hover {
            background-color: #d32f2f; /* Color rojo un poco mas oscuro al pasar el cursor */
}

    </style>
    <?php
}





/**
 * Function: render_add_answers_to_survey_question
 * 
 * Purpose:
 * This function handles the creation and editing of possible answers to survey questions.
 * It performs two main operations:
 * 1. Processes the POST request to add or update answers to a survey question.
 * 2. Renders the HTML form for adding and editing answers based on the currently selected survey and question.
 * 
 * Steps:
 * 
 * 1. Checks if the request method is POST, ensuring the request is made via AJAX and a question_id is set:
 *    - Logs the POST data for debugging.
 *    - Initializes buffer (ob_start) to capture any unwanted outputs.
 *    - Calls a function to process and update any existing answers.
 *    - Iterates through POST data to process any new answers:
 *      a. Determines if the key starts with 'answer_' (indicating a new answer).
 *      b. Extracts the option_number, answer_text, and answer_type.
 *      c. Calls the insert_or_update_answers_on_survey_questions function to handle DB interaction.
 *    - Ends the buffer (ob_end_clean) to discard any unwanted outputs.
 *    - Based on the success or failure of the DB operations, logs the outcome and echoes "success" or "error".
 *    - Exits the function to prevent further processing.
 * 
 * 2. If not a POST request or not the expected AJAX POST:
 *    - Retrieves a list of surveys and associated questions from the database.
 *    - If a survey_id is provided in the POST request, retrieves details about the selected survey.
 *    - If a question_id is provided in the POST request, retrieves details about the selected question.
 *    - If a selected_question_id exists, retrieves existing answers associated with that question.
 * 
 * 3. Renders the HTML:
 *    - Displays the title "Creación y edición de respuestas para las preguntas de las encuestas".
 *    - Renders dropdown menus for selecting surveys and questions.
 *    - Renders the form fields for adding or editing answers.
 *    - Adds a JavaScript event listener to submit the form when the survey selection changes.
 * 
 * Inputs:
 * - $_POST['ajax']: Expected to be set if the request is made via AJAX.
 * - $_POST['question_id']: The ID of the survey question being edited.
 * - $_POST['survey_id']: The ID of the selected survey.
 * 
 * Outputs:
 * - "success" or "error" echoed to the browser based on the result of the DB operations.
 * - HTML form for adding and editing answers rendered to the browser.
 */
function render_add_answers_to_survey_question() {
    global $wpdb;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] == 1 && isset($_POST['question_id'])) {

        ob_start(); 
        $question_id = intval($_POST['question_id']);
        $success = true;

        // Call the new function to process existing answers.
        $selected_survey_type = isset($_POST['survey_type']) ? $_POST['survey_type'] : null;
        $success = $success && update_existing_answers_on_survey_question($wpdb, $question_id, $selected_survey_type);
        
        // Process new answers
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'answer_') === 0 && !empty($value)) {
                $option_number = intval(str_replace('answer_', '', $key));
                        
                if ($option_number > 0) {
                    $answer_text = sanitize_text_field($value);
                    
                    // Get the corresponding answer_type if it's available in POST
                    $answer_type = isset($_POST['answer_type_' . $option_number]) ? sanitize_text_field($_POST['answer_type_' . $option_number]) : null;
        
                    // Extract action_to_take and type_of_action from POST data
                    $action_to_take = isset($_POST['action_to_take_' . $option_number]) ? sanitize_text_field($_POST['action_to_take_' . $option_number]) : null;
                    $type_of_action = isset($_POST['type_of_action_' . $option_number]) ? sanitize_text_field($_POST['type_of_action_' . $option_number]) : null;
            
                    // Use the refactored function, passing the new arguments
                    $result = insert_or_update_answers_on_survey_questions($wpdb, $question_id, $option_number, $answer_text, $answer_type, $action_to_take, $type_of_action);
        
                    if ($result === false) {
                        $success = false;
                    }
                }
            }
        }

        ob_end_clean();
        if ($success) {
            echo "success";
        } else {
            echo "error";
        }
        exit;
    }

    $selected_survey_id = $selected_question_id = null;
    $selected_survey_name = $selected_question_text = $selected_type_name = '';
    $surveys = $wpdb->get_results("SELECT * FROM 7c_survey_surveys", OBJECT);

    $questions = [];
    if (isset($_POST['survey_id']) && !empty($_POST['survey_id'])) {
        $selected_survey_id = intval($_POST['survey_id']);
        $selected_survey = $wpdb->get_row($wpdb->prepare("SELECT * FROM 7c_survey_surveys WHERE survey_id = %d", $selected_survey_id), OBJECT);
        $selected_survey_name = $selected_survey->survey_name;

        $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM 7c_survey_questions WHERE survey_id = %d AND type_id = 1", $selected_survey_id), OBJECT);
    }
    
    $selected_survey_type = null;
    if($selected_survey_id) {
        $selected_survey_type = $wpdb->get_var($wpdb->prepare("SELECT survey_type FROM 7c_survey_surveys WHERE survey_id = %d", $selected_survey_id));
    }


    if (isset($_POST['question_id']) && !empty($_POST['question_id'])) {
        $selected_question_id = intval($_POST['question_id']);
        $selected_question = $wpdb->get_row($wpdb->prepare("SELECT * FROM 7c_survey_questions WHERE question_id = %d", $selected_question_id), OBJECT);
        $selected_question_text = $selected_question->question_text;

        $type = $wpdb->get_row($wpdb->prepare("SELECT * FROM 7c_question_types WHERE type_id = %d", $selected_question->type_id), OBJECT);
        $selected_type_name = $type->type_name;
    }

    $existing_answers = [];
    if ($selected_question_id) {
        $existing_answers = $wpdb->get_results($wpdb->prepare("SELECT * FROM 7c_possible_answers WHERE question_id = %d", $selected_question_id), OBJECT);
    }

    ?>

    <div class="wrap">
        <h1 class="wp-heading-inline">Creación y edición de respuestas para las preguntas de las encuestas</h1>


        <?php 
        render_survey_question_dropdowns($selected_survey_id, $selected_question_id);
        render_answer_forms_and_scripts($selected_question_id, $existing_answers, $selected_survey_type);

    ?>
    <script>
        document.getElementById('surveySelector').addEventListener('change', function() {
            document.getElementById('surveyForm').submit();
        });
    </script>
    <?php
}





/**
 * Función render_add_answers_to_survey_question
 * 
 * Esta función se encarga de manejar y mostrar las respuestas relacionadas a una pregunta específica de una encuesta.
 * A grandes rasgos, realiza lo siguiente:
 * 1. Si se hace una petición POST, procesa y actualiza o inserta respuestas en la base de datos.
 * 2. Obtiene el listado de encuestas y, si se ha seleccionado una, muestra las preguntas relacionadas.
 * 3. Si se selecciona una pregunta, muestra las respuestas existentes para esa pregunta y permite agregar o modificar respuestas.
 * 4. Incluye scripts y estilos relacionados con la gestión y presentación de respuestas.
 * 
 * La función también utiliza una función auxiliar para renderizar los menús desplegables de selección de encuestas y preguntas.
 */
function render_survey_question_dropdowns($selected_survey_id = null, $selected_question_id = null) {
    global $wpdb;

    $surveys = $wpdb->get_results("SELECT * FROM 7c_survey_surveys", OBJECT);

    $questions = [];
    if ($selected_survey_id) {
        $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM 7c_survey_questions WHERE survey_id = %d AND type_id = 1", $selected_survey_id), OBJECT);
    }

    ?>
    <form method="post" action="" id="surveyForm">
        <table class="form-table" style="margin-bottom: 0;">
            <tbody>
                <tr>
                    <th scope="row"><label for="survey_id">Select Survey:</label></th>
                    <td>
                        <select name="survey_id" id="surveySelector" class="postform">
                            <option value="">-- Select --</option>
                            <?php foreach ($surveys as $survey): ?>
                                <option value="<?php echo $survey->survey_id; ?>" <?php selected($selected_survey_id, $survey->survey_id); ?>><?php echo $survey->survey_name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </tbody>
        </table>
    </form>

    <?php if (!empty($questions)): ?>
        <form method="post" action="">
            <input type="hidden" name="survey_id" value="<?php echo $selected_survey_id; ?>" />
            <table class="form-table" style="margin-top: 0;">
                <tbody>
                    <tr>
                        <th scope="row"><label for="question_id">Select Question:</label></th>
                        <td>
                            <select name="question_id" class="postform">
                                <option value="">-- Select --</option>
                                <?php foreach ($questions as $question): ?>
                                    <option value="<?php echo $question->question_id; ?>" <?php selected($selected_question_id, $question->question_id); ?>><?php echo $question->question_text; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="submit" value="Show Details" class="button button-primary" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
        <hr style="margin-top: 10px; margin-bottom: 20px; border: 0; border-top: 1px solid #ccc;" />
    <?php endif; ?>

    <?php
}





/**
 * Function: render_answer_forms_and_scripts
 * 
 * Purpose:
 * This function renders the HTML form and associated scripts for adding, editing, and deleting answers to a selected survey question. It displays answer inputs based on existing answers, provides the capability to add new answers dynamically and submits them via AJAX. Furthermore, it handles survey types, specifically the 'microlearning' type, by offering a dropdown to specify the answer type.
 * 
 * Steps:
 * 
 * 1. Conditional Rendering:
 *    - Only renders the contents if a valid selected_question_id is provided.
 * 
 * 2. HTML Form Rendering:
 *    - Iterates through each of the existing answers:
 *      a. Displays an input field pre-filled with the existing answer.
 *      b. If the survey type is 'microlearning', displays a dropdown with options: 'null', 'yes', 'no', and 'partial'.
 *      c. Provides a button to delete the answer.
 *    - Offers a button to add a new answer dynamically.
 *    - Contains hidden input fields to pass along the question_id and AJAX flag.
 *    - Provides a submit button to finalize and send the answers.
 *    - Contains a message container to show AJAX request outcomes.
 * 
 * 3. JavaScript Logic:
 *    - Handles the dynamic addition of new answer fields:
 *      a. Creates a new input field for the answer.
 *      b. If the survey type is 'microlearning', dynamically generates a dropdown with answer type options.
 *    - Allows the deletion of answers using the delete button.
 *    - Manages the AJAX submission of the form:
 *      a. Posts the form data to the specified action.
 *      b. Updates the message container based on the server response.
 * 
 * 4. Styling:
 *    - Provides basic CSS styling for the answer input fields.
 * 
 * Inputs:
 * - $selected_question_id: The ID of the selected survey question.
 * - $existing_answers: Array of existing answers related to the selected question.
 * - $selected_survey_type: The type of the selected survey (e.g., 'microlearning').
 * 
 * Outputs:
 * - An interactive HTML form rendered to the browser, allowing the user to add, edit, or delete answers.
 */
function render_answer_forms_and_scripts($selected_question_id, $existing_answers, $selected_survey_type) {
    
    // Obtener valores predeterminados de la base de datos
    $is_correct_answer_defaults = get_enum_values('7c_possible_answers', 'is_correct_answer');
    $type_of_action_defaults = get_enum_values('7c_possible_answers', 'type_of_action');

    ?>
    <?php if ($selected_question_id): ?>
        <form id="answers-form" method="POST" action="">
            <div id="answers-container">
                <?php foreach ($existing_answers as $answer): ?>
                    <div class="answer-group">
                        <input type="text" class="answer-input" name="existing_answer_<?php echo $answer->option_number; ?>" value="<?php echo esc_attr($answer->answer_text); ?>" placeholder="Enter answer <?php echo $answer->option_number; ?>">
                        
                        <?php if($selected_survey_type == 'microlearning'): ?>
                            <select class="answer-dropdown" name="answer_type_<?php echo $answer->option_number; ?>">
                                <?php foreach ($is_correct_answer_defaults as $value): ?>
                                    <option value="<?php echo $value; ?>" <?php selected($answer->is_correct_answer, $value); ?>><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        
                        <!-- Action to take input -->
                        <input type="text" class="answer-input" id="action_to_take_<?php echo $answer->option_number; ?>" name="action_to_take_<?php echo $answer->option_number; ?>" value="<?php echo esc_attr($answer->action_to_take ?? ''); ?>" placeholder="Enter action to take">
                        
                        <!-- Type of action dropdown -->
                        <select class="answer-dropdown" id="type_of_action_<?php echo $answer->option_number; ?>" name="type_of_action_<?php echo $answer->option_number; ?>">
                            <?php foreach ($type_of_action_defaults as $value): ?>
                                <option value="<?php echo $value; ?>" <?php selected($answer->type_of_action, $value); ?>><?php echo $value; ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <button class="delete-answer" type="button">x</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button id="add-answer-button" type="button">Add Answer</button>
            <input type="hidden" name="question_id" value="<?php echo $selected_question_id; ?>">
            <input type="hidden" name="ajax" value="1">
            <button id="submit-answers-button" type="button">Submit Answers</button>
            <div id="message-container"></div>
        </form>
 
        <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", function() {
                let form = document.getElementById("answers-form");
                let container = document.getElementById("answers-container");
                let addButton = document.getElementById("add-answer-button");
                let submitButton = document.getElementById("submit-answers-button");
                let messageContainer = document.getElementById("message-container");
                                
                let counter = <?php echo count($existing_answers) + 1; ?>;
                
                addButton.addEventListener("click", function() {
                    let answerGroupDiv = document.createElement("div");
                    answerGroupDiv.className = "answer-group";
                    
                    // Create text input
                    let input = document.createElement("input");
                    input.type = "text";
                    input.name = "answer_" + counter;
                    input.className = "answer-input"; // Added class
                    input.placeholder = "Enter answer " + counter;
                    answerGroupDiv.appendChild(input);
                    
                    // Check if the survey type is 'microlearning'
                    if (<?php echo json_encode($selected_survey_type) ?> == 'microlearning') {
                        // Create dropdown for microlearning
                        let dropdown = document.createElement("select");
                        dropdown.name = "answer_type_" + counter;
                        dropdown.className = "answer-dropdown"; // Added class
                        ["null", "yes", "no", "partial"].forEach(optionValue => {
                            let option = document.createElement("option");
                            option.value = optionValue;
                            option.textContent = optionValue;
                            dropdown.appendChild(option);
                        });
                        answerGroupDiv.appendChild(dropdown);
                    }
                    
                    // Create and append action to take input
                    let actionInput = document.createElement("input");
                    actionInput.type = "text";
                    actionInput.name = "action_to_take_" + counter;
                    actionInput.className = "answer-input"; // Added class
                    actionInput.placeholder = "Enter action to take";
                    answerGroupDiv.appendChild(actionInput);
                    
                    // Create and append type of action dropdown
                    let actionDropdown = document.createElement("select");
                    actionDropdown.name = "type_of_action_" + counter;
                    actionDropdown.className = "answer-dropdown"; // Added class
                    ["null", "function", "message", "cancel"].forEach(optionValue => {
                        let option = document.createElement("option");
                        option.value = optionValue;
                        option.textContent = optionValue;
                        actionDropdown.appendChild(option);
                    });
                    answerGroupDiv.appendChild(actionDropdown);
                    
                    // Delete button
                    let deleteButton = document.createElement("button");
                    deleteButton.className = "delete-answer"; // Added class
                    deleteButton.textContent = "x";
                    deleteButton.type = "button";
                    answerGroupDiv.appendChild(deleteButton);
                    
                    container.appendChild(answerGroupDiv);
                    
                    counter++;
                });
                
                container.addEventListener('click', function(e) {
                    if (e.target && e.target.className === 'delete-answer') {
                        // Before removing the element, retrieve the option_number
                        let nameAttribute = e.target.previousElementSibling.name;
                        let optionNumber = nameAttribute.match(/\d+/)[0]; // Extract the number from the string
                        
                        // Create a hidden input for deleted answers
                        let hiddenInput = document.createElement('input');
                        hiddenInput.type = "hidden";
                        hiddenInput.name = "deleted_answer_" + optionNumber;
                        hiddenInput.value = optionNumber;
                        form.appendChild(hiddenInput);  // Add it to the main form
                        
                        // Log for debugging purposes
                        console.log('Deleted answer with option_number:', optionNumber);
                        
                        // Remove the answer group
                        e.target.parentElement.remove();
                    }
                });
            
                submitButton.addEventListener("click", function() {
                    let formData = new FormData(form);
                    fetch(form.action, {
                        method: 'POST',
                        body: formData,
                    })
                    .then(response => response.text())
                    .then(text => {
                        if (text.includes("success")) {
                            messageContainer.textContent = 'Los datos fueron insertados exitosamente';
                        } else {
                            messageContainer.textContent = 'Hubo un error al insertar los datos';
                        }
                    })
                    .catch(error => {
                        console.error('Hubo un error al enviar el formulario:', error);
                        messageContainer.textContent = 'Hubo un error al enviar el formulario';
                    });
                });
            });
        </script>

        <style>
            #answers-container input {
                display: block;
                margin-bottom: 10px;
            }
        </style>
    <?php endif; ?>
    <?php
}



function get_enum_values($table, $field) {
    global $wpdb;
    
    $result = $wpdb->get_row("SHOW COLUMNS FROM {$table} WHERE Field = '{$field}'");
    if (!$result) {
        return [];
    }
    
    $type = $result->Type;
    preg_match('/^enum\((.*)\)$/', $type, $matches);
    $values = [];
    foreach( explode(',', $matches[1]) as $value ){
        $values[] = trim( $value, "'" );
    }
    return $values;
}



/**
 * Function: insert_or_update_answers_on_survey_questions
 * 
 * Purpose:
 * This function aims to either insert a new answer for a specific survey question or update an existing one in the '7c_possible_answers' table. It first checks whether the combination of question_id and option_number already exists in the database. If it does, the answer is updated; if it doesn't, a new record is inserted.
 * 
 * Steps:
 * 
 * 1. Check for Existing Entry:
 *    - A database query is performed to check if an entry with the given question_id and option_number already exists.
 * 
 * 2. Update or Insert:
 *    - If an existing entry is found:
 *      a. An update operation is executed on the '7c_possible_answers' table to update the answer_text and is_correct_answer columns based on the provided data.
 *      b. Log the update action.
 *    - If no existing entry is found:
 *      a. An insert operation is executed on the '7c_possible_answers' table to add a new record with the provided data.
 *      b. Log the insert action.
 * 
 * Inputs:
 * - $wpdb: The WordPress database object for performing database operations.
 * - $question_id: The ID of the related survey question.
 * - $option_number: The number indicating the answer's position/sequence.
 * - $answer_text: The text of the answer to be inserted or updated.
 * - $answer_type (optional): Specifies if the answer is correct. Only applicable for specific survey types (e.g., 'microlearning').
 * 
 * Outputs:
 * - Success:
 *   - Returns the number of rows affected by the operation (either insert or update).
 * - Failure:
 *   - Returns false.
 */
function insert_or_update_answers_on_survey_questions($wpdb, $question_id, $option_number, $answer_text, $answer_type = null, $action_to_take = null, $type_of_action = null) {
    // Check if the combination already exists
    $existing_entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM 7c_possible_answers WHERE question_id = %d AND option_number = %d", $question_id, $option_number), OBJECT);

    if ($existing_entry) {
        return $wpdb->update(
            '7c_possible_answers',
            [
                'answer_text' => $answer_text,
                'is_correct_answer' => $answer_type,
                'action_to_take' => $action_to_take, // Add this line
                'type_of_action' => $type_of_action // Add this line
            ],
            ['question_id' => $question_id, 'option_number' => $option_number]
        );
    } else {
        return $wpdb->insert('7c_possible_answers', [
            'question_id' => $question_id,
            'option_number' => $option_number,
            'answer_text' => $answer_text,
            'is_correct_answer' => $answer_type,
            'action_to_take' => $action_to_take, // Add this line
            'type_of_action' => $type_of_action // Add this line
        ]);
    }
}




/**
 * Function: update_existing_answers_on_survey_question
 * 
 * Purpose:
 * This function's primary goal is to update existing answers for a specific survey question based on incoming POST data. The POST data should contain keys starting with 'existing_answer_' followed by the option number, which is the position or sequence of the answer. This function will then proceed to update each answer in the '7c_possible_answers' table.
 * 
 * Steps:
 * 
 * 1. Initialize Success Flag:
 *    - Start with an assumption that the operation will be successful.
 * 
 * 2. Iterate Through POST Data:
 *    - For each key in the POST data that starts with 'existing_answer_':
 *      a. Extract the option number from the key.
 *      b. If the option number is valid (>0) and the associated value is not empty:
 *         i. Sanitize the answer text.
 *         ii. Get the associated answer type from the POST data and sanitize it.
 *         iii. Log the intention to update the answer.
 *         iv. Execute an update operation on the '7c_possible_answers' table.
 *         v. If the update operation fails, log an error and update the success flag to false.
 * 
 * Inputs:
 * - $wpdb: The WordPress database object for performing database operations.
 * - $question_id: The ID of the related survey question.
 * 
 * Outputs:
 * - Success:
 *   - Returns true, indicating that all updates were successful.
 * - Failure:
 *   - Returns false, signifying that one or more update operations failed.
 */
function update_existing_answers_on_survey_question($wpdb, $question_id, $selected_survey_type) {
    $success = true;
    
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'existing_answer_') === 0 && !empty($value)) {
            $option_number = intval(str_replace('existing_answer_', '', $key));
                    
            if ($option_number > 0) {
                $answer_text = sanitize_text_field($value);
                
                // Only get answer_type if survey type is microlearning
                $answer_type = ($selected_survey_type == 'microlearning' && isset($_POST['answer_type_' . $option_number])) ? sanitize_text_field($_POST['answer_type_' . $option_number]) : null;
                
                $action_to_take = isset($_POST['action_to_take_' . $option_number]) ? sanitize_text_field($_POST['action_to_take_' . $option_number]) : null;
                $type_of_action = isset($_POST['type_of_action_' . $option_number]) ? sanitize_text_field($_POST['type_of_action_' . $option_number]) : null;
                       
                $update_data = [
                    'answer_text' => $answer_text,
                    'action_to_take' => $action_to_take,
                    'type_of_action' => $type_of_action
                ];
                
                if ($selected_survey_type == 'microlearning') {
                    $update_data['is_correct_answer'] = $answer_type;
                }

                $result = $wpdb->update('7c_possible_answers', $update_data, ['question_id' => $question_id, 'option_number' => $option_number]);
        
                if ($result === false) {
                    $success = false;
                }
            }
        }

        // Check for deleted answers
        if (strpos($key, 'deleted_answer_') === 0) {
            $option_number = intval(str_replace('deleted_answer_', '', $key));
            if ($option_number > 0) {
                $result = $wpdb->delete('7c_possible_answers', ['question_id' => $question_id, 'option_number' => $option_number]);
                if ($result === false) {
                    $success = false;
                } else {
                }
            }
        }
    }
    
    return $success;
}