<?php
// /home/<account>/public_html/wp-content/plugins/7-ktz-basic-surveys-and-microlearning/config.php

// Define constant for the plugin directory using dynamic path
define('MY_PLUGIN_PATH', __DIR__ . '/');

// Define constant for the support functions file using dynamic path
define('SUPPORT_FUNCTIONS_FILE', MY_PLUGIN_PATH . '../../../chat/include/functions.php');

// Include wp-load.php to make WordPress functions available
require_once(MY_PLUGIN_PATH . '../../../wp-load.php');

// Añadir las funciones para el motor de búsqueda
require_once MY_PLUGIN_PATH . '/includes/search_engine_functions.php';

// Añadir funciones para manejo de base de datos varias que no sean específicas de una funcionalidad que requiera tener eso concentrado
require_once MY_PLUGIN_PATH . 'includes/database_operations.php';
