<?php
if (getenv('OBJECTSTORE_S3_BUCKET')) {
    $use_ssl = getenv('OBJECTSTORE_S3_SSL');
    $use_path = getenv('OBJECTSTORE_S3_USEPATH_STYLE');
    $use_legacyauth = getenv('OBJECTSTORE_S3_LEGACYAUTH');
    $autocreate = getenv('OBJECTSTORE_S3_AUTOCREATE');
    $key = getenv('OBJECTSTORE_S3_KEY') ?: '';
    $secret = getenv('OBJECTSTORE_S3_SECRET') ?: '';
    if (getenv('OBJECTSTORE_S3_KEY_FILE') && file_exists(getenv('OBJECTSTORE_S3_KEY_FILE'))) {
        $key = trim(file_get_contents(getenv('OBJECTSTORE_S3_KEY_FILE')));
    }
    if (getenv('OBJECTSTORE_S3_SECRET_FILE') && file_exists(getenv('OBJECTSTORE_S3_SECRET_FILE'))) {
        $secret = trim(file_get_contents(getenv('OBJECTSTORE_S3_SECRET_FILE')));
    }
    $CONFIG = array(
        'objectstore' => array(
            'class' => '\OC\Files\ObjectStore\S3',
            'arguments' => array(
                'bucket' => getenv('OBJECTSTORE_S3_BUCKET'),
                'key' => $key,
                'secret' => $secret,
                'region' => getenv('OBJECTSTORE_S3_REGION') ?: '',
                'hostname' => getenv('OBJECTSTORE_S3_HOST') ?: '',
                'port' => getenv('OBJECTSTORE_S3_PORT') ?: '',
                'objectPrefix' => getenv('OBJECTSTORE_S3_OBJECT_PREFIX') ?: 'urn:oid:',
                'storageClass' => getenv('OBJECTSTORE_S3_STORAGE_CLASS') ?: '',
                'autocreate' => (strtolower($autocreate) !== 'false' && $autocreate !== false),
                'use_ssl' => (strtolower($use_ssl) !== 'false' && $use_ssl !== false),
                // required for some non Amazon S3 implementations
                'use_path_style' => (strtolower($use_path) !== 'false' && $use_path !== false),
                // required for older protocol versions
                'legacy_auth' => (strtolower($use_legacyauth) !== 'false' && $use_legacyauth !== false),
            )
        )
    );

    if (getenv('OBJECTSTORE_S3_SSE_C_KEY_FILE') && file_exists(getenv('OBJECTSTORE_S3_SSE_C_KEY_FILE'))) {
        $CONFIG['objectstore']['arguments']['sse_c_key'] = trim(file_get_contents(getenv('OBJECTSTORE_S3_SSE_C_KEY_FILE')));
    } elseif (getenv('OBJECTSTORE_S3_SSE_C_KEY') !== false) {
        $CONFIG['objectstore']['arguments']['sse_c_key'] = (string)getenv('OBJECTSTORE_S3_SSE_C_KEY');
    }
}
