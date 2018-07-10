<?php
namespace ide\forms;

use facade\Async;
use ide\account\api\ServiceResponse;
use ide\Ide;
use ide\ui\Notifications;
use php\gui\framework\AbstractForm;
use php\gui\layout\UXAnchorPane;
use php\gui\UXFileChooser;
use php\gui\UXImage;
use php\gui\UXImageArea;
use php\io\Stream;
use php\lib\str;

/**
 * Class AccountProfileEditForm
 * @package ide\forms
 *
 *
 * @property UXImageArea $avatarArea
 */
class AccountProfileEditForm extends AbstractForm
{
    /**
     * @var bool
     */
    protected $avatarChanged = false;

    /**
     * @var null|string
     */
    protected $avatarFile = null;

    /**
     * @var UXFileChooser
     */
    protected $dialog;

    protected function init()
    {
        parent::init();

        $dialog = new UXFileChooser();
        $dialog->extensionFilters = [
            ['description' => 'Images (jpg, png, gif)', 'extensions' => ['*.jpg', '*.jpeg', '*.png', '*.gif']]
        ];

        $this->dialog = $dialog;

        $this->icon->image = ico('flatAccount48')->image;

        $avatarArea = new UXImageArea();
        $avatarArea->centered = true;
        $avatarArea->stretch = true;
        $avatarArea->smartStretch = true;
        $avatarArea->proportional = true;

        UXAnchorPane::setAnchor($avatarArea, 0);

        $this->avatarPane->add($avatarArea);
        $avatarArea->toBack();

        $this->avatarArea = $avatarArea;
    }

    public function update()
    {
        $this->showPreloader();

        Ide::service()->account()->getAsync(function (ServiceResponse $response) {
            if ($response->isSuccess()) {
                $data = $response->result();

                Ide::service()->file()->loadImage($data['avatarId'], $this->avatarArea, 'noAvatar.jpg');

                $this->nameField->text = $data['login'];
                $this->emailLabel->text = $data['email'];

                $this->hidePreloader();
            } else {
                Notifications::showAccountUnavailable();
                $this->hide();
            }
        });
    }

    /**
     * @event show
     */
    public function doShow()
    {
        $this->update();
    }

    /**
     * @event saveButton.action
     */
    public function doSave()
    {
        $this->showPreloader('Сохранение данных');

        Async::parallel([
            function ($callback) {
                $my = $callback;
                $oldName = Ide::accountManager()->getAccountData()['login'];

                Ide::service()->account()->changeLoginAsync($this->nameField->text, function (ServiceResponse $response) use ($callback, $oldName) {
                    if ($response->isNotSuccess()) {
                        if ($response->isFail()) {
                            list($message, $param) = str::split($response->result(), ':');

                            switch ($message) {
                                case 'LoginNotUnique':
                                    Notifications::error('Error Saving', "That username is already in use.");
                                    break;
                                case "LoginMinLength":
                                    Notifications::error('Error Saving', 'The username you have chosen is too short - ' . $param);
                                    break;
                                case "LoginMaxLength":
                                    Notifications::error('Error Saving', 'The username you have chosen is too long - ' . $param);
                                    break;
                                default:
                                    Notifications::error('Error Saving', $message);
                                    break;
                            }

                        } else {
                            Notifications::show('Error Saving', 'Невозможно сохранить ваш псевдоним, возможно он введен некорректно!', 'ERROR');
                        }
                    }

                    if ($response->isSuccess() && $oldName != $this->nameField->text) {
                        Notifications::show('Name changed', 'Your name was successfully changed - ' . $this->nameField->text, 'SUCCESS');
                    }

                    $callback();
                });
            },
            function ($callback) {
                if ($this->avatarChanged) {
                    if ($this->avatarFile) {
                        Ide::service()->file()->uploadFileAsync($this->avatarFile, function (ServiceResponse $response) use ($callback) {
                            if ($response->isSuccess()) {
                                Ide::service()->account()->changeAvatarAsync($response->result('id'), function (ServiceResponse $response) use ($callback) {
                                    if ($response->isNotSuccess()) {
                                        if ($response->isFail()) {
                                            Notifications::error('Error сохранения', $response->message());
                                        } else {
                                            Notifications::show('Error сохранения', 'Невозможно сохранить ваш Avatar, попробуйте other.', 'ERROR');
                                        }
                                    }

                                    if ($response->isSuccess()) {
                                        $this->avatarChanged = false;
                                        Notifications::show('Avatar amended', 'Success!, ваш Avatar был успешно amended на other', 'SUCCESS');
                                    }

                                    $callback();
                                });
                            } else {
                                if ($response->isNotSuccess()) {
                                    Notifications::show('Error сохранения', 'Невозможно сохранить ваш Avatar, изображение не может быть загружено.', 'ERROR');
                                }

                                if ($response->isSuccess()) {
                                    $this->avatarChanged = false;
                                    Notifications::show('Avatar удален', 'Success!, Avatar вашего профиля был успешно удален', 'SUCCESS');
                                }

                                $callback();
                            }
                        });
                    } else {
                        Ide::service()->account()->deleteAvatarAsync(function (ServiceResponse $response) use ($callback) {
                            if ($response->isNotSuccess()) {
                                Notifications::show('Error сохранения', 'Невозможно удалить ваш Avatar, попробуйте в other раз.', 'ERROR');
                            }

                            $callback();
                        });
                    }
                } else {
                    $callback();
                }
            }
        ], function () {
            Ide::accountManager()->updateAccount();

            $this->hidePreloader();
            $this->update();
        });
    }

    /**
     * @event avatarClearButton.action
     */
    public function doAvatarClear()
    {
        $this->avatarArea->image = Ide::get()->getImage('noAvatar.jpg')->image;
        $this->avatarFile = null;

        $this->avatarChanged = true;
    }

    /**
     * @event avatarEditButton.action
     */
    public function doAvatarEdit()
    {
        if ($file = $this->dialog->execute()) {
            $this->avatarArea->image = new UXImage(Stream::of($file));
            $this->avatarFile = $file;

            $this->avatarChanged = true;
        }
    }

    /**
     * @event changePasswordButton.action
     */
    public function doChangePassword()
    {
        $dialog = new AccountChangePasswordForm();
        $dialog->showAndWait();
    }

    /**
     * @event cancelButton.action
     */
    public function doCancel()
    {
        $this->hide();
    }
}