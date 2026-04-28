<?php
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'u569083206_sme');
define('DB_PORT', 3306);
define('PLANEJAMENTO_DB_HOST', DB_HOST);
define('PLANEJAMENTO_DB_USER', DB_USER);
define('PLANEJAMENTO_DB_PASS', DB_PASS);
define('PLANEJAMENTO_DB_NAME', 'u569083206_planejamento');
define('PLANEJAMENTO_DB_PORT', DB_PORT);
define('COMPRAS_DB_HOST', DB_HOST);
define('COMPRAS_DB_USER', DB_USER);
define('COMPRAS_DB_PASS', DB_PASS);
define('COMPRAS_DB_NAME', getenv('COMPRAS_DB_NAME') ?: 'compras');
define('COMPRAS_DB_PORT', DB_PORT);
define('SESSION_NAME', 'saeducacao_sess');

define('GOVBR_SIGN_ENABLED', filter_var(getenv('GOVBR_SIGN_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN));
define('GOVBR_SIGN_ENV', getenv('GOVBR_SIGN_ENV') ?: 'staging');
define('GOVBR_SIGN_CLIENT_ID', getenv('GOVBR_SIGN_CLIENT_ID') ?: '');
define('GOVBR_SIGN_CLIENT_SECRET', getenv('GOVBR_SIGN_CLIENT_SECRET') ?: '');
define('GOVBR_SIGN_REDIRECT_URI', getenv('GOVBR_SIGN_REDIRECT_URI') ?: '');
define('GOVBR_SIGN_SCOPE', getenv('GOVBR_SIGN_SCOPE') ?: 'sign');
define('GOVBR_SIGN_AUTHORIZE_URL', getenv('GOVBR_SIGN_AUTHORIZE_URL') ?: '');
define('GOVBR_SIGN_TOKEN_URL', getenv('GOVBR_SIGN_TOKEN_URL') ?: '');
define('GOVBR_SIGN_SIGN_URL', getenv('GOVBR_SIGN_SIGN_URL') ?: '');
define('GOVBR_SIGN_CERT_URL', getenv('GOVBR_SIGN_CERT_URL') ?: '');
