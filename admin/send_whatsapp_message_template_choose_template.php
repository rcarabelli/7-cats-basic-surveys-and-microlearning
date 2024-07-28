<?php
// /home/<account>/public_html/wp-content/plugins/7-ktz-basic-surveys-and-microlearning/admin/send_whatsapp_message_template_choose_template.php

// Llamar al archivo config.php
require_once __DIR__ . '/../config.php';

// Ahora puedes usar las constantes
require_once SUPPORT_FUNCTIONS_FILE;

// Agregar solicitudes AJAX
require_once plugin_dir_path(__FILE__) . '../includes/process_ajax_request.php';

function my_enqueue_admin_scripts() {
    // Usando plugin_dir_url para generar la ruta del archivo JS de forma dinámica.
    wp_enqueue_script('my-plugin-script', plugin_dir_url(__FILE__) . 'plugin-scripts.js', array('jquery'));

    wp_localize_script('my-plugin-script', 'myPlugin', array(
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
}


add_action('admin_enqueue_scripts', 'my_enqueue_admin_scripts');



// Datos de usuarios predeterminados (estos son los que proporcionaste, pero decodificados de su formato URL)
$default_selected_users = [
    (object) [
        "user_id" => "10",
        "first_name" => "Francesca",
        "last_name" => "Tripi",
        "email" => null,
        "whatsapp_number" => "+51922053856"
    ],
    (object) [
        "user_id" => "1636",
        "first_name" => "Renato",
        "last_name" => "Carabelli",
        "email" => "rcarabelli@gmail.com",
        "whatsapp_number" => "+51956031565"
    ],
    // ... puedes continuar con otros usuarios
];


// Para recibir los datos enviados por POST y manejarlos:
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_users'])) {
    if (is_string($_POST['selected_users'])) {
        $users_details = [json_decode($_POST['selected_users'])];
    } else {
        $decoded_selected_users = array_map('json_decode', $_POST['selected_users']);
        $unique_users_details = [];
        foreach ($decoded_selected_users as $user) {
            $unique_users_details[$user->user_id] = $user;
        }
        $users_details = array_values($unique_users_details);
    }
} else {
    // Usamos los datos predeterminados si no hay datos POST
    $users_details = $default_selected_users;
}




// Conexión a la base de datos
global $wpdb;

// Obtener los templates de la base de datos
$templates = $wpdb->get_results("SELECT * FROM 7c_whatsapp_template_configurations", OBJECT);

function render_send_whatsapp_message_template_choose_template() {
    global $users_details, $templates;

    echo '<div class="wrap">'; // Clase 'wrap' para contenedores principales en WP Admin
    
    // Título de la Página
    echo '<h2>Choose the message template to send</h2>'; // Título sin la clase 'title'
    
    if (isset($users_details) && is_array($users_details)) {
        error_log(json_encode($users_details));
        echo '<div class="postbox">'; // Clase 'postbox' para cajas de contenido en WP Admin
        echo '<h3 class="hndle">Usuarios Seleccionados</h3>'; // Clase 'hndle' para títulos de cajas en WP Admin
        echo '<div class="inside">'; // Clase 'inside' para contenido interno de cajas en WP Admin
        foreach($users_details as $user) {
            echo "<p>ID: {$user->user_id}, Nombre: {$user->first_name}, Apellido: {$user->last_name}, WhatsApp: {$user->whatsapp_number}</p>";
        }
        echo '</div>'; // Final del div con la clase 'inside'.
        echo '</div>'; // Final del div con la clase 'postbox'.
        
        // Imprimir los message templates con radio buttons
        echo '<div class="wrap">';
        echo '<h2>Templates de WhatsApp</h2>';
        echo '<form id="templateForm">'; // Aquí abrimos la etiqueta <form>
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '    <thead>';
        echo '        <tr>';
        echo '            <th scope="col">Seleccionar</th>';
        echo '            <th scope="col">ID</th>';
        echo '            <th scope="col">Template Name</th>';
        echo '            <th scope="col">Template Description</th>';
        echo '            <th scope="col">Template Languages</th>';
        echo '            <th scope="col">Header Parameters</th>';
        echo '            <th scope="col">Body Parameters</th>';
        echo '        </tr>';
        echo '    </thead>';
        echo '    <tbody>';
        foreach($templates as $template) {
            echo '<tr>';
            echo "  <td><input type='radio' name='template' value='{$template->id}'></td>";
            echo "  <td>{$template->id}</td>";
            echo "  <td>{$template->template_name}</td>";
            echo "  <td>{$template->template_description}</td>";
            echo "  <td>{$template->template_languages}</td>";
            echo "  <td>{$template->header_parameters}</td>";
            echo "  <td>{$template->body_parameters}</td>";
            echo '</tr>';
        }
        echo '    </tbody>';
        echo '</table>';
        echo '</form>'; // Cierre de la etiqueta <form>
        echo '</div>'; // Cierre de la etiqueta <div class="wrap">
        echo '<div style="height: 20px;"></div>'; // Div con espaciado vertical

        echo '<div id="choiceConfirmation"></div>';

        echo '<div class="userDetailsDebug">';
        echo '<h2>User Details Debug</h2>';
        foreach($users_details as $user) {
            echo '<p>';
            echo 'User ID: ' . htmlspecialchars($user->user_id) . '<br>';
            echo 'First Name: ' . htmlspecialchars($user->first_name) . '<br>';
            echo 'Last Name: ' . htmlspecialchars($user->last_name) . '<br>';
            echo 'WhatsApp Number: ' . htmlspecialchars($user->whatsapp_number) . '<br>';
            echo '</p>';
        }
        echo '</div>';

        echo '<button id="chooseTemplateBtn" class="button button-primary">Elegir Template</button>';
        echo '<br>';
        echo '<br>';
        echo '<div id="dynamicFormContainer"></div>';
    } else {
        echo '<p>No se han seleccionado usuarios.</p>';
    }
}
?>
