<?php

return [
    // Ruta al GeoLite2-City.mmdb (compartido entre proyectos del servidor).
    'database_path' => env('GEOIP_DATABASE_PATH', '/opt/shared/geoip/GeoLite2-City.mmdb'),

    // Desactivar si no hay .mmdb (las visitas se registran igual, sin país/ciudad).
    'enabled' => env('GEOIP_ENABLED', true),
];
