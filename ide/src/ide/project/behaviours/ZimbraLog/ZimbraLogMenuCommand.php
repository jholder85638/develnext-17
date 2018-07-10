<?php
namespace ide\project\behaviours\ZimbraLog;

use ide\editors\AbstractEditor;
use ide\editors\menu\AbstractMenuCommand;
use ide\project\behaviours\ZimbraLogBehaviour;
use timer\AccurateTimer;

abstract class ZimbraLogMenuCommand extends AbstractMenuCommand
{
    /**
     * @var callable
     */
    private $backupGetter;

    /**
     * @var BackupProjectBehaviour
     */
    protected $behaviour;

    /**
     * BackupDeleteMenuCommand constructor.
     * @param BackupProjectBehaviour $behaviour
     * @param callable $backupGetter
     */
    public function __construct(ZimbraLogBehaviour $behaviour, callable $backupGetter)
    {
        $this->backupGetter = $backupGetter;
        $this->behaviour = $behaviour;
    }

    abstract public function onBackupExecute(ZimbraLog $backup);

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        $backup = call_user_func($this->backupGetter);

        if ($backup instanceof ZimbraLog) {
            $this->onBackupExecute($backup);
        }
    }

    /**
     * @param \php\gui\UXMenu|\php\gui\UXMenuItem $item
     * @param AbstractEditor|null $editor
     */
    public function onBeforeShow($item, AbstractEditor $editor = null)
    {
        $backup = call_user_func($this->backupGetter);
        $item->enabled = $backup instanceof ZimbraLog;
    }

    public function makeUiForHead()
    {
        $ui = parent::makeUiForHead();
        $ui->text = $this->getName();

        $backupGetter = $this->backupGetter;

        $timer = new AccurateTimer(100, function () use ($ui, $backupGetter) {
            $backup = call_user_func($backupGetter);
            $ui->enabled = $backup instanceof ZimbraLog;
        });
        $timer->start();

        $ui->observer('parent')->addListener(function ($_, $new) use ($timer) {
            if (!$new) {
                $timer->free();
            }
        });

        return $ui;
    }
}