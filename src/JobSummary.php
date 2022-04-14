<?php

namespace violinist\JobSummary;

class JobSummary
{

    const COMPOSER_2_ERROR = 'composer-2-error';

    const COMPOSER_2_REQUIRED_ERROR = 'composer-2-required-error';

    const COMPOSER_INSTALL_ERROR = 'composer-install-error';

    const TIMEFRAME_DISALLOWED_ERROR = 'timeframe-disallowed-error';

    const GIT_CLONE_ERROR = 'git-clone-error';

    const UPDATE_DATA_WRONG = 'update-data-wrong';

    protected $rawMessages;

    protected $errors = [];

    protected $outsideConstraint = [];

    protected $updates = [];

    protected $notUpdated = [];

    protected $prs = [];

    protected $blacklisted = [];

    protected $finishedSuccessFully = false;

    protected $composer2error = false;

    protected $installError = false;

    protected $runErrors = [];

    protected $isSkippedForTimeFrame = false;

    private $overrideSuccessMessage = false;

    private $updateOutput;

    public function getUpdateOutput()
    {
        return $this->updateOutput;
    }

  /**
   * @return \stdClass[]
   */
    public function getPrs()
    {
        return $this->prs;
    }

  /**
   * @param array $prs
   */
    public function setPrs($prs)
    {
        $this->prs = $prs;
    }

  /**
   * @return array
   */
    public function getBlacklisted()
    {
        return $this->blacklisted;
    }

  /**
   * @param array $blacklisted
   */
    public function setBlacklisted($blacklisted)
    {
        $this->blacklisted = $blacklisted;
    }

  /**
   * @return \stdClass[]
   */
    public function getNotUpdated()
    {
        return $this->notUpdated;
    }

  /**
   * @param array $notUpdated
   */
    public function setNotUpdated($notUpdated)
    {
        $this->notUpdated = $notUpdated;
    }

  /**
   * @return array
   */
    public function getUpdates()
    {
        return $this->updates;
    }

  /**
   * @param array $updates
   */
    public function setUpdates($updates)
    {
        $this->updates = $updates;
    }

  /**
   * @return array
   */
    public function getOutsideConstraint()
    {
        return $this->outsideConstraint;
    }

  /**
   * @param array $outsideConstraint
   */
    public function setOutsideConstraint($outsideConstraint)
    {
        $this->outsideConstraint = $outsideConstraint;
    }

  /**
   * @return array
   */
    public function getErrors()
    {
        return $this->errors;
    }

  /**
   * @param array $errors
   */
    public function setErrors($errors)
    {
        $this->errors = $errors;
    }

    public function getUpdateForPackage($package)
    {
        foreach ($this->getUpdates() as $update) {
            if ($update->name == $package) {
                return $update;
            }
        }
        return false;
    }

    public function __construct(array $messages)
    {
        $this->rawMessages = $messages;
        $this->analyzeMessages();
    }

    public function getRawMessages() : array
    {
        return $this->rawMessages;
    }

    public function didFinishWithSuccess()
    {
        return $this->finishedSuccessFully;
    }

    public function skippedForTimeFrame()
    {
      return $this->isSkippedForTimeFrame;
    }

    public function getErrorTypes()
    {
        return $this->runErrors;
    }

    public function getErrorType()
    {
        if ($this->composer2error) {
            return self::COMPOSER_2_ERROR;
        }
        return false;
    }

    protected function analyzeMessages()
    {
        foreach ($this->rawMessages as $message) {
            if (is_string($message)) {
                // This message is probably a horrible error.
                $this->errors[] = $message;
                continue;
            }
            if (!empty($message->message) && !empty($message->context->data) && $message->type === 'command') {
                $flattened = array_map(function ($item) {
                    return implode('', $item);
                }, $message->context->data);
                $interested_in_this = implode("\n", $flattened);
                $this->updateOutput = $interested_in_this;
            }
            if (!empty($message->message) && preg_match('/Current hour is inside timeframe disallowed/', $message->message)) {
                $this->isSkippedForTimeFrame = true;
            }
            if (!empty($message->message) && preg_match('/Caught Exception: Problem with the execCommand git clone/', $message->message)) {
                $this->runErrors[] = self::GIT_CLONE_ERROR;
            }
            if (!empty($message->message) && preg_match('/Current hour is inside timeframe disallowed/', $message->message)) {
                $this->runErrors[] = self::TIMEFRAME_DISALLOWED_ERROR;
            }
            if (!empty($message->message) && preg_match('/require.*should not contain uppercase/', $message->message, $output_array)) {
                $this->composer2error = true;
                $this->runErrors[] = self::COMPOSER_2_ERROR;
            } else if (!empty($message->message) && preg_match('/found composer-plugin-api\[2.1.0\] but it does not match the constraint./', $message->message)) {
                $this->runErrors[] = self::COMPOSER_2_ERROR;
            }
            if (!empty($message->message) && preg_match('/Plugin installation failed \(Declaration of Symfony\\\Flex\\\ParallelDownloader/', $message->message)) {
                $this->runErrors[] = self::COMPOSER_2_REQUIRED_ERROR;
            }
            if (!empty($message->message) && preg_match('/Caught Exception: Composer install/', $message->message)) {
                $this->runErrors[] = self::COMPOSER_INSTALL_ERROR;
            }
            if (!empty($message->context->package) && $message->type == 'command' && !empty($message->context->type) && $message->context->type === 'exit_code_output') {
                // This means the package had an error upon update, and it was not
                // updated.
                $this->notUpdated[] = $message;
                continue;
            }
            if (!empty($message->message) && preg_match('/Update data was in wrong format or missing/', $message->message)) {
                $this->runErrors[] = self::UPDATE_DATA_WRONG;
                // Also flag it to indicate it was indeed not successful.
                $this->overrideSuccessMessage = true;
            }
            if (!empty($message->message) && $message->message === 'Cleaning up after update check.') {
                // For now, let's say that indicates this was finished with
                // success.
                if (!$this->overrideSuccessMessage) {
                    $this->finishedSuccessFully = true;
                }
            }
            switch ($message->type) {
                case 'unupdate':
                    $this->outsideConstraint[] = $message;
                    break;

                case 'notupdated':
                    $this->notUpdated[] = $message;
                    break;

                case 'update':
                    if (empty($message->context)) {
                        $this->updates = explode("\n", $message->message);
                      // Remove the ones that are empty.
                        foreach ($this->updates as $delta => $update) {
                            if (empty($update)) {
                                unset($this->updates[$delta]);
                            }
                        }
                    } else {
                        $this->updates = [];
                      // "Convert" to array.
                        foreach ($message->context->packages as $package) {
                            $this->updates[] = $package;
                        }
                    }
                    break;

                case 'pr_url':
                    if (!empty($message->context)) {
                        $this->prs[] = $message;
                    }
                    break;


                case 'pr_exists':
                    if (!empty($message->context)) {
                        $this->prs[] = $message;
                    }
                    break;

                case 'blacklisted':
                    if (!empty($message->context)) {
                        $this->blacklisted[] = $message;
                    }
                    break;

                case 'error':
                    if (!empty($message->context->package)) {
                        $this->errors[] = $message;
                    }
                    break;

                default:
                    break;
            }
        }
    }
}
