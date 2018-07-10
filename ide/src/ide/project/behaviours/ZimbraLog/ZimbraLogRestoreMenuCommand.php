<?php
namespace ide\project\behaviours\ZimbraLog;

use ide\editors\AbstractEditor;
use ide\editors\menu\AbstractMenuCommand;
use ide\project\behaviours\ZimbraLogBehaviour;

class ZimbraLogRestoreMenuCommand extends ZimbraLogMenuCommand
{
    public function getName()
    {
        return 'command.restore::Восстановить';
    }

    public function getIcon()
    {
        return 'icons/return16.png';
    }

    public function onBackupExecute(ZimbraLog $backup)
    {
        $this->behaviour->restoreFromBackupRequest($backup);
    }
}