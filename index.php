<html>
    <head>
        <title>Тестовая страница авторизации</title>
    </head>
    <body>
<?php

require_once 'config.php';
require_once 'TokenStorage.php';
include_once __DIR__ . '/vendor/autoload.php';

use AmoCRM\OAuth2\Client\Provider\AmoCRM;

if (isset($_GET['logout'])) {
    TokenStorage::clear();
    Header('Location: /');
    exit;
}

session_start();

/**
 * Создаем провайдера
 */
$provider = new AmoCRM([
    'baseDomain' => CFG_BASE_DOMAIN,
    'clientId' => CFG_CLIENT_ID,
    'clientSecret' => CFG_CLIENT_SECRET,
    'redirectUri' => CFG_REDIRECT_URI,
]);

$accessToken = TokenStorage::get();

/*
 * Если токен доступа существует, то пытаемся использовать его
 */
if ($accessToken) {
    $provider->setBaseDomain($accessToken->getValues()['baseDomain']);

    // токен доступа просрочен, пытаемся обновить
    if ($accessToken->hasExpired()) {
        // обновляющий токен просрочен, прерываем выполнение, запускаем процесс авторизации
        $refreshToken = $accessToken->getRefreshToken();
        if ($refreshToken->hasExpired()) {
            TokenStorage::clear();
            Header('Location: /'); exit;
        }

        // обновляющий токен валиден, обновляем токен доступа
        try {
            $accessToken = $provider->getAccessToken(new League\OAuth2\Client\Grant\RefreshToken(), [
                'refresh_token' => $refreshToken,
            ]);

            TokenStorage::save([
                'accessToken' => $accessToken->getToken(),
                'refreshToken' => $accessToken->getRefreshToken(),
                'expires' => $accessToken->getExpires(),
                'baseDomain' => $provider->getBaseDomain(),
            ]);
        } catch (Exception $e) {
            die((string)$e);
        }
    }

    /** @var \AmoCRM\OAuth2\Client\Provider\AmoCRMResourceOwner $ownerDetails */
    $ownerDetails = $provider->getResourceOwner($accessToken);
    printf('Hello, %s!', $ownerDetails->getName());
    printf(' <a href="/index.php?logout=1">Выйти</a>');
}
/*
 * Если пришел запрос с кодом, то есть попытка авторизаии, тогда пытаемся получить токен
 */
elseif (isset($_GET['code']) && $_GET['state'] == $_SESSION['oauth2state']) {
    try {
        /** @var \League\OAuth2\Client\Token\AccessToken $access_token */
        $accessToken = $provider->getAccessToken(new League\OAuth2\Client\Grant\AuthorizationCode(), [
            'code' => $_GET['code'],
        ]);

        TokenStorage::save([
            'accessToken' => $accessToken->getToken(),
            'refreshToken' => $accessToken->getRefreshToken(),
            'expires' => $accessToken->getExpires(),
            'baseDomain' => $provider->getBaseDomain(),
        ]);
    } catch (Exception $e) {
        die((string)$e);
    }
    // Возвращаемся на главную
    Header('Location: /'); exit();
}
/*
 * В остальных случаях рисуем кнопку авторизации
 */
else {
    $_SESSION['oauth2state'] = bin2hex(random_bytes(16));
    echo <<<SCRIPT
        <div>
            Здравствуете! Пожалуйста авторизуйтесь.
        </div>
        <script
            class="amocrm_oauth"
            charset="utf-8"
            data-client-id="{$provider->getClientId()}"
            data-title="Установить интеграцию"
            data-compact="false"
            data-class-name="className"
            data-color="default"
            data-state="{$_SESSION['oauth2state']}"
            data-error-callback="handleOauthError"
            src="https://www.amocrm.ru/auth/button.min.js"
        ></script>
        <script>
            handleOauthError = function(event) {
                alert('ID клиента - ' + event.client_id + ' Ошибка - ' + event.error);
            }
        </script>
SCRIPT;
}


//
//$token = $accessToken->getToken();
//
//try {
//    /**
//     * Делаем запрос к АПИ
//     */
//    $data = $provider->getHttpClient()
//        ->request('GET', $provider->urlAccount() . 'api/v2/account', [
//            'headers' => $provider->getHeaders($accessToken)
//        ]);
//
//    $parsedBody = json_decode($data->getBody()->getContents(), true);
//    printf('ID аккаунта - %s, название - %s', $parsedBody['id'], $parsedBody['name']);
//} catch (GuzzleHttp\Exception\GuzzleException $e) {
//    var_dump((string)$e);
//}


?>
    </body>
</html>
