<?php
namespace ide\project\behaviours\ZimbraLog;


use ide\editors\AbstractEditor;
use ide\misc\AbstractCommand;
use ide\project\behaviours\ZimbraLogBehaviour;

class ZimbraLogCreateMasterCommand extends AbstractCommand
{
    /**
     * @var BackupProjectBehaviour
     */
    private $behavior;

    /**
     * BackupCreateMasterCommand constructor.
     * @param ZimbraLogProjectBehaviour $behavior
     */
    public function __construct(ZimbraLogBehaviour $behavior)
    {
        $this->behavior = $behavior;
    }

    public function getIcon()
    {
        return 'icons/backup16.png';
    }

    public function getCategory()
    {
        return 'backup';
    }

    public function getName()
    {
        return 'command.create.master.backup::Создать мастер-копию';
    }

    public function getAccelerator()
    {
        return 'Ctrl + Alt + S';
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        $this->behavior->makeMasterBackupRequest();
    }
}