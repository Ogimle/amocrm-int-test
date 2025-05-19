<?php

use AmoCRM\Collections\NotesCollection;
use AmoCRM\Exceptions\AmoCRMApiErrorResponseException;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Client\AmoCRMApiClient;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$_POST) {
    $_POST = json_decode(@file_get_contents('php://input'), true);
}

function dlog() {
    $lfile = __DIR__.'/tmp/dbg.log';
    $args = func_get_args();

    if ($args[0]=='reset') {
        $f = fopen($lfile, 'r+');
        ftruncate($f,0);
        fclose($f);
        array_shift($args);
    }

    if (count($args)) {
        $t = date('d.m.Y H:i:s');
        $out = '['.$t.']'.(count($args)>1?"\n":'');
        foreach($args as $m) {
            $out .= sprintf("\t%s%s",
                @var_export($m, true),
                (is_string($m) && preg_match('/\S+=$/', $m) ? '' : "\n")
            );
        }
        error_log($out, 3, $lfile);
    }
}

function compactCustomFields(array $fields)
{
    return array_reduce($fields, function($acc, $itm) {
        $acc .= sprintf("%s: %s\n", $itm['name'], $itm['values'][0]['value']);
        return $acc;
    }, '');
}

function sendNotesToCRM(NotesCollection $notesCollection, string $nType, AmoCRMApiClient $api)
{
    $notesService = $api->notes($nType);

    try {
        $notesService->add($notesCollection);
    } catch (AmoCRMApiException $e) {
        $validationErrors = null;
        if ($e instanceof AmoCRMApiErrorResponseException) {
            $validationErrors = var_export($e->getValidationErrors(), true);
        }
        dlog(
            'title=', $e->getTitle(),
            'code=', $e->getCode(),
            'debugInfo=', $e->getLastRequestInfo(),
            'validationErrors=', $validationErrors
        );
    }
}
