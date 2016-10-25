<?php

namespace pokmot\Hooks;


class Hooks
{

  const LOWEST_PRIORITY = 1000;
  const SYSTEM_PRIORITY = 100;
  const HIGHEST_PRIORITY = -1000;

  static protected $_ignoreNonexistentCallbacks = true;
  static protected $_filters = [];
  static protected $_filter_sorted = false;
  static protected $_debug = false;
  static protected $_debug_callstack = [];


  /**
   * @return bool
   */
  public static function getDebug()
  {
    return self::$_debug;
  }


  /**
   * @param bool $debug
   */
  public static function setDebug($debug = true)
  {
    self::$_debug = $debug;
  }


  /**
   *
   */
  public static function clear()
  {
    self::$_filters = [];
  }


  /**
   * Adds a filter to the chain
   *
   * @param string $tag
   * @param mixed $callback
   * @param int $priority
   * @return bool True if successfully added
   */
  public static function addFilter($tag, $callback, $priority = self::SYSTEM_PRIORITY)
  {
    return self::_addFilter($tag, $callback, $priority, true);
  }


  /**
   * Adds an event that can be triggered
   *
   * @param $tag
   * @param $callback
   * @param int $priority
   * @return bool
   */
  public static function addEvent($tag, $callback, $priority = self::SYSTEM_PRIORITY)
  {
    return self::_addFilter($tag, $callback, $priority, false);
  }


  /**
   * @param string $tag
   * @param mixed $callback
   * @param int $priority
   * @param bool $is_filter
   * @return bool
   */
  protected static function _addFilter($tag, $callback, $priority, $is_filter = true)
  {
    $priority = intval($priority);
    self::_normalizeCallbackName($callback);
    $index = self::_uniqueId($callback);
    if ($index != null) {
      // Reset sort if the tag priority doesn't already exist, otherwise keep existing 'sort flag'.
      self::$_filter_sorted[$tag] = isset(self::$_filters[$tag][$priority]) ? self::$_filter_sorted[$tag] : false;
      self::$_filters[$tag][$priority][$index] = [
        'callback' => $callback,
        'is_filter' => $is_filter,
      ];
    }

    return $index != null;
  }


  /**
   * Takes into account that ['Classes', 'hello'] is equivalent to 'Classes::hello)
   *
   * @param callable $callback
   */
  protected static function _normalizeCallbackName(&$callback)
  {
    if (is_string($callback) && strpos($callback, '::') !== false) {
      $callback = explode('::', $callback);
    }
  }


  /**
   * Generates a unique ID for the filter function, to ensure the function is stored only once for each priority.
   * Requires PHP 5.2.3 at minimum
   *
   * @param mixed $callback Name of the function, or link to object, or closure statement
   * @return string Unique index
   */
  protected static function _uniqueId($callback)
  {
    if (is_string($callback)) {
      return $callback;
    }
    if (is_object($callback)) {
      return spl_object_hash($callback);
    }
    if (is_array($callback) && count($callback) == 2) {
      if (is_object($callback[0])) {
        return spl_object_hash($callback[0]) . $callback[1];
      }
      if (is_string($callback[0])) {
        return $callback[0] . '::' . $callback[1];
      }
    }

    return null;
  }


  /**
   * Triggers a filter and returns a value
   *
   * @param string $tag
   * @param mixed $value Argument(s) for the filter.
   * @return mixed Returns the value from the filter, or the first argument passed to the filter call
   */
  public static function filter($tag, $value)
  {
    if (self::$_debug) {
      $backtrace = debug_backtrace();
      self::$_debug_callstack[] = [
        'file' => isset($backtrace[0]['file']) ? $backtrace[0]['file'] : null,
        'line' => isset($backtrace[0]['line']) ? $backtrace[0]['line'] : null,
        'function' => isset($backtrace[0]['function']) ? $backtrace[0]['function'] : null,
        'tag' => $tag,
        'tag_found' => isset(self::$_filters[$tag]),
      ];
    }
    if (!isset(self::$_filters[$tag])) {
      return $value;
    }

    return self::_apply(true, func_get_args());
  }


  /**
   * Triggers an Event, but unlike 'filter' it will NOT return a value
   *
   * @param $tag
   */
  public static function trigger($tag)
  {
    if (self::$_debug) {
      $backtrace = debug_backtrace();
      self::$_debug_callstack[] = [
        'file' => isset($backtrace[0]['file']) ? $backtrace[0]['file'] : null,
        'line' => isset($backtrace[0]['line']) ? $backtrace[0]['line'] : null,
        'function' => isset($backtrace[0]['function']) ? $backtrace[0]['function'] : null,
        'tag' => $tag,
        'tag_found' => isset(self::$_filters[$tag]),
      ];
    }
    if (isset(self::$_filters[$tag])) {
      self::_apply(false, func_get_args());
    }
  }


  /**
   * @param bool $is_filter
   * @param array $args
   * @return mixed
   */
  protected static function _apply($is_filter, $args)
  {
    $tag = array_shift($args);
    self::_sortPriority($tag);
    foreach (self::$_filters[$tag] as $functions) {
      try {
        foreach ($functions as $callback) {
          if (self::$_ignoreNonexistentCallbacks && !self::_callbackExists($callback['callback'])) {
            continue;
          }
          if ($is_filter && $callback['is_filter']) {
            $value = call_user_func_array($callback['callback'], $args);
            // Always pass the current $value through; meaning we chain the output of one to the input of the next one.
            $args[0] = $value;
          } else {
            call_user_func_array($callback['callback'], $args);
          }
        }
      } catch (StopHookProcessing $e) {
        // No action - but stop processing
        $args[0] = $e->getReturnValue();
      }
    }

    if ($is_filter) {
      return $args[0];
    } else {
      return null;
    }
  }


  /**
   * Sorts the filters in priority order
   *
   * @param $tag
   */
  protected static function _sortPriority($tag)
  {
    if (!empty(self::$_filters[$tag]) && !self::$_filter_sorted[$tag]) {
      ksort(self::$_filters[$tag]);
      self::$_filter_sorted[$tag] = true;
    }
  }


  protected static function _callbackExists($callback)
  {
    if (is_object($callback)) {
      return true;
    }
    if (is_string($callback) && function_exists($callback)) {
      return true;
    }
    if (is_string($callback) && strpos($callback, '::') !== false) {
      $callback = explode('::', $callback);
    }
    if (is_array($callback) && count($callback) == 2) {
      if ((is_object($callback[0]) || is_string($callback[0])) && method_exists(
          $callback[0],
          $callback[1]
        )
      ) {
        return true;
      }
    }

    return false;
  }


  public static function hasEvent($tag, $callback = false)
  {
    return self::hasFilter($tag, $callback);
  }


  /**
   * Checks if a filter exists, or a specific callback within the filter, regardless of priority
   * Returns the lowest priority the callback is running as. Does not return multiple priorities
   * if the callback is set to run multiple times at different priority levels
   *
   * @param string $tag
   * @param bool|string $callback
   * @return bool|int Returns false or the numbered priority
   */
  public static function hasFilter($tag, $callback = false)
  {
    if ($callback !== false) {
      self::_normalizeCallbackName($callback);
    }

    $tag_exists = !empty(self::$_filters[$tag]);
    if ($callback === false || $tag_exists === false) {
      return $tag_exists;
    }
    self::_sortPriority($tag); // Must ensure they are in sorted priority to return the lowest priority of the callback
    $index = self::_uniqueId($callback);
    if ($index) {
      foreach (array_keys(self::$_filters[$tag]) as $priority) {
        if (isset(self::$_filters[$tag][$priority][$index])) {
          return $priority;
        }
      }
    }

    return false;
  }


  public static function removeEvent($tag, $callback, $priority = self::SYSTEM_PRIORITY)
  {
    return self::removeFilter($tag, $callback, $priority);
  }


  /**
   * Removes a filter/action
   *
   * @param string $tag
   * @param $callback
   * @param int $priority
   * @return bool True if filter has been found and removed; false otherwise
   */
  public static function removeFilter($tag, $callback, $priority = self::SYSTEM_PRIORITY)
  {
    $priority = intval($priority);
    self::_normalizeCallbackName($callback);
    $index = self::_uniqueId($callback);
    if (isset(self::$_filters[$tag][$priority][$index])) {
      unset(self::$_filters[$tag][$priority][$index]);
      self::$_filter_sorted[$tag] = false;

      return true;
    }

    return false;
  }


  public static function removeAllEvents($tag, $priority = null)
  {
    return self::removeAllFilters($tag, $priority);
  }


  /**
   * Removes all filters for a specific tag. Optionally only for a specific priority
   *
   * @param string $tag
   * @param int $priority
   * @return bool Always returns true
   */
  public static function removeAllFilters($tag, $priority = null)
  {
    if (isset(self::$_filters[$tag])) {
      if (is_null($priority)) {
        unset(self::$_filters[$tag]);
        unset(self::$_filter_sorted[$tag]);
      } else {
        $priority = intval($priority);
        if (isset(self::$_filters[$tag][$priority])) {
          unset(self::$_filters[$tag][$priority]);
        }
        self::$_filter_sorted[$tag] = false;
      }
    }

    return true;
  }


  public static function getignoreNonexistentCallbacks()
  {
    return self::$_ignoreNonexistentCallbacks;
  }


  public static function setignoreNonexistentCallbacks($ignoreNonexistentCallbacks = true)
  {
    self::$_ignoreNonexistentCallbacks = $ignoreNonexistentCallbacks;
  }


  /**
   * Adds a list of events in one simple call
   *
   * Usage:
   *   $hooks->addEvents(array('app.pre' => array($this, 'event'))); // Simple, without priority
   *   $hooks->addEvents(array('app.pre2' => 'my_static_method::event')); // Simple, without priority
   *   $hooks->addEvents(array('app.pre3' => array(array($this, 'event'), 95))); // With priority
   *   $hooks->addEvents(array('app.pre4' => array('my_static_method::event', 95))); // With priority
   *
   * @param array $events
   */

  public static function addEvents(array $events)
  {
    self::_addFilters($events, false);
  }


  /**
   * Adds a list of filters in one simple call
   *
   * Usage:
   *   $hooks->addFilters(array('app.pre' => array($this, 'event'))); // Simple, without priority
   *   $hooks->addFilters(array('app.pre2' => 'my_static_method::event')); // Simple, without priority
   *   $hooks->addFilters(array('app.pre3' => array(array($this, 'event'), 95))); // With priority
   *   $hooks->addFilters(array('app.pre4' => array('my_static_method::event', 95))); // With priority
   *
   * @param array $filters
   */

  public static function addFilters(array $filters)
  {
    self::_addFilters($filters, true);
  }


  protected static function _addFilters(array $filters, $is_filter)
  {
    foreach ($filters as $tag => $params) {
      $priority = static::SYSTEM_PRIORITY; // Default priority
      if (is_string($params)) {
        $callback = $params;
      }
      if (is_array($params)) {
        if (is_array($params[0]) || is_string($params[0])) {
          $callback = $params[0];
          $priority = isset($params[1]) ? intval($params[1]) : $priority;
        }
        if (is_object($params[0])) {
          $callback = $params;
        }
      }
      if (isset($callback)) {
        self::_addFilter($tag, $callback, $priority, $is_filter);
      }
    }
  }


  /**
   * @return array
   */
  public static function getDebugCallstack()
  {
    return self::$_debug_callstack;
  }


  /**
   * @return string
   */
  public static function getDebugPrintCallstack()
  {
    $output = '';
    foreach (self::$_debug_callstack as $call) {
      $output .= "{$call['function']} '{$call['tag']}' in {$call['file']} ({$call['line']})" . ($call['tag_found'] ? '' : ' - not active') . "<br>\n";
    }

    return $output;
  }


  public static function debugList()
  {
    print "<table>";
    if (is_array(self::$_filters)) {
      foreach (self::$_filters as $key => $filter) {
        print "<tr>";
        print "<td colspan=2 style=\"font-weight: bold; font-size: 14px\"><br>{$key}</td>";
        print "</tr>";
        ksort($filter);
        foreach ($filter as $priority => $callback) {
          print "<tr>";
          print "<td style='padding-right:15px; vertical-align: top'>{$priority}</td>";
          print "<td><table style=\"padding: 0; margin: 0\">";
          foreach ($callback as $identifier => $details) {
            print "<tr>";
            print "<td style=\"width: 65px\">";
            if ($details['is_filter']) {
              print "FILTER&nbsp;&nbsp;";
            } else {
              print "EVENT&nbsp;&nbsp;";
            }
            print "</td>";
            print "<td>";
            if (is_string($details['callback'])) {
              print $details['callback'];
            } elseif (is_object($details['callback'])) {
              print "CLOSURE";
            } elseif (is_array($details['callback'])) {
              if (is_object($details['callback'][0]) && is_string($details['callback'][1])) {
                print get_class($details['callback'][0]) . '->' . $details['callback'][1];
              } elseif (is_string($details['callback'][0]) && is_string($details['callback'][1])) {
                print $identifier;
              } else {
                var_dump($details);
              }
            }
            print "</td>";
            print "</tr>";
          }
          print "</table></td>";
        }
        print "</tr>";
      }
      print "</table>";
    }
  }

}
