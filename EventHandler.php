<?php
    require_once 'TokenStorage.php';
    require_once 'utils.php';
    require_once 'config.php';
    include_once __DIR__ . '/vendor/autoload.php';

    use AmoCRM\Collections\NotesCollection;
    use AmoCRM\Helpers\EntityTypesInterface;
    use AmoCRM\Models\NoteType\CommonNote;
    use AmoCRM\Client\AmoCRMApiClient;
    use League\OAuth2\Client\Token\AccessTokenInterface;

    /*
     * Если нет актуального токена, то прекращаем выполнение
     */
    $accessToken = TokenStorage::get();

    if (!$accessToken) {
        exit;
    }

    if ($accessToken->hasExpired()) {
        exit;
    }

    /*
     * Выполняем обработку запроса
     */
    $apiClient = new AmoCRMApiClient(CFG_CLIENT_ID, CFG_CLIENT_SECRET, CFG_REDIRECT_URI);
    $apiClient->setAccessToken($accessToken)
        ->setAccountBaseDomain($accessToken->getValues()['baseDomain'])
        ->onAccessTokenRefresh(
            function (AccessTokenInterface $accessToken, string $baseDomain) {
                TokenStorage::save(
                    [
                        'accessToken' => $accessToken->getToken(),
                        'refreshToken' => $accessToken->getRefreshToken(),
                        'expires' => $accessToken->getExpires(),
                        'baseDomain' => $baseDomain,
                    ]
                );
            }
        );

    /*
     * Обработчик событий контактов
     */
    function ContactsHandler(array $contacts)
    {
        global $apiClient;

        $notesCollection = new NotesCollection();

        foreach ($contacts['add'] as $contact) {
            $commonNote = new CommonNote();
            $commonNote->setEntityId($contact['id'])
                ->setText(
                    'Имя контакта : '. $contact['name'] . "\n"
                        . 'Оветственный ID: ' . $contact['responsible_user_id'] . "\n"
                        . 'Создано: ' . date('d.m.Y H:i:s', $contact['created_at'])
                )
                ->setCreatedBy(0);
            $notesCollection->add($commonNote);
        }

        foreach ($contacts['update'] as $contact) {
            $commonNote = new CommonNote();
            $text = 'Контакт обновлен: ' . date('d.m.Y H:i:s', $contact['updated_at']) . "\n"
                . compactCustomFields($contact['custom_fields']);
            $commonNote->setEntityId($contact['id'])
                ->setText($text)
                ->setCreatedBy(0);
            $notesCollection->add($commonNote);
        }

        sendNotesToCRM($notesCollection, EntityTypesInterface::CONTACTS, $apiClient);
    }

    /*
     * Обработчик событий сделок
     */
    function LeadsHandler(array $leads)
    {
        global $apiClient;

        $notesCollection = new NotesCollection();

        foreach ($leads['add'] as $lead) {
            $commonNote = new CommonNote();
            $commonNote->setEntityId($lead['id'])
                ->setText(
                    'Имя сделки : '. $lead['name'] . "\n"
                    . 'Оветственный ID: ' . $lead['responsible_user_id'] . "\n"
                    . 'Создано: ' . date('d.m.Y H:i:s', $lead['created_at'])
                )
                ->setCreatedBy(0);
            $notesCollection->add($commonNote);
        }

        foreach ($leads['update'] as $lead) {
            $commonNote = new CommonNote();
            $text = 'Сделка обновлена: ' . date('d.m.Y H:i:s', $lead['updated_at']);
            $commonNote->setEntityId($lead['id'])
                ->setText($text)
                ->setCreatedBy(0);
            $notesCollection->add($commonNote);
        }

        sendNotesToCRM($notesCollection, EntityTypesInterface::LEADS, $apiClient);
    }

    /*
     * По ключам массива генерирум имена обработчико и вызываем их если есть
     */
    foreach($_POST as $k => $v) {
        $handlerName = ucfirst($k) . 'Handler';
        if (is_callable($handlerName)) {
            call_user_func($handlerName, $v);
        }
    }