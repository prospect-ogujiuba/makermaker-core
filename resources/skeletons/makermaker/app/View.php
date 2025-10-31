<?php
namespace MakerMaker;

class View extends \TypeRocket\Template\View
{
    public function init()
    {
        $this->setFolder(TYPEROCKET_PLUGIN_MAKERMAKER_VIEWS_PATH);
    }
}