<?php
if (getenv('REDIS_HOST')) {
    $password = '';
    if (getenv('REDIS_HOST_PASSWORD_FILE') && file_exists(getenv('REDIS_HOST_PASSWORD_FILE'))) {
        $password = trim(file_get_contents(getenv('REDIS_HOST_PASSWORD_FILE')));
    } elseif (getenv('REDIS_HOST_PASSWORD') !== false) {
        $password = (string)getenv('REDIS_HOST_PASSWORD');
    }

    $CONFIG = array(
        'memcache.distributed' => '\OC\Memcache\Redis',
        'memcache.locking' => '\OC\Memcache\Redis',
        'redis' => array(
            'host' => getenv('REDIS_HOST'),
            'password' => $password,
        ),
    );

    if (getenv('REDIS_HOST_USER')) {
        $CONFIG['redis']['user'] = (string)getenv('REDIS_HOST_USER');
    }

    if (getenv('REDIS_HOST_PORT') !== false) {
        $CONFIG['redis']['port'] = (int)getenv('REDIS_HOST_PORT');
    } elseif (getenv('REDIS_HOST')[0] != '/') {
        $CONFIG['redis']['port'] = 6379;
    }
}
