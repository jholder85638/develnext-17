<?php

namespace ide\project\behaviours;

use ide\editors\ProjectEditor;
use ide\formats\ProjectFormat;
use ide\forms\InputMessageBoxForm;
use ide\forms\MessageBoxForm;
use ide\Ide;
use ide\IdeConfiguration;
use ide\Logger;
use ide\misc\SimpleSingleCommand;
use ide\project\AbstractProjectBehaviour;
use ide\project\behaviours\ZimbraLog\ZimbraLog;
use ide\project\behaviours\ZimbraLog\ZimbraLogConfiguration;
use ide\project\behaviours\ZimbraLog\ZimbraLogCreateMasterCommand;
use ide\project\behaviours\ZimbraLog\ZimbraLogProjectControlPane;
use ide\project\behaviours\ZimbraLog\ZimbraLogSettingsMenuCommand;
use ide\project\Project;
use ide\project\ProjectExporter;
use ide\project\ProjectImporter;
use ide\systems\FileSystem;
use ide\systems\ProjectSystem;
use php\compress\ZipException;
use php\lib\arr;
use php\lib\fs;
use php\lib\str;
use php\time\Time;
use php\time\Timer;
use php\util\Regex;

/**
 * Class ZimbraLogBehaviour
 * @package ide\project\behaviours
 */
class ZimbraLogBehaviour extends AbstractProjectBehaviour
{
    /**
     * @var ZimbraLogControlPane
     */
    private $controlPane;

    /**
     * @var Timer
     */
    protected $timer;

    /**
     * @var BackupConfiguration
     */
    protected $config;

    /**
     * ...
     */
    public function inject()
    {
        $this->project->on('open', [$this, 'doOpen']);
        $this->project->on('save', [$this, 'doSave']);
        $this->project->on('export', [$this, 'doExport']);
        $this->project->on('close', [$this, 'doClose']);

        $this->project->on('execute', [$this, 'doExecute']);

        $this->config = new ZimbraLogConfiguration($this->project->getIdeFile("backup.conf"));
    }

    /**
     * @return BackupConfiguration
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return \php\io\File
     */
    public function getBackupDir()
    {
        return $this->project->getIdeFile('backup');
    }

    public function doExecute()
    {
        //$this->makeDefaultMasterBackup();
    }

    public function doOpen()
    {
        $this->config->load();

        $this->timer = Timer::every('1s', [$this, 'doBackup']);

        $this->controlPane = new ZimbraLogProjectControlPane($this);

        /** @var ProjectFormat $projectFormat */
        if ($projectFormat = Ide::get()->getRegisteredFormat(ProjectFormat::class)) {
            $projectFormat->addControlPane($this->controlPane);
        }

        $this->makeMenu();

        fs::makeDir($this->getBackupDir());

        // Удаляем лишние бэкапы от предыдущей сессии.
        $newBackups = [];

        foreach ($this->getAutoBackups() as $backup) {
            if ($backup->isNew()) {
                $newBackups[] = $backup;
            }
        }

        foreach (flow($newBackups)->skip(1) as $backup) {
            $this->deleteBackup($backup);
        }

        if ($firstBackup = $newBackups[0]) {
            $firstBackup->setNew(false);
            $this->saveBackupProperties($firstBackup);
        }

        foreach (flow($this->getAutoBackups())
                     ->find(function (ZimbraLog $backup) { return !$backup->isNew(); })
                     ->skip($this->config->getAutoAmountMax()) as $backup) {
            $this->deleteBackup($backup);
        }

        if ($this->config->isAutoOpenTrigger()) {
            $this->makeAutoBackup();
        }
    }

    public function doSave()
    {
        $this->config->save();

        //$this->makeDefaultMasterBackup();
    }

    /**
     * Обновить UI backup pane, если необходимо.
     */
    public function refreshRequest()
    {
        $editor = FileSystem::getSelectedEditor();

        if ($editor instanceof ProjectEditor) {
            uiLater(function () use ($editor) {
                $editor->refresh();
            });
        }
    }

    public function doExport(ProjectExporter $exporter)
    {
        $exporter->removeDirectory($this->getBackupDir());
    }

    public function doClose()
    {
        if ($this->timer) {
            $this->timer->cancel();
        }

        if ($this->config->isAutoCloseTrigger()) {
            $this->makeAutoBackup();
        }
    }

    /**
     * @param Backup $backup
     */
    public function saveBackupProperties(ZimbraLog $backup)
    {
        $file = $backup->getFilename();

        $config = new IdeConfiguration("$file.properties");

        $properties = $backup->getProperties();

        foreach ($properties as $code => $value) {
            $config->set($code, $value);
        }

        $config->saveFile();
    }

    /**
     * Создать автоматический бэкап.
     *
     * @return Backup
     */
    public function makeAutoBackup()
    {
        ProjectSystem::saveOnlyRequired();

        $exporter = $this->project->makeExporter();

        $now = Time::now();

        $date = $now->toString('hhmmss-yyyymmdd');

        try {
            $exporter->save($file = "{$this->getBackupDir()}/auto/Backup-$date.zip");
        } catch (ZipException $e) {
            Logger::warn("Failed to create backup '$file', {$e->getMessage()}");
            return null;
        }

        $backup = new ZimbraLog();
        $backup->setName($now->toString('dd MMM, HH:mm:ss, yyyy'));
        $backup->setDescription('Auto-Backup');
        $backup->setCreatedAt(Time::millis());
        $backup->setFilename($file);
        $backup->setNew(true);
        $backup->setMaster(false);

        $this->saveBackupProperties($backup);

        $startTime = Ide::get()->getStartTime();
        $sessionBackups = [];

        foreach ($this->getAutoBackups() as $backup) {
            $createdAt = new Time($backup->getCreatedAt());

            if ($createdAt > $startTime) {
                $sessionBackups[] = $backup;
            }
        }

        $needDeletedBackups = flow($sessionBackups)->skip($this->config->getAutoAmountMaxInSession());

        foreach ($needDeletedBackups as $backup) {
            $this->deleteBackup($backup);
        }

        $this->project->trigger('backup', $backup);

        return $backup;
    }

    public function makeDefaultMasterBackup()
    {
        if ($name = $this->config->getMasterDefault()) {
            $time = Time::millis();
            Logger::info("Make default master backup, name = $name");

            Ide::async(function () use ($name, $time) {
                $oldBackup = $this->getMasterBackup($name);

                if ($oldBackup) {
                    $this->deleteBackup($oldBackup);
                }

                $this->makeMasterBackup($name, 'Default Master Backup');

                Logger::info("Make default master backup, name = $name, is done, time = " . (Time::millis() - $time));
            });
            //$this->refreshRequest();
        }
    }

    /**
     * Создать мастер-копию.
     *
     * @param $name
     * @param $description
     * @return Backup
     */
    public function makeMasterBackup($name, $description)
    {
        ProjectSystem::saveOnlyRequired();

        $exporter = $this->project->makeExporter();
        $now = Time::now();
        $date = $now->toString('HHmmss-yyyyMMdd');
        $exporter->save($file = "{$this->getBackupDir()}/master/Backup-$date.zip");

        $config = new IdeConfiguration("$file.properties");
        $config->set('name', $name);
        $config->set('description', $description);
        $config->set('createdAt', Time::millis());
        $config->saveFile();

        $backup = new ZimbraLog();
        $backup->setName($name);
        $backup->setDescription($description);
        $backup->setCreatedAt(Time::millis());
        $backup->setFilename($file);
        $backup->setNew(false);
        $backup->setMaster(true);

        $this->saveBackupProperties($backup);

        $this->project->trigger('backup', $backup);

        return $backup;
    }

    public function makeMasterBackupRequest()
    {
        $input = new InputMessageBoxForm('backup.creation::Создание мастер-копии', 'backup.enter.name::Введите название копии');
        $input->setPattern(Regex::of('.+'), 'enter.name::Введите название');

        retry:
        if ($input->showDialog()) {
            $name = $input->getResult();

            $oldBackup = $this->getMasterBackup($name);

            if ($oldBackup) {
                if (!MessageBoxForm::confirm(_("backup.message.confirm.rewrite::Мастер-копия ({0}) уже существует, хотите перезаписать её?", $name))) {
                    goto retry;
                } else {
                    $this->deleteBackup($oldBackup);
                }
            }

            $this->makeMasterBackup($name, '');

            if (!$oldBackup) {
                Ide::toast(_("backup.master.creation.is.successful::Резервная мастер-копия ({0}) успешно создана.", $name));
            } else {
                Ide::toast(_("backup.master.updation.is.successful::Резервная мастер-копия ({0}) успешно обновлена.", $name));
            }

            $this->refreshRequest();
        }
    }

    public function makeAutoBackupRequest()
    {
        $backup = $this->makeAutoBackup();
        Ide::toast(_('backup.creation.is.successful::Резервная копия ({0}) успешно создана.', $backup->getName()));
        $this->refreshRequest();
    }

    /**
     * Удалить все бэкапы.
     * @param string $sub
     */
    public function clearBackup($sub = '')
    {
        fs::clean("{$this->getBackupDir()}/$sub");

        $this->project->trigger('clearBackup', $sub);
    }

    /**
     */
    public function clearMasterBackupRequest()
    {
        if (MessageBoxForm::confirmDelete('backup.master.copies::мастер-копии')) {
            $this->clearBackup('master');
            Ide::toast('backup.message.all.master.copies.are.deleted::Все мастер-копии были удалены.');

            $this->refreshRequest();
        }
    }

    /**
     */
    public function clearAutoBackupRequest()
    {
        if (MessageBoxForm::confirmDelete('backup.auto.copies::автоматические копии')) {
            $this->clearBackup('auto');
            Ide::toast('backup.message.all.auto.copies.are.deleted::Все автоматические копии были удалены.');

            $this->refreshRequest();
        }
    }

    /**
     * @param Backup $backup
     */
    public function deleteBackup(ZimbraLog $backup)
    {
        Logger::info("Delete backup $backup");

        fs::delete($backup->getFilename());
        fs::delete("{$backup->getFilename()}.properties");
    }

    /**
     * @param Backup $backup
     */
    public function deleteBackupRequest(ZimbraLog $backup)
    {
        if (MessageBoxForm::confirmDelete(_("backup.delete.target.copy::копию {0}", $backup->getName()))) {
            $this->deleteBackup($backup);

            Ide::toast(_("backup.message.copy.is.deleted.successful::Резервная копия {0} успешно удалена.", $backup->getName()));

            $this->refreshRequest();
        }
    }

    /**
     * Возвращает список автоматических бэкапов.
     * @return Backup[]
     */
    public function getAutoBackups()
    {
        $files = fs::scan("{$this->getBackupDir()}/auto/", [
            'namePattern' => '^Backup\\-.+', 'extensions' => 'zip', 'excludeDirs' => true
        ], 1);

        $result = [];

        foreach ($files as $file) {
            if (fs::isFile("$file.properties")) {
                $config = new IdeConfiguration("$file.properties");

                $backup = new ZimbraLog($config->toArray());
                $backup->setFilename($file);

                $result[] = $backup;
            }
        }

        $result = arr::sort($result, function (ZimbraLog $a, ZimbraLog $b) {
            return $a->getCreatedAt() > $b->getCreatedAt() ? -1 : 1;
        });

        return $result;
    }

    /**
     * @param $name
     * @return Backup|null
     */
    public function getMasterBackup($name)
    {
        foreach ($this->getMasterBackups() as $backup) {
            if (str::equalsIgnoreCase(str::trim($backup->getName()), str::trim($name))) {
                return $backup;
            }
        }

        return null;
    }

    /**
     * Возвращает список мастер-копий.
     *
     * @return Backup[]
     */
    public function getMasterBackups()
    {
        $files = fs::scan("{$this->getBackupDir()}/master/", [
            'namePattern' => '^Backup\\-.+', 'extensions' => 'zip', 'excludeDirs' => true
        ], 1);

        $result = [];

        foreach ($files as $file) {
            if (fs::isFile("$file.properties")) {
                $config = new IdeConfiguration("$file.properties");
                $backup = new ZimbraLog($config->toArray());
                $backup->setFilename($file);
                $backup->setMaster(true);

                $result[] = $backup;
            }
        }

        $result = arr::sort($result, function (ZimbraLog $a, ZimbraLog $b) {
            return $a->getCreatedAt() > $b->getCreatedAt() ? -1 : 1;
        });

        return $result;
    }

    /**
     * Восстановить проект из бэкапа.
     *
     * @param Backup $backup
     * @return Project|null
     */
    public function restoreFromBackup(ZimbraLog $backup)
    {
        $oldProperties = $this->config->getProperties();

        ProjectSystem::close(false);

        $importer = new ProjectImporter($backup->getFilename());
        $importer->extract($this->project->getRootDir());

        $file = fs::scan($this->project->getRootDir(), ['extensions' => ['dnproject']])[0];

        $project = ProjectSystem::open($file, true, true, false);

        if ($project) {
            // сохраняем старые настроки.
            /** @var ZimbraLogBehaviour $newBehaviour */
            if ($newBehaviour = $project->getBehaviour(ZimbraLogBehaviour::class)) {
                $newBehaviour->getConfig()->setProperties($oldProperties);
                $newBehaviour->getConfig()->save();
            }
        }

        return $project;
    }

    /**
     * Запрос на восстановление бэкапа.
     *
     * @param Backup $backup
     */
    public function restoreFromBackupRequest(ZimbraLog $backup)
    {
        if (MessageBoxForm::confirm('backup.message.confirm.to.restore::Вы уверены, что хотите восстановить выбранный бэкап?')) {
            Ide::get()->getMainForm()->showPreloader('message.waiting::Подождите ...');

            waitAsync(500, function () use ($backup) {
                $project = $this->restoreFromBackup($backup);

                if ($project) {
                    Ide::toast(_("backup.project.is.restored.from.backup.successful::Проект был успешно восстановлен из бэкапа - {0}", $backup->getName()));

                    uiLater(function () use ($project) {
                        /** @var ProjectEditor $projectEditor */
                        $projectEditor = FileSystem::open($project->getMainProjectFile());
                        $projectEditor->navigate(ZimbraLogProjectControlPane::class);

                        Ide::get()->getMainForm()->hidePreloader();
                    });
                }
            });
        }
    }

    public function doBackup()
    {
        static $lastStamp = 0;

        if ($lastStamp == 0) {
            $lastStamp = Time::millis();
            return;
        }

        // Если IDE не активна, не делаем бэкап.
        if (Ide::get()->isIdle() || !$this->config->isAutoIntervalTrigger()) {
            return;
        }

        $diff = Time::millis() - $lastStamp;

        if ($diff > $this->config->getAutoIntervalTriggerTime()) {
            $this->makeAutoBackup();

            $lastStamp = Time::millis();

            $this->refreshRequest();
        }
    }

    public function makeMenu()
    {
        Ide::get()->getMainForm()->defineMenuGroup('backup', 'backup.project.archive::Архив проекта');

        Ide::get()->registerCommand(new ZimbraLogCreateMasterCommand($this));
        $command = SimpleSingleCommand::makeForMenu('backup.project.copy.list::Список копий проекта', null, function () {
            /** @var ProjectEditor $projectEditor */
            if ($projectEditor = FileSystem::open($this->project->getMainProjectFile())) {
                $projectEditor->navigate(ZimbraLogControlPane::class);
            }
        });
        $command->setCategory('backup');
        Ide::get()->registerCommand($command);
        Ide::get()->registerCommand(new ZimbraLogSettingsMenuCommand($this));
        //Ide::get()->registerCommand(new BackupCleanMasterCommand($this));
    }

    /**
     * see PRIORITY_* constants
     * @return int
     */
    public function getPriority()
    {
        return self::PRIORITY_COMPONENT;
    }
}