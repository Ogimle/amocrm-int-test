<?php
    require 'debug_utils.php';
    define('AMOTEST', 1);

    if (!$_POST) {
        $_POST = json_decode(@file_get_contents('php://input'), true);
    }

    function ContactsHandler(array $contacts)
    {
        foreach($contacts as $contact['add']) {
            // todo: записать в примечание контакта crm значение id контакта
        }

        foreach($contacts as $contact['update']) {
            // todo: записать в примечание контакта crm значения измененных полей и дату изменения
        }
    }

    foreach($_POST as $k => $v) {
        $handlerName = ucfirst($k) . 'Handler';
        if (is_callable($handlerName)) {
            call_user_func($handlerName, $v);
        }
    }