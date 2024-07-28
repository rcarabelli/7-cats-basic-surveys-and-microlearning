<?php
// Llamar al archivo config.php
require_once __DIR__ . '/../config.php';

// Ahora puedes usar las constantes
require_once SUPPORT_FUNCTIONS_FILE;

function render_add_questions_to_survey() {
    global $wpdb;
    $table_name_surveys = '7c_survey_surveys';
    $table_name_questions = '7c_survey_questions';
    $table_name_types = '7c_question_types';

    $selected_survey = isset($_POST['selected_survey']) ? intval($_POST['selected_survey']) : null;

    // Handle new question addition
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
        $existing_questions_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name_questions WHERE survey_id = $selected_survey");
        $survey_limit = $wpdb->get_var("SELECT num_questions FROM $table_name_surveys WHERE survey_id = $selected_survey");

        if ($existing_questions_count < $survey_limit) {
            $question_text = sanitize_text_field($_POST['question_text']);
            $answer_type = sanitize_text_field($_POST['answer_type']);
            $media_type = sanitize_text_field($_POST['media_type']);
            $media_url = sanitize_text_field($_POST['media_url']);
            $type_id = isset($_POST['type_id']) ? intval($_POST['type_id']) : null;
            $title = sanitize_text_field($_POST['title']);
            $extra = sanitize_text_field($_POST['extra']);

            $wpdb->insert(
                $table_name_questions,
                array(
                    'survey_id' => $selected_survey,
                    'question_text' => $question_text,
                    'answer_type' => $answer_type,
                    'media_type' => $media_type,
                    'media_url' => $media_url,
                    'type_id' => $type_id,
                    'title' => $title,
                    'extra' => $extra,
                )
            );
        } else {
            echo '<div class="notice notice-error"><p>You have reached the limit of questions for this survey.</p></div>';
        }
    }

    // Handle question editing
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_question'])) {
        $question_id = intval($_POST['question_id']);
        $new_question_text = sanitize_text_field($_POST['new_question_text']);
        $new_answer_type = sanitize_text_field($_POST['new_answer_type']);
        $new_media_type = sanitize_text_field($_POST['new_media_type']);
        $new_media_url = sanitize_text_field($_POST['new_media_url']);
        $new_type_id = isset($_POST['new_type_id']) ? intval($_POST['new_type_id']) : null;
        $new_title = sanitize_text_field($_POST['new_title']);
        $new_extra = sanitize_text_field($_POST['new_extra']);

        $wpdb->update(
            $table_name_questions,
            array(
                'question_text' => $new_question_text,
                'answer_type' => $new_answer_type,
                'media_type' => $new_media_type,
                'media_url' => $new_media_url,
                'type_id' => $new_type_id,
                'title' => $new_title,
                'extra' => $new_extra,
            ),
            array('question_id' => $question_id)
        );
    }

    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">Add or Edit Questions to Survey</h1>';
    echo '<form method="post" action="">';
    echo '<label for="selected_survey">Select Survey:</label>';
    echo '<select name="selected_survey" id="selected_survey" class="regular-text">';
    $surveys = $wpdb->get_results("SELECT * FROM $table_name_surveys");
    foreach ($surveys as $survey) {
        echo '<option value="' . $survey->survey_id . '"' . ($survey->survey_id === $selected_survey ? ' selected' : '') . '>' . $survey->survey_name . '</option>';
    }
    echo '</select>';
    echo '<input type="submit" name="select_survey" value="Select Survey" class="button button-primary">';
    echo '</form>';

    if ($selected_survey !== null) {
        $questions = $wpdb->get_results("SELECT * FROM $table_name_questions WHERE survey_id = $selected_survey");
        
        echo '<h2>Add New Question</h2>';
        echo '<form method="post" action="" class="form-table">';
        echo '<input type="hidden" name="selected_survey" value="' . $selected_survey . '">';
        echo '<table>';
        echo '<tr>';
        echo '<th scope="row"><label for="title">Title:</label></th>';
        echo '<td><input type="text" id="title" name="title" class="regular-text"></td>';
        echo '</tr>';        
        echo '<tr>';
        echo '<th scope="row"><label for="question_text">Question Text:</label></th>';
        echo '<td><input type="text" id="question_text" name="question_text" required class="regular-text"></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="extra">Extra:</label></th>';
        echo '<td><input type="text" id="extra" name="extra" class="regular-text"></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="media_type">Media Type:</label></th>';
        echo '<td><select name="media_type" id="media_type" class="regular-text">';
        echo '<option value="NONE">None</option>';
        echo '<option value="IMAGE">Image</option>';
        echo '<option value="VIDEO">Video</option>';
        echo '<option value="FILE">File</option>';
        echo '</select></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="media_url">Media URL:</label></th>';
        echo '<td>';
        echo '<input type="text" id="media_url" name="media_url" class="regular-text">';
        echo '<button type="button" class="upload-media-button" data-input-id="media_url" data-title="Choose Media" data-button-text="Choose Media" id="upload_media_button">Upload Media</button>';
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="answer_type">Answer Type:</label></th>';
        echo '<td><select name="answer_type" id="answer_type" class="regular-text">';
        echo '<option value="1-5">1-5</option>';
        echo '<option value="A-E">A-E</option>';
        echo '</select></td>';
        echo '</tr>';



        echo '<tr>';
        echo '<th scope="row"><label for="type_id">Question Type:</label></th>';
        echo '<td><select name="type_id" id="type_id" class="regular-text">';
        $types = $wpdb->get_results("SELECT * FROM $table_name_types");
        foreach ($types as $type) {
            echo '<option value="' . $type->type_id . '">' . $type->type_name . '</option>';
        }
        echo '</select></td>';
        echo '</tr>';
        echo '</table>';
        echo '<p class="submit">';
        echo '<input type="submit" name="add_question" value="Add Question" class="button button-primary">';
        echo '</p>';
        echo '</form>';

        // Display and Edit existing questions
        echo '<h2>Existing Questions</h2>';
        echo '<ul>';
        foreach ($questions as $question) {
            echo '<li>' . $question->question_text . ' (' . $question->answer_type . ') <a href="#edit_question_' . $question->question_id . '">Edit</a></li>';
        }
        echo '</ul>';

        echo '<h2>Edit Questions</h2>';
        foreach ($questions as $question) {
            echo '<form method="post" action="" id="edit_question_' . $question->question_id . '">';
            echo '<input type="hidden" name="question_id" value="' . $question->question_id . '">';
            echo '<table class="form-table">';
            echo '<tr>';
            echo '<th scope="row"><label for="new_title">New Title:</label></th>';
            echo '<td><input type="text" id="new_title" name="new_title" value="' . $question->title . '" class="regular-text"></td>';
            echo '</tr>';
            echo '<tr>';
            echo '<th scope="row"><label for="new_question_text">New Question Text:</label></th>';
            echo '<td><input type="text" id="new_question_text" name="new_question_text" value="' . $question->question_text . '" class="regular-text"></td>';
            echo '</tr>';
            echo '<tr>';
            echo '<th scope="row"><label for="new_extra">New Extra:</label></th>';
            echo '<td><input type="text" id="new_extra" name="new_extra" value="' . $question->extra . '" class="regular-text"></td>';
            echo '</tr>';
            echo '<tr>';
            echo '<th scope="row"><label for="new_media_type">New Media Type:</label></th>';
            echo '<td><select name="new_media_type" id="new_media_type" class="regular-text">';
            echo '<option value="NONE" ' . ($question->media_type === 'NONE' ? 'selected' : '') . '>None</option>';
            echo '<option value="IMAGE" ' . ($question->media_type === 'IMAGE' ? 'selected' : '') . '>Image</option>';
            echo '<option value="VIDEO" ' . ($question->media_type === 'VIDEO' ? 'selected' : '') . '>Video</option>';
            echo '<option value="FILE" ' . ($question->media_type === 'FILE' ? 'selected' : '') . '>File</option>';
            echo '</select></td>';
            echo '</tr>';            
            echo '<tr>';
            echo '<tr>';
            echo '<th scope="row"><label for="new_media_url">New Media URL:</label></th>';
            echo '<td>';
            echo '<input type="text" id="new_media_url_' . $question->question_id . '" name="new_media_url" value="' . $question->media_url . '" class="regular-text">';
            echo '<button type="button" class="upload-media-button" data-input-id-prefix="new_media_url_" data-title="Choose Media" data-button-text="Choose Media" id="upload_media_button_' . $question->question_id . '">Upload Media</button>';
            echo '</td>';
            echo '</tr>';
            echo '<tr>';
            echo '<th scope="row"><label for="new_answer_type">New Answer Type:</label></th>';
            echo '<td><select name="new_answer_type" id="new_answer_type" class="regular-text">';
            echo '<option value="1-5" ' . ($question->answer_type === '1-5' ? 'selected' : '') . '>1-5</option>';
            echo '<option value="A-E" ' . ($question->answer_type === 'A-E' ? 'selected' : '') . '>A-E</option>';
            echo '</select></td>';
            echo '</tr>';
            echo '<tr>';
            echo '<th scope="row"><label for="new_type_id">New Question Type:</label></th>';
            echo '<td><select name="new_type_id" id="new_type_id" class="regular-text">';
            $types = $wpdb->get_results("SELECT * FROM $table_name_types");
            foreach ($types as $type) {
                echo '<option value="' . $type->type_id . '" ' . ($question->type_id === $type->type_id ? 'selected' : '') . '>' . $type->type_name . '</option>';
            }
            echo '</select></td>';
            echo '</tr>';
            echo '</table>';
            echo '<p class="submit">';
            echo '<input type="submit" name="edit_question" value="Edit Question" class="button button-primary">';
            echo '</p>';
            echo '</form>';
        }
    }

    echo '</div>';  // End of wrap div
}

function enqueue_questions_admin_scripts() {
    // Asegúrate de que la biblioteca de medios de WordPress está encolada
    wp_enqueue_media();
    
    // Asegúrate de encolar tu script personalizado en el pie de página
    wp_enqueue_script('my-plugin-scripts', plugin_dir_url(__FILE__) . 'plugin-scripts.js', array('jquery'), null, true);
}

add_action('admin_enqueue_scripts', 'enqueue_questions_admin_scripts');

?>