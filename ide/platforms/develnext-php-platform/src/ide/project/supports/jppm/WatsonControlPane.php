<?php
namespace ide\project\supports\jppm;

use ide\project\control\AbstractProjectControlPane;
use php\gui\UXLabel;
use php\gui\UXNode;

class WatsonControlPane extends AbstractProjectControlPane
{
    public function getName()
    {
        return 'System Overview';
    }

    public function getDescription()
    {
        return 'Enviroment Information';
    }

    public function getIcon()
    {
        return 'icons/gameMonitor.png';
    }

    /**
     * @return UXNode
     */
    protected function makeUi()
    {
        return new UXLabel("Test MakeUI");
    }

    /**
     * Refresh ui and pane.
     */
    public function refresh()
    {
    }
}