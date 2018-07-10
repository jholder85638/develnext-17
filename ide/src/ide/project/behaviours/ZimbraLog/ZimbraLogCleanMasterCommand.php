<?php
namespace ide\project\behaviours\ZimbraLog;

use ide\editors\AbstractEditor;
use ide\misc\AbstractCommand;
use ide\project\behaviours\ZimbraLogBehaviour;

class ZimbraLogCleanMasterCommand extends AbstractCommand
{
    /**
     * @var BackupProjectBehaviour
     */
    private $behaviour;

    /**
     * BackupCleanMasterCommand constructor.
     * @param BackupProjectBehaviour $behaviour
     */
    public function __construct(ZimbraLogBehaviour $behaviour)
    {
        $this->behaviour = $behaviour;
    }

    public function getName()
    {
        return 'delete.all.master.backups::Удалить все мастер-копии';
    }

    public function getCategory()
    {
        return 'backup';
    }

    public function getIcon()
    {
        return 'icons/trash16.gif';
    }

    public function withBeforeSeparator()
    {
        return true;
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        $this->behaviour->clearMasterBackupRequest();
    }

    public function makeUiForHead()
    {
        $button = $this->makeGlyphButton();
        $button->text = $this->getName();
        return $button;
    }
}