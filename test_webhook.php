<?php
echo "étape 1 OK\n";
require_once __DIR__ . '/includs/env_loader.php';
echo "étape 2 OK\n";
echo env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', 'NON TROUVÉ');