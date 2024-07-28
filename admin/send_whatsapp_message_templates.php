<?php
// /home/<account>/public_html/wp-content/plugins/7-ktz-basic-surveys-and-microlearning/admin/send_whatsapp_message_templates.php

// Aquí va la lógica de tu página para enviar templates de mensajes de WhatsApp.
// Puedes añadir formularios, manejadores de formularios, scripts, etc.

// Llamar al archivo config.php
require_once __DIR__ . '/../config.php';

// Ahora puedes usar las constantes
require_once SUPPORT_FUNCTIONS_FILE;



// Comprueba si es una solicitud AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    if (isset($_POST['users_to_send_message_template'])) {
        $user_ids = explode(', ', sanitize_text_field($_POST['users_to_send_message_template']));
        echo json_encode(['success' => true, 'message' => 'Templates enviados con éxito', 'ids' => $user_ids]);
        exit; // finalizar el script aquí
    }
}


function render_send_whatsapp_message_templates() {
    global $wpdb;

    // Obtener estados de ambas tablas
    $statuses = $wpdb->get_col("SELECT DISTINCT status FROM 7c_survey_status");
    $bookingStatuses = $wpdb->get_col("SELECT DISTINCT status FROM bravo_bookings");
    
    $searchString = $_POST['searchString'] ?? '';
    $statusFilter = isset($_POST['statusFilter']) ? array_map('sanitize_text_field', $_POST['statusFilter']) : [];
    $bookingStatusFilter = isset($_POST['bookingStatusFilter']) ? array_map('sanitize_text_field', $_POST['bookingStatusFilter']) : [];

    // Si todos los campos están vacíos, muestra un mensaje de error
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($searchString) && empty($statusFilter) && empty($bookingStatusFilter)) {
        echo '<div class="notice notice-error"><p>Debes llenar al menos uno de los campos.</p></div>';
        return; // Retorna temprano para evitar procesar el resto del código
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Agregar logs para los filtros
        error_log("[LOG] Filtro de Estado de Menús y Encuestas: " . json_encode($statusFilter));
        error_log("[LOG] Filtro de Estado de Booking: " . json_encode($bookingStatusFilter));

        $user_ids_valid_whatsapp = main_search_function($searchString, $statusFilter, $bookingStatusFilter);
        // Aquí deberás llamar a la función que usa $bookingStatusFilter si es necesario

        $user_information = get_user_information($user_ids_valid_whatsapp);
    
        $unique_user_information = [];
        foreach ($user_information as $user) {
            $unique_user_information[$user['user_id']] = $user;
        }
    }

?>


<div class="wrap">
    <h1>Envío de Templates de WhatsApp</h1>
    <form method="post" action="" class="main-form">
        <div class="sections-container">
            <div class="form-section form-search">
                <div class="form-header">Buscar</div>
                <div class="form-content">
                    <input type="text" name="searchString" id="searchString" placeholder="Introduzca términos de búsqueda">
                </div>
            </div>

            <div class="form-section form-status">
                <div class="form-header">Filtro de Estado de Menús y Encuestas</div>
                <div class="form-content">
                    <select name="statusFilter[]" id="statusFilter" multiple size="3">
                        <?php foreach ($statuses as $status) echo "<option value='{$status}'>{$status}</option>"; ?>
                    </select>
                </div>
            </div>

            <div class="form-section form-booking">
                <div class="form-header">Filtro de Estado de Booking</div>
                <div class="form-content">
                    <select name="bookingStatusFilter[]" id="bookingStatusFilter" multiple size="3">
                        <?php foreach ($bookingStatuses as $status) echo "<option value='{$status}'>{$status}</option>"; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="btn-container">
            <button type="submit" class="button button-primary btn-large">Buscar</button>
        </div>
    </form>

    <div style="height: 20px;"></div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($searchString) && empty($unique_user_information)): ?>
        <h2>No se encontraron resultados para: "<?php echo $searchString; ?>"</h2>
        <div style="height: 10px;"></div>
    <?php endif; ?>
</div>



<!-- CSS Estilos -->
<style>
    .main-form {
        display: flex;
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
    
    .sections-container {
        display: flex;
        flex-direction: row;
        gap: 10px;
        width: 100%;
    }
    
    .form-section {
        border: 1px solid #ccc;
        padding: 10px;
        height: 150px;
        display: flex;
        flex-direction: column;
    }
    
    .form-search {
        flex-basis: 25%;
    }
    
    .form-status, .form-booking {
        flex-basis: 12%;
    }
    
    .form-header {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        border-bottom: 1px solid #ccc;
        margin-bottom: 10px;
        padding-bottom: 5px;
    }
    
    .form-content {
        flex: 2;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: center;
    }
    
    .form-content input[type="text"] {
        width: 100%;
        padding: 8px;
        box-sizing: border-box;
    }
    
    .btn-container {
        margin-top: 10px;
        display: flex;
        justify-content: flex-start;
        width: 12%;  /* Ajusta esto al ancho deseado */
    }
    
    .btn-large {
        width: 100% !important; /* Cambiado del 25% anterior */
        padding: 10px;
        font-size: 1.2em;
    }

    
    .form-content select {
        width: 10vw;
    }
    
    @media (max-width: 768px) {
        .main-form {
            flex-direction: column;
            gap: 15px;
        }
    
        .form-search, .form-status, .form-booking {
            flex-basis: 100%;
        }
    
        .btn-container {
            justify-content: center;
        }
    
        .btn-large {
            width: 80%;
        }
    
        .form-content select {
            width: 80%;
        }
    }

    }
    
</style>






        <?php if (isset($unique_user_information) && !empty($unique_user_information)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col">Seleccionar</th>
                        <th scope="col">ID</th>
                        <th scope="col">Nombre</th>
                        <th scope="col">Apellido</th>
                        <th scope="col">Email</th>
                        <th scope="col">WhatsApp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($unique_user_information as $user): ?>
                        <tr>
                            <td><input type='checkbox' name='user_select[]' value='<?php echo htmlspecialchars(json_encode($user)); ?>'></td>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><?php echo $user['first_name']; ?></td>
                            <td><?php echo $user['last_name']; ?></td>
                            <td><?php echo $user['email']; ?></td>
                            <td><?php echo $user['whatsapp_number']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="height: 20px;"></div>
            <form method="post" action="<?php echo admin_url('admin.php?page=send_whatsapp_message_template_choose_template'); ?>" id="sendTemplateForm">
                <?php foreach ($unique_user_information as $user): ?>
                    <input type="hidden" name="users[]" value="<?php echo htmlspecialchars(json_encode($user)); ?>">
                <?php endforeach; ?>
                <button type="submit" class="button button-primary">Enviar Message Template</button>
            </form>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($user_information)): ?>
            <p>No se encontraron resultados para: "<?php echo $searchString; ?>"</p>
        <?php endif; ?>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                const form = document.getElementById('sendTemplateForm');
                if (form) {
                    form.addEventListener('submit', function(event) {
                        const checkboxes = document.querySelectorAll("input[name='user_select[]']:checked");
                        if (!checkboxes.length) {
                            event.preventDefault();
                            alert('Selecciona al menos un usuario.');
                            return;
                        }
                        checkboxes.forEach(function(checkbox) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'selected_users[]';
                            input.value = checkbox.value;
                            form.appendChild(input);
                        });
                    });
                    observer.disconnect();
                }
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    });
    </script>

    <!-- ... -->
    <div id="reportContainer"></div>
    <!-- ... -->


<?php
// error_log("Fin de la función render_send_whatsapp_message_templates");
}
