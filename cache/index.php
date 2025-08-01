<?php
// GW2 Community Calendar Cache Protection
// Verhindere direkten Zugriff auf Cache-Verzeichnis
if (!defined('ABSPATH')) {
    http_response_code(403);
    exit('Zugriff verweigert');
}
?> 