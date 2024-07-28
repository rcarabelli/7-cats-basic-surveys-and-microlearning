<?php
// Llamar al archivo config.php
require_once __DIR__ . '/../config.php';

// Now you can use your constants
require_once SUPPORT_FUNCTIONS_FILE;

// Usa la variable global para almacenar los intents y contextos recuperados de la base de datos
$GLOBALS['intents_and_contexts'] = null;

function get_intents_from_db() {
    global $wpdb; // Objeto global de WordPress para interactuar con la base de datos

    // Si la variable global ya está poblada, simplemente devuelve su valor
    if ($GLOBALS['intents_and_contexts'] !== null) {
        return $GLOBALS['intents_and_contexts'];
    }

    // Intenta recuperar los intents y contextos de la base de datos
    $results = $wpdb->get_results("SELECT * FROM `7c_intents_and_context_list`", ARRAY_A);

    // Si se produjo un error al recuperar los datos, registra el error y devuelve un array vacío
    if ($wpdb->last_error) {
        error_log("Error retrieving intents and contexts: " . $wpdb->last_error);
        return array();
    }

    // Almacena los resultados en la variable global y devuélvelos
    $GLOBALS['intents_and_contexts'] = $results;
    return $results;
}





// Function to handle the creation of a new survey
function handle_create_survey($wpdb, $table_name) {
    $survey_name = sanitize_text_field($_POST['survey_name']);
    $header = sanitize_text_field($_POST['header']);
    $survey_message = sanitize_textarea_field($_POST['survey_message']);
    $extra = sanitize_text_field($_POST['extra']);
    $image = esc_url_raw($_POST['image']);
    $num_questions = intval($_POST['num_questions']);
    $survey_type = sanitize_text_field($_POST['survey_type']); // Retrieve the "survey_type" from the POST data
    $intent = sanitize_text_field($_POST['intent']); // Retrieve the "intent" from the POST data

    $wpdb->insert(
        $table_name,
        array(
            'survey_name' => $survey_name,
            'header' => $header,
            'survey_message' => $survey_message,
            'extra' => $extra,
            'image' => $image,
            'num_questions' => $num_questions,
            'survey_type' => $survey_type, // Make sure to include this
            'intent' => $intent, // Make sure to include this
        )
    );
}



// Function to handle the editing of an existing survey
function handle_edit_survey($wpdb, $table_name) {
    $survey_id = intval($_POST['selected_survey']);
    $new_survey_name = sanitize_text_field($_POST['new_survey_name']);
    $new_header = sanitize_text_field($_POST['new_header']);
    $new_survey_message = sanitize_textarea_field($_POST['new_survey_message']);
    $new_extra = sanitize_text_field($_POST['new_extra']);
    $new_image = esc_url_raw($_POST['new_image']);
    $new_num_questions = intval($_POST['new_num_questions']);
    $new_survey_type = sanitize_text_field($_POST['new_survey_type']);  // Retrieve the selected survey type from POST data
    $new_intent = sanitize_text_field($_POST['new_intent']);  // Retrieve the selected intent from POST data

    $update_data = array();

    if (!empty($new_survey_name)) {
        $update_data['survey_name'] = $new_survey_name;
    }
    
    if (!empty($new_header)) {
        $update_data['header'] = $new_header;
    }

    if (!empty($new_survey_message)) {
        $update_data['survey_message'] = $new_survey_message;
    }

    if (!empty($new_extra)) {
        $update_data['extra'] = $new_extra;
    }
    
    if (!empty($new_image)) {
        $update_data['image'] = $new_image;
    }

    if (!empty($new_num_questions)) {
        $update_data['num_questions'] = $new_num_questions;
    }

    if (!empty($new_survey_type)) {
        $update_data['survey_type'] = $new_survey_type;  // Update the survey type
    }

    if (!empty($new_intent)) {
        $update_data['intent'] = $new_intent;  // Update the intent
    }

    if (!empty($update_data)) {
        $wpdb->update(
            $table_name,
            $update_data,
            array('survey_id' => $survey_id)
        );
    }
}



// Function to render the form for creating a new survey
function render_create_survey_form() {
    $intents = get_intents_from_db(); // Cambiado para recuperar los intents de la base de datos.
    
    // Aquí, puedes verificar si $intents tiene datos
    if(empty($intents)) {
        echo 'No se han encontrado intents en la base de datos.';
        return;
    }
    
    echo '<form method="post" action="">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="survey_name">Survey Name</label></th>
                        <td><input name="survey_name" type="text" id="survey_name" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="header">Header</label></th>
                        <td><input name="header" type="text" id="header" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="survey_message">Survey Message</label></th>
                        <td><textarea name="survey_message" id="survey_message" class="large-text" rows="4"></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="extra">Extra</label></th>
                        <td><input name="extra" type="text" id="extra" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="image">Image URL</label></th>
                        <td>
                        <input name="image" type="text" id="image" class="regular-text">
                        <button type="button" class="upload-media-button" 
                                data-input-id="image" 
                                data-title="Choose Image" 
                                data-button-text="Choose Image" 
                                id="upload_image_button">Upload Image</button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="num_questions">Number of Questions</label></th>
                        <td><input name="num_questions" type="number" id="num_questions" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="survey_type">Survey Type</label></th>
                        <td><select name="survey_type" id="survey_type">
                                <option value="survey">Survey</option>
                                <option value="menu">Menu</option>
                                <option value="microlearning">Microlearning</option>
                            </select></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="intent">Intent</label></th>
                        <td><select name="intent" id="intent">';

    foreach ($intents as $intent_data) {
        $intent = $intent_data['intent_or_context_name']; // Accediendo al valor de la columna 'intent_or_context_name'
        echo "<option value='$intent'>$intent</option>";
    }

    echo            '</select></td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <input type="submit" name="create_survey" class="button button-primary" value="Create Survey">
            </p>
          </form>';
}


// Function to render the form for editing an existing survey
function render_edit_survey_form($wpdb, $table_name, $selected_survey) {
    $intents = get_intents_from_db(); // Cambiado para recuperar los intents de la base de datos.
    
    // Aquí, puedes verificar si $intents tiene datos
    if(empty($intents)) {
        echo 'No se han encontrado intents en la base de datos.';
        return;
    }
    
    echo '<form method="post" action="">
            <input type="hidden" name="selected_survey" value="' . $selected_survey->survey_id . '">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">Original Survey Name</th>
                        <td>' . $selected_survey->survey_name . '</td>
                    </tr>
                    <tr>
                        <th scope="row">Original Survey Message</th>
                        <td>' . esc_textarea($selected_survey->survey_message) . '</td>
                    </tr>
                    <tr>
                        <th scope="row">Original Number of Questions</th>
                        <td>' . $selected_survey->num_questions . '</td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="new_survey_type">New Survey Type</label></th>
                        <td><select name="new_survey_type" id="new_survey_type">
                                <option value="survey" ' . ($selected_survey->survey_type == 'survey' ? 'selected' : '') . '>Survey</option>
                                <option value="menu" ' . ($selected_survey->survey_type == 'menu' ? 'selected' : '') . '>Menu</option>
                                <option value="microlearning" ' . ($selected_survey->survey_type == 'microlearning' ? 'selected' : '') . '>Microlearning</option>
                            </select></td>
                    </tr>
                    <tr>
                        <th scope="row">Original Intent</th>
                        <td>' . $selected_survey->intent . '</td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="new_survey_name">New Survey Name</label></th>
                        <td><input name="new_survey_name" type="text" id="new_survey_name" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="new_header">New Header</label></th>
                        <td><input name="new_header" type="text" id="new_header" class="regular-text" value="' . esc_attr($selected_survey->header) . '"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="new_survey_message">New Survey Message</label></th>
                        <td><textarea name="new_survey_message" id="new_survey_message" class="large-text" rows="4"></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="new_extra">New Extra</label></th>
                        <td><input name="new_extra" type="text" id="new_extra" class="regular-text" value="' . esc_attr($selected_survey->extra) . '"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="new_image">New Image URL</label></th>
                    <td>
                    <input name="new_image" type="text" id="new_image" class="regular-text" value="' . esc_attr($selected_survey->image) . '">
                    <button type="button" class="upload-media-button" 
                            data-input-id="new_image" 
                            data-title="Choose Image" 
                            data-button-text="Choose Image" 
                            id="upload_new_image_button">Upload Image</button>
                    </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="new_num_questions">New Number of Questions</label></th>
                        <td><input name="new_num_questions" type="number" id="new_num_questions" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="new_intent">New Intent</label></th>
                        <td><select name="new_intent" id="new_intent">';

    foreach ($intents as $intent_data) {
        $intent = $intent_data['intent_or_context_name']; // Accediendo al valor de la columna 'intent_or_context_name'
        echo "<option value='$intent'>$intent</option>";
    }

    echo            '</select></td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <input type="submit" name="edit_survey" class="button button-primary" value="Edit Survey">
            </p>
          </form>';
}

function enqueue_plugin_admin_scripts() {
    wp_enqueue_media(); // Asegúrate de que la biblioteca de medios de WordPress está encolada
    wp_enqueue_script('my-plugin-scripts', plugin_dir_url(__FILE__) . 'plugin-scripts.js', array('jquery'), null, true);
}


add_action('admin_enqueue_scripts', 'enqueue_plugin_admin_scripts');

// Main function
function render_create_and_manage_survey() {
    global $wpdb;
    $table_name = '7c_survey_surveys';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['create_survey'])) {
            handle_create_survey($wpdb, $table_name);
        } elseif (isset($_POST['edit_survey'])) {
            handle_edit_survey($wpdb, $table_name);
        }
    }

    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">Create and Manage Surveys</h1>';

    render_create_survey_form();

    echo '<h2>Edit Existing Survey</h2>';
    echo '<form method="post" action="">
            <label for="selected_survey">Select Survey to Edit:</label>
            <select name="selected_survey" id="selected_survey">';

    $surveys = $wpdb->get_results("SELECT * FROM $table_name");
    foreach ($surveys as $survey) {
        echo '<option value="' . $survey->survey_id . '">' . $survey->survey_name . '</option>';
    }

    echo '      </select>
              <input type="submit" name="select_survey" value="Select Survey">
          </form>';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_survey'])) {
        $selected_survey = $wpdb->get_row("SELECT * FROM $table_name WHERE survey_id = " . intval($_POST['selected_survey']));
        render_edit_survey_form($wpdb, $table_name, $selected_survey);
    }

    echo '</div>'; // End of wrap div
}
?>
