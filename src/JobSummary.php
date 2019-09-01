<?php

namespace violinist\JobSummary;

class JobSummary
{

    protected $rawMessages;

    protected $errors = [];

    protected $outsideConstraint = [];

    protected $updates = [];

    protected $notUpdated = [];

    protected $prs = [];

    protected $blacklisted = [];

  /**
   * @return array
   */
  public function getPrs() {
    return $this->prs;
  }

  /**
   * @param array $prs
   */
  public function setPrs($prs) {
    $this->prs = $prs;
  }

  /**
   * @return array
   */
  public function getBlacklisted() {
    return $this->blacklisted;
  }

  /**
   * @param array $blacklisted
   */
  public function setBlacklisted($blacklisted) {
    $this->blacklisted = $blacklisted;
  }

  /**
   * @return array
   */
  public function getNotUpdated() {
    return $this->notUpdated;
  }

  /**
   * @param array $notUpdated
   */
  public function setNotUpdated($notUpdated) {
    $this->notUpdated = $notUpdated;
  }

  /**
   * @return array
   */
  public function getUpdates() {
    return $this->updates;
  }

  /**
   * @param array $updates
   */
  public function setUpdates($updates) {
    $this->updates = $updates;
  }

  /**
   * @return array
   */
  public function getOutsideConstraint() {
    return $this->outsideConstraint;
  }

  /**
   * @param array $outsideConstraint
   */
  public function setOutsideConstraint($outsideConstraint) {
    $this->outsideConstraint = $outsideConstraint;
  }

  /**
   * @return array
   */
  public function getErrors() {
    return $this->errors;
  }

  /**
   * @param array $errors
   */
  public function setErrors($errors) {
    $this->errors = $errors;
  }

  public function getUpdateForPackage($package) {
    foreach ($this->getUpdates() as $update) {
      if ($update->name == $package) {
        return $update;
      }
    }
    return FALSE;
  }

    public function __construct(array $messages)
    {
        $this->rawMessages = $messages;
        $this->analyzeMessages();
    }

    protected function analyzeMessages()
    {
        foreach ($this->rawMessages as $message) {
          if (is_string($message)) {
              // This message is probably a horrible error.
              $this->errors[] = $message;
              continue;
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
                }
                else {
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

