<?php
function is_mobile(): bool {
    if (isset($_SERVER['HTTP_SEC_CH_UA_MOBILE'])) {
        return ($_SERVER['HTTP_SEC_CH_UA_MOBILE'] === '?1');
    }

    if (empty($_SERVER['HTTP_USER_AGENT'])) {
        return false;
    }

    return str_contains($_SERVER['HTTP_USER_AGENT'], 'Mobile')
        || str_contains($_SERVER['HTTP_USER_AGENT'], 'Android')
        || str_contains($_SERVER['HTTP_USER_AGENT'], 'Silk/')
        || str_contains($_SERVER['HTTP_USER_AGENT'], 'Kindle')
        || str_contains($_SERVER['HTTP_USER_AGENT'], 'BlackBerry')
        || str_contains($_SERVER['HTTP_USER_AGENT'], 'Opera Mini')
        || str_contains($_SERVER['HTTP_USER_AGENT'], 'Opera Mobi');
}
