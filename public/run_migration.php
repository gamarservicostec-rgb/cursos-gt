<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Executando script de migração...\n";
require_once __DIR__ . '/../database/add_course_fields.php';
echo "Fim da execução. Verifique migration_log.txt no mesmo diretório.";
