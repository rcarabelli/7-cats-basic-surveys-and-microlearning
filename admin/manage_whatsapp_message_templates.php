<?php
// /home/<account>/public_html/wp-content/plugins/7-ktz-basic-surveys-and-microlearning/admin/manage_whatsapp_message_templates.php

// Llamar al archivo config.php
require_once __DIR__ . '/../config.php';

// Ahora puedes usar las constantes
require_once SUPPORT_FUNCTIONS_FILE;

function render_manage_whatsapp_message_templates() {
    global $wpdb; // Objeto de base de datos de WordPress
    
    // Obtener el ID del template seleccionado desde la URL (si existe)
    $selected_template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;
    $selected_template = null;
    
    // Si el formulario ha sido enviado, procesamos la información
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Limpiamos y validamos los datos antes de insertar en la base de datos
        $template_name = sanitize_text_field($_POST['template_name']);
        $template_description = sanitize_textarea_field($_POST['template_description']);
        $header_parameters = intval($_POST['header_parameters']);
        $body_parameters = intval($_POST['body_parameters']);
        
        // Convertimos el array de template_languages en una cadena delimitada por comas
        $template_languages = implode(',', array_map('sanitize_text_field', $_POST['template_languages']));
        
        // Preparamos la información para insertarla en la base de datos
        $table_name = '7c_whatsapp_template_configurations';
        $data = [
            'template_name' => $template_name,
            'template_description' => $template_description,
            'header_parameters' => $header_parameters,
            'body_parameters' => $body_parameters,
            'template_languages' => $template_languages
        ];
        $format = ['%s', '%s', '%d', '%d', '%s'];
        
        // Verificamos si existe un template con el mismo nombre
        $existing_template = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE template_name = %s", $template_name), ARRAY_A);
        
        if ($existing_template && $existing_template['id'] != $selected_template_id) {
            // Actualizamos el template existente
            $wpdb->update($table_name, $data, ['id' => $existing_template['id']], $format, ['%d']);
        } else {
            // Si no existe un template con el mismo nombre, o si estamos editando el mismo template, insertamos o actualizamos
            if ($selected_template_id) {
                $wpdb->update($table_name, $data, ['id' => $selected_template_id], $format, ['%d']);
            } else {
                $wpdb->insert($table_name, $data, $format);
            }
        }
    }
    
    // Obtener los templates configurados.
    $templates = $wpdb->get_results("SELECT * FROM 7c_whatsapp_template_configurations", ARRAY_A);
    
    // Query para obtener los idiomas de la tabla de idiomas.
    $languages = $wpdb->get_results("SELECT * FROM 7c_language_codes", ARRAY_A);
    
    if ($selected_template_id) {
        // Obtener los datos del template seleccionado.
        $selected_template = $wpdb->get_row($wpdb->prepare("SELECT * FROM 7c_whatsapp_template_configurations WHERE id = %d", $selected_template_id), ARRAY_A);
    }
?>
<div class="wrap">
    <h1>Gestión de Templates de WhatsApp</h1>
    
    <!-- Dropdown para seleccionar un Template Configurado -->
    <div class="form-field" style="margin-bottom: 20px; margin-top: 20px;">
        <label for="selected_template">Selecciona un Template Configurado:</label>
        <select name="selected_template" id="selected_template" class="regular-text" onchange="location.href='?page=manage_whatsapp_message_templates&template_id=' + this.value;">
            <option value="">-- Selecciona un Template --</option>
            <?php foreach($templates as $template): ?>
                <option value="<?= esc_attr($template['id']) ?>" <?= $selected_template_id == $template['id'] ? 'selected' : '' ?>><?= esc_html($template['template_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <!-- Formulario Principal para la carga de parámetros de Templates -->
    <form method="post" action="" style="max-width: 600px;">
        <h2>Formulario de carga de parámetros de Templates</h2>
        
        <!-- Nombre del Template -->
        <div class="form-field form-required" style="margin-bottom: 20px;">
            <label for="template_name">Nombre del Template:</label>
            <input type="text" name="template_name" id="template_name" class="regular-text" value="<?= esc_attr($selected_template['template_name'] ?? '') ?>" required>
        </div>
        
        <!-- Descripción del Template -->
        <div class="form-field" style="margin-bottom: 20px;">
            <label for="template_description">Descripción del Template:</label>
            <textarea name="template_description" id="template_description" class="regular-text" rows="4" required><?= esc_textarea($selected_template['template_description'] ?? '') ?></textarea>
        </div>
        
        <!-- Número de parámetros del Header -->
        <div class="form-field" style="margin-bottom: 20px;">
            <label for="header_parameters">Número de parámetros del Header:</label>
            <input type="number" name="header_parameters" id="header_parameters" class="small-text" min="0" value="<?= esc_attr($selected_template['header_parameters'] ?? '') ?>" required>
        </div>
        
        <!-- Número de parámetros del Body -->
        <div class="form-field" style="margin-bottom: 20px;">
            <label for="body_parameters">Número de parámetros del Body:</label>
            <input type="number" name="body_parameters" id="body_parameters" class="small-text" min="0" value="<?= esc_attr($selected_template['body_parameters'] ?? '') ?>" required>
        </div>
        
        <!-- Idioma del Template -->
        <div class="form-field" style="margin-bottom: 20px;">
            <label for="template_languages">Idioma del Template:</label>
            <select name="template_languages[]" id="template_languages" multiple class="regular-text" style="height: 150px; width: 100%;">
                <?php foreach($languages as $language): ?>
                    <option value="<?= esc_attr($language['language_code']) ?>" <?= in_array($language['language_code'], explode(',', $selected_template['template_languages'] ?? '')) ? 'selected' : '' ?>><?= esc_html($language['language_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="submit">
            <input type="submit" value="Guardar Template" class="button button-primary">
        </div>
    </form>
</div>
<?php
}

