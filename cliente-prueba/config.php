<?php
// Configuración cliente prueba — lee variables de docker-compose.
declare(strict_types=1);

return [
    // URLs internas — usadas por firmar.php / verificar.php / fecha-hora.php
    // para hablar con WildFly directamente vía Docker DNS (no atraviesa nginx).
    'api_base'      => getenv('FIRMAEC_API_BASE')      ?: 'http://wildfly:8080/api',
    'servicio_base' => getenv('FIRMAEC_SERVICIO_BASE') ?: 'http://wildfly:8080/servicio',

    // URL pública vista por el navegador del usuario (vía nginx).
    // Mostrada en la pestaña Endpoints del UI.
    'public_base'   => getenv('FIRMAEC_PUBLIC_BASE')   ?: 'http://localhost:8080',

    'sistema'       => getenv('FIRMAEC_SISTEMA')       ?: 'ClienteDemo',
    'api_key'       => getenv('FIRMAEC_API_KEY')       ?: '',

    'db_host'       => getenv('FIRMAEC_DB_HOST')       ?: 'postgres',
    'db_name'       => getenv('FIRMAEC_DB_NAME')       ?: 'firmaec',
    'db_user'       => getenv('FIRMAEC_DB_USER')       ?: 'firmaec_app',
    'db_pass'       => getenv('FIRMAEC_DB_PASS')       ?: '',
];
