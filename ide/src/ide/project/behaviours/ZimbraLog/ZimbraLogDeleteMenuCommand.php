<?php
namespace ide\project\behaviours\ZimbraLog;


class ZimbraLogDeleteMenuCommand extends ZimbraLogMenuCommand
{
    public function getName()
    {
        return 'command.delete::Удалить';
    }

    public function getIcon()
    {
        return 'icons/trash16.gif';
    }

    public function onBackupExecute(ZimbraLog $backup)
    {
        $this->behaviour->deleteBackupRequest($backup);
    }

    public function getAccelerator()
    {
        return 'Delete';
    }

    public function withBeforeSeparator()
    {
        return true;
    }
}