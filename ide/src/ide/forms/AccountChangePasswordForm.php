<?php
namespace ide\forms;

use ide\account\api\ServiceResponse;
use ide\Ide;
use ide\ui\Notifications;
use php\gui\UXDialog;
use php\gui\UXPasswordField;

/**
 * Class AccountChangePasswordForm
 * @package ide\forms
 *
 * @property UXPasswordField $oldPasswordField
 * @property UXPasswordField $newPasswordField
 * @property UXPasswordField $checkPasswordField
 */
class AccountChangePasswordForm extends AbstractOnlineIdeForm
{
    protected function init()
    {
        parent::init();

        $this->icon->image = ico('flatKey48')->image;
    }

    /**
     * @event cancelButton.action
     */
    public function doCancel()
    {
        $this->hide();
    }

    /**
     * @event saveButton.action
     */
    public function doSave()
    {
        if ($this->checkPasswordField->text !== $this->newPasswordField->text) {
            UXDialog::show('Введите одинаковые пароли', 'ERROR');
            return;
        }

        $this->showPreloader();

        Ide::service()->account()->changePasswordAsync($this->oldPasswordField->text, $this->newPasswordField->text, function (ServiceResponse $response) {
            if ($response->isSuccess()) {
                Notifications::show('Смена пароля', 'Ваш пароль был успешно amended на новый');
                $this->hide();
            } else {
                switch ($response->message()) {
                    case 'NewPasswordRequired':
                        $message = 'Enter a new password.';
                        $this->showError($message, $this->newPasswordField);
                        break;
                    case 'NewPasswordInvalid':
                        $message = 'Please enter a valid password.';
                        $this->showError($message, $this->newPasswordField);
                        break;
                    case 'OldPasswordInvalid':
                        $message = 'The previous password was not correct.';
                        $this->showError($message, $this->oldPasswordField);
                        break;
                    default:
                        $message = $response->isFail() ? $response->message() : 'There was an unexpected problem. Cannot proceed.';
                        break;
                }

                Notifications::error('Change password', $message);
            }

            $this->hidePreloader();
        });
    }
}