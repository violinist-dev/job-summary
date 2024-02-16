<?php

namespace violinist\JobSummary;

class JobSummary
{

    const COMPOSER_2_ERROR = 'composer-2-error';

    const COMPOSER_2_REQUIRED_ERROR = 'composer-2-required-error';

    const COMPOSER_INSTALL_ERROR = 'composer-install-error';

    const SCRIPT_FAILED_ERROR = 'composer-install-failed-script';

    const TIMEFRAME_DISALLOWED_ERROR = 'timeframe-disallowed-error';

    const GIT_CLONE_ERROR = 'git-clone-error';

    const UPDATE_DATA_WRONG = 'update-data-wrong';

    const AUTH_NEEDED = 'auth-needed';

    const PHP_NOT_SATISFIED = 'php-not-satisfied';

    const EXTENSION_ERROR = 'php-extension-missing';

    protected $rawMessages;

    protected $errors = [];

    protected $outsideConstraint = [];

    protected $updates = [];

    protected $notUpdated = [];

    protected $concurrentThrottled = [];

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

    public function getConcurrentThrottled() : array
    {
        return $this->concurrentThrottled;
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
        foreach ($this->rawMessages as $delta => $message) {
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
            if (!empty($message->message) && preg_match('/You must be using the interactive console to authenticate/', $message->message)) {
                 $this->runErrors[] = self::AUTH_NEEDED;
            }
            if (!empty($message->message) && preg_match('/Current hour is inside timeframe disallowed/', $message->message)) {
                $this->isSkippedForTimeFrame = true;
            }
            if (!empty($message->message) && preg_match('/Caught Exception: Problem with the execCommand git clone/', $message->message)) {
                $this->runErrors[] = self::GIT_CLONE_ERROR;
            } else if (!empty($message->message) && preg_match('/404 Project Not Found/', $message->message, $output_array)) {
                $this->runErrors[] = self::GIT_CLONE_ERROR;
            }
            if (!empty($message->message) && preg_match('/your [Pp][hH][pP] version \(\d+\.\d+\.\d+\) does not satisfy that requirement/', $message->message)) {
                // This could be the case if a package was attempted upgrade,
                // but the actual new require did was not satisfied. Does not
                // necessarily mean the project itself was not "PHP satisfied".
                if (empty($message->context->package)) {
                    // If the context has a package, then that means this was
                    // not the actual composer install command.
                    $this->runErrors[] = self::PHP_NOT_SATISFIED;
                }
            }
            if (!empty($message->message) && preg_match('/Current hour is inside timeframe disallowed/', $message->message)) {
                $this->runErrors[] = self::TIMEFRAME_DISALLOWED_ERROR;
            }
            if (!empty($message->message) && preg_match('/require.*should not contain uppercase/', $message->message, $output_array)) {
                $this->composer2error = true;
                $this->runErrors[] = self::COMPOSER_2_ERROR;
            } else if (!empty($message->message) && preg_match('/found composer-plugin-api\[2.1.0\] but it does not match the constraint./', $message->message)) {
                $this->runErrors[] = self::COMPOSER_2_ERROR;
            } else if (!empty($message->message) && preg_match('/exists as composer-plugin-api\[1\.\d\.\d\] but these/', $message->message)) {
                $this->runErrors[] = self::COMPOSER_2_REQUIRED_ERROR;
            }
            if (!empty($message->message) && preg_match('/Plugin installation failed \(Declaration of Symfony\\\Flex\\\ParallelDownloader/', $message->message)) {
                $this->runErrors[] = self::COMPOSER_2_REQUIRED_ERROR;
            }
            if (!empty($message->message) && preg_match('/To enable extensions/', $message->message)) {
                $this->runErrors[] = self::EXTENSION_ERROR;
            }
            if (!empty($message->message) && preg_match('/This package is not installable via Composer 1\.x/', $message->message)) {
                $this->runErrors[] = self::COMPOSER_2_REQUIRED_ERROR;
            }
            if (!empty($message->message) && preg_match('/Caught Exception: Composer install/', $message->message)) {
                $this->runErrors[] = self::COMPOSER_INSTALL_ERROR;
                // Let's have a look at the last message. Did that by any chance
                // include the generation of autoload files. Could be we have
                // installed but should disable scripts. Let's hint about that.
                $last_message = $this->rawMessages[$delta - 1];
                if (!empty($last_message->message) && preg_match('/Generating autoload files/', $last_message->message)) {
                    $this->runErrors[] = self::SCRIPT_FAILED_ERROR;
                }
            }
            if (!empty($message->context->package) && $message->type === 'command' && !empty($message->context->type) && $message->context->type === 'exit_code_output') {
                // This means the package had an error upon update, and it was not
                // updated.
                $this->notUpdated[] = $message;
                $this->errors[] = $message;
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
                case 'concurrent_throttled':
                    $this->concurrentThrottled[] = $message;
                    break;

                case 'unupdate':
                    // @todo(eiriksm): This is hopefully only needed within the
                    // short time until we actually can indicate these messages
                    // way better.
                    if (strpos($message->message, 'can not be updated to dependencies.') === false) {
                        $this->outsideConstraint[] = $message;
                    }
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
