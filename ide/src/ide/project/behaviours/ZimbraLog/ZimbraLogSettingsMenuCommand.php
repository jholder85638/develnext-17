<?php
namespace ide\project\behaviours\ZimbraLog;

use ide\editors\AbstractEditor;
use ide\Ide;
use ide\misc\AbstractCommand;
use ide\project\behaviours\ZimbraLogBehaviour;

class ZimbraLogSettingsMenuCommand extends AbstractCommand
{
    /**
     * @var BackupProjectBehaviour
     */
    private $behaviour;

    /**
     * BackupSettingsMenuCommand constructor.
     * @param BackupProjectBehaviour $behaviour
     */
    public function __construct(ZimbraLogBehaviour $behaviour)
    {
        $this->behaviour = $behaviour;
    }

    public function getCategory()
    {
        return 'backup';
    }

    public function withBeforeSeparator()
    {
        return true;
    }

    public function getName()
    {
        return 'backup.settings::Настройки архивирования';
    }

    public function getIcon()
    {
        return 'icons/settings16.png';
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        $form = new ZimbraLogSettingsForm();
        $config = $this->behaviour->getConfig();

        $form->setResult($config->getProperties());

        if ($form->showDialog() && $form->getResult()) {
            $config->setProperties($form->getResult());
            $config->save();

            Ide::toast('backup.message.settings.are.saved::Настройки архивирования успешно сохранены.');
        }
    }
}