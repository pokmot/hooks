<?php
/**
 * Stop Exception
 *
 * This Exception is thrown when a Stop signal is required. For instance, when a Hook filter needs to prevent further
 * hooks being executed. This exception should always be caught and return flow control to the calling PHP script.
 */

namespace pokmot\Hooks;


class StopHookProcessing extends \Exception
{

  protected $return_value;


  public function __construct($message = "", $code = 0, \Exception $previous = null)
  {
    $this->return_value = $message;
    $message = '';
    parent::__construct($message, $code, $previous);
  }


  public function getReturnValue()
  {
    return $this->return_value;
  }
}
