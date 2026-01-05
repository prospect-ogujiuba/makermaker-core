<?php
namespace MakermakerCore\Helpers;

use TypeRocket\Models\Model;

class RedirectHelper
{
    public static function afterCreate(string $resourceName)
    {
        return tr_redirect()->toPage($resourceName, 'index');
    }

    public static function afterUpdate(string $resourceName, $modelId)
    {
        return tr_redirect()->toPage($resourceName, 'edit', $modelId);
    }

    public static function backWithErrors(array $errors)
    {
        return tr_redirect()->back()->withErrors($errors);
    }
}
