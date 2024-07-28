<?php

// Hook para agregar un ítem al menú de administración
add_action('admin_menu', 'cats_custom_menu_page');

function cats_custom_menu_page() {
    // Menú principal para Surveys
    add_menu_page('7C Surveys', '7C Surveys', 'manage_options', 'cats_basic_surveys', 'cats_menu_page_render', 'dashicons-feedback');

    // Submenús para Surveys
    add_submenu_page('cats_basic_surveys', 'Create and Manage Surveys/Courses', 'Create Survey/Course', 'manage_options', 'create_and_manage_survey', 'render_create_and_manage_survey');
    // Aquí, establecer 'cats_basic_surveys' como el parent_slug para Add Questions y Add Answers
    add_submenu_page('cats_basic_surveys', 'Add Questions to Survey/Course', 'Add Questions', 'manage_options', 'add_questions_to_survey', 'render_add_questions_to_survey');
    add_submenu_page('cats_basic_surveys', 'Add Answers to Survey/Course', 'Add Answers', 'manage_options', 'add_answers_to_survey_question', 'render_add_answers_to_survey_question');
    add_submenu_page('cats_basic_surveys', 'Send Survey to User', 'Send Survey & Course', 'manage_options', 'send_survey_to_user', 'render_send_survey_to_user');
    
    // Menú principal para funciones de WhatsApp
    add_menu_page('7C WA Functions', '7C WA Functions', 'manage_options', 'whatsapp_functions', 'render_whatsapp_functions', 'dashicons-whatsapp');
    
    // Submenús para funciones de WhatsApp
    add_submenu_page('whatsapp_functions', 'Manage WA Template', 'Manage WA Templates', 'manage_options', 'manage_whatsapp_message_templates', 'render_manage_whatsapp_message_templates');
    add_submenu_page('whatsapp_functions', 'Send WA Template', 'Send WA Templates', 'manage_options', 'send_whatsapp_message_templates', 'render_send_whatsapp_message_templates');
    add_submenu_page('whatsapp_functions', 'Choose WA Template', 'Choose WA Template', 'manage_options', 'send_whatsapp_message_template_choose_template', 'render_send_whatsapp_message_template_choose_template');
    add_submenu_page('whatsapp_functions', 'Send WA Template Manually', 'Send WA Template Manually', 'manage_options', 'send_whatsapp_message_template_manual_trigger', 'send_whatsapp_message_template_manual_trigger_page');
}


function cats_menu_page_render() {
?>
    <div class="wrap">
        <h1>Módulo de creación y envío de encuestas</h1>
        <h2>Funcionalidad para Board Support + WhatsApp Business Cloud API</h2>
        <p>El módulo permite crear múltiples encuestas con múltiples preguntas de respuesta 1 a 5 (elige una opción) para luego enviarlas a usuarios de Board Support a través de una integración de WhatsApp Business API.</p>
        <div class="content-container">
            <div class="video-container">
                <iframe src="https://www.youtube.com/embed/22Ow2zVT2Y0?rel=0" frameborder="0" allowfullscreen></iframe>
            </div>
            <div class="copyright">
                &copy; 7 Cats Studio - 2023 Todos los derechos reservados. Versión 0.9
            </div>
        </div>
        <style>
            .content-container {
                position: relative;
                overflow: hidden;
            }
            .video-container {
                width: 100%; 
                padding-bottom: 39.25%; 
                position: relative;
            }
            .video-container iframe {
                position: absolute;
                top: 0;
                left: 15%; /* Centrar el iframe que tiene un width del 70% */
                width: 70%; 
                height: 100%; 
            }
            .copyright {
                font-size: 1em;
                margin-top: 10px;
                padding-left: 10px;
            }
        </style>
    </div>
<?php
}