console.log('plugin-scripts.js is loaded'); // Para confirmar si el archivo se está cargando


//Función que crea el formulario en java (jalado por AJAX) para llenar los datos del message template a enviar
function buildForm(template, selectedUsers) {
    console.log('Entering buildForm...');
    console.log('buildForm is called with arguments:', template, selectedUsers);
    let formHtml = '<form id="dynamicForm" class="wrap">'; // Clase 'wrap' para contenedores principales en WP Admin
    
    formHtml += '<h2 style="margin-top: 20px;">Llena los datos y envía el template a los usuarios elegidos</h2>'; // Subtítulo
    
    // Bloque 1: Header
    let headerParamsCount = parseInt(template.header_parameters);
    formHtml += '<div class="block"><h3>Header</h3>';
    for(let i=0; i<headerParamsCount; i++) {
        formHtml += '<input type="text" name="headerParam' + i + '" placeholder="Header Param ' + (i+1) + '" class="regular-text"/>'; // Clase 'regular-text' para campos de texto en WP Admin
    }
    formHtml += '</div>';
    
    // Bloque 2: Body
    let bodyParamsCount = parseInt(template.body_parameters);
    formHtml += '<div class="block"><h3>Body</h3>';
    for(let i=0; i<bodyParamsCount; i++) {
        formHtml += '<input type="text" name="bodyParam' + i + '" placeholder="Body Param ' + (i+1) + '" class="regular-text"/>';
    }
    formHtml += '</div>';
    
    // Bloque 3: Language
    let languages = template.template_languages.split(',');
    formHtml += '<div class="block"><h3>Choose the language to send the template</h3><select name="language" class="postform">'; // Clase 'postform' para selects en WP Admin
    languages.forEach(lang => {
        formHtml += '<option value="' + lang + '">' + lang + '</option>';
    });
    formHtml += '</select></div>';
    
    // Botón de envío
    formHtml += '<button type="button" id="submitDynamicForm" class="button button-primary" style="margin-top: 10px;">Enviar</button>'; // Clases 'button' y 'button-primary' para botones en WP Admin
    
    formHtml += '</form>';
    
    // Insertar formulario en la página
    document.getElementById('dynamicFormContainer').innerHTML = formHtml;
    
    // Añadir event listener al botón 'submitDynamicForm' después de que el formulario ha sido insertado en el DOM
    document.getElementById('submitDynamicForm').addEventListener('click', function() {
        console.log('Form Submitted!');
        
        const headerParams = [];
        document.querySelectorAll('#dynamicForm input[name^="headerParam"]').forEach(input => {
            headerParams.push(input.value);
        });
        
        const bodyParams = [];
        document.querySelectorAll('#dynamicForm input[name^="bodyParam"]').forEach(input => {
            bodyParams.push(input.value);
        });
        
        const language = document.querySelector('#dynamicForm select[name="language"]').value;
        
        const data = {
            action: 'insert_template_log', // Aquí va el nombre de la acción que has definido en PHP.
            template_id: template.id, 
            user_ids: selectedUsers.join(','), // Asegurándonos de que estamos enviando solo los IDs de usuario.
            header_variables: headerParams.join(','),
            body_variables: bodyParams.join(','),
            language: language
        };
        
        // Envía el objeto 'data' a tu servidor usando fetch.
        console.log('About to make the fetch call:', ajaxurl, data); // Add this line
        fetch(ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest' // Este header es importante para que WordPress reconozca la solicitud como AJAX
            },
            body: new URLSearchParams(data).toString() // Convertimos el objeto de datos a una string de parámetros URL codificados.
        })
        .then(response => response.text()) // Obtén el texto de la respuesta primero
            .then(text => {
                console.log('Raw Response:', text); // Loguea el texto crudo de la respuesta para depuración
                
                // Reemplaza doble barra invertida con una sola barra invertida antes de parsear el JSON.
                const correctedText = text.replace(/\\\\/g, '\\');
                console.log('Corrected Response:', correctedText); // Loguea el texto corregido para depuración
                
                try {
                    return JSON.parse(correctedText); // Intenta parsear el texto corregido como JSON
                } catch (e) {
                    // Si hay un error al parsear el JSON, lóguealo y lanza el error
                    console.error('Error parsing JSON:', e, 'Corrected Text:', correctedText);
                    throw e;
                }
            })
            .then(data => {
                if(data.success) {
                    console.log('Data successfully sent to the server:', data);
                } else {
                    console.error('Error sending data to server:', data);
                }
            })
            .catch(error => console.error('There was an error sending the data to the server:', error));

    });
        console.log('Exiting buildForm...');

}

// Tu código jQuery existente
jQuery(document).ready(function($) {
    var mediaUploaders = {};
    // ...
    // Resto de tu código jQuery
});



// Operaciones para que el sistema esté atento a la inserción del message template
document.addEventListener('DOMContentLoaded', function() {
    // Intentar obtener el elemento con ID 'chooseTemplateBtn'
    const chooseTemplateBtn = document.getElementById('chooseTemplateBtn');

    // Si el elemento existe, entonces añadimos el event listener
    if (chooseTemplateBtn) {
        chooseTemplateBtn.addEventListener('click', function() {
            console.log('Botón "Elegir Template" fue presionado.');

            // Obtenemos el template ID seleccionado
            const selectedTemplateId = document.querySelector('input[name="template"]:checked').value;
            console.log('ID del template seleccionado:', selectedTemplateId);

            // Obtenemos los usuarios seleccionados
            const selectedUsers = [...document.querySelectorAll('.userDetailsDebug p')].map(pElement => {
                const match = pElement.innerText.match(/ID: (\d+)/);
                if (match && match[1]) return match[1];
            });
            console.log('Usuarios seleccionados:', selectedUsers);

            // Hacemos la solicitud fetch (AJAX)
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    action: 'choose_template',
                    templateId: selectedTemplateId,
                    selectedUsers: selectedUsers
                }).toString()
            })
            .then(response => {
                console.log('Respuesta recibida del servidor:', response);
                return response.json();
            })
            .then(data => {
                console.log("Objeto completo data:", data);
                console.log("Estatus success:", data.success);
                console.log("Objeto template:", data.data && data.data.template);
                console.log("Array selectedUsers:", data.data && data.data.selectedUsers);
                
                // Aquí es donde debes hacer la conversión si es necesario
                if (typeof data.data.selectedUsers === 'string') {
                    data.data.selectedUsers = data.data.selectedUsers.split(',').map(Number);
                }
            
                if(!data) {
                    console.error('El objeto data no está presente.');
                } else if (!data.success) {
                    console.error('El estatus success no es verdadero.');
                } else if (!data.data || !data.data.template) {
                    console.error('El objeto "template" no se devolvió correctamente.');
                } else if (!data.data || !data.data.selectedUsers) {
                    console.error('El objeto "selectedUsers" no se devolvió correctamente.');
                } else {
                    buildForm(data.data.template, data.data.selectedUsers);
                }
            })
            .catch(error => {
                console.error('Error en la solicitud:', error);
            });
        });
    } else {
        console.warn("El botón 'chooseTemplateBtn' no se encontró en la página.");
    }
});



// Listener para la pagina /home/xxxx/public_html/wp-content/plugins/7-ktz-basic-surveys-and-microlearning/admin/send_whatsapp_message_template_manual_trigger.php
document.addEventListener('DOMContentLoaded', function() {
    const sendDueTemplatesBtn = document.querySelector('button[name="send_due_templates"]');
    
    if (sendDueTemplatesBtn) {
        sendDueTemplatesBtn.addEventListener('click', function(event) {
            event.preventDefault();

            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    action: 'send_due_templates'
                }).toString()
            })
            .then(response => response.json())
            .then(data => {
                // Puedes mostrar un mensaje basado en la respuesta
                alert(data.data.message);
            })
            .catch(error => {
                console.error('Error en la solicitud:', error);
            });
        });
    }
});

