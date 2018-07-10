<?php

namespace ide\project\supports\jppm;

use ide\editors\menu\ContextMenu;
use ide\editors\ProjectEditor;
use ide\forms\MessageBoxForm;
use ide\Ide;
use ide\misc\SimpleSingleCommand;
use ide\project\behaviours\BackupProjectBehaviour;
use ide\project\control\AbstractProjectControlPane;
use ide\systems\FileSystem;
use ide\utils\UiUtils;
use php\gui\event\UXMouseEvent;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXLabel;
use php\gui\UXListCell;
use php\gui\UXListView;
use php\gui\UXNode;
use php\gui\UXSeparator;
use php\lib\fs;
use php\time\Time;
use php\util\Locale;

class SystemLogsControlPanel extends AbstractProjectControlPane
{
    /**
     * @var BackupProjectBehaviour
     */
    private $behaviour;

    /**
     * @var UXListView
     */
    private $uiList;

    /**
     * @var UXListView
     */
    private $uiMasterList;

    /**
     * @var ContextMenu
     */
    private $contextMenu;

    public function getName()
    {
        return "zmwatson.ext.name::Архив проекта";
    }

    public function getDescription()
    {
        return "zmwatson.ext.description::Резервные копии";
    }

    public function getIcon()
    {
        return 'icons/backup16.png';
    }


    /**
     * @return UXNode
     */
    protected function makeUi()
    {
        $list = $this->uiList = new UXListView();
        $masterList = $this->uiMasterList = new UXListView();

        $cellFactory = function (UXListCell $cell, Backup $backup = null) {
            $cell->text = null;
            $cell->graphic = null;
            $title = new UXLabel("UXLabel Line 80");
            $title->style = UiUtils::fontSizeStyle() . "; -fx-font-weight: bold;";
            $description = new UXLabel("Crated at Var" .  "Description"
            );
            $title->style = '-fx-text-fill: blue';

            $description->style = '-fx-text-fill: gray; ' . UiUtils::fontSizeStyle();

            $cell->graphic = new UXHBox([
                ico('archive32'),
                new UXVBox([$title, $description], 0)
            ], 10);
            $cell->graphic->padding = 4;

        };

        $masterList->setCellFactory($cellFactory);
        $list->setCellFactory($cellFactory);

        $list->fixedCellSize = $masterList->fixedCellSize = 45;

        $clickHandler = function (UXMouseEvent $e) {
            if ($e->clickCount > 1) {
                //Todo: Handle click event
//                $this->behaviour->restoreFromBackupRequest($e->sender->selectedItem);
            }
        };

        $masterList->on('click', $clickHandler);
        $list->on('click', $clickHandler);

        $masterList->height = $masterList->fixedCellSize * 4 + 2;
        UXVBox::setVgrow($list, 'ALWAYS');

        $label1 = _(new UXLabel("backup.master.copies::Мастер-копии"));
        $label2 = _(new UXLabel("backup.auto.copies::Автоматические копии"));

    }

    /**
     * Refresh ui and pane.
     */
    public function refresh()
    {
        $n = 0;
        $testArray = array();
        while($n<15){
            $testArray[] = "RandomString".$n;
            $n++;
        }

        if ($this->uiList) {
//            $this->uiList->items->setAll($this->behaviour->getAutoBackups());
        }

        if ($this->uiMasterList) {
            $this->uiMasterList->items->setAll($testArray);
        }
    }
}