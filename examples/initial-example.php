<?php
/**
 * An initial example of Hooks
 */

use pokmot\Hooks\Hooks as Hooks;

include '../vendor/autoload.php';

Hooks::setDebug();
Hooks::addFilter('filtertest', 'filter');
Hooks::addFilter('filtertest', 'filter2');
Hooks::addFilter('filtertest', 'filter3');

Hooks::filter('filtertest', 123);

function filter($value)
{
  print "<b>filter</b>";
  var_dump($value);
  print "<hr>";

  return $value + 1;
}

function filter2($value)
{
  print "<b>filter2</b>";
  var_dump($value);
  print "Next filter (3) will not be executed as we throw new StopHookProcessing()";
  print "<hr>";

  throw new \pokmot\Hooks\StopHookProcessing();
}

function filter3($value)
{
  print "<b>filter3</b>";
  var_dump($value);
  print "<hr>";

  return $value + 1;
}



$Classes = new Classes();

Hooks::addFilter('test', 'abc');
Hooks::addFilter('test', 'App');
Hooks::addFilter('test', function($name) {
//  printf("<b>Closure Hello</b> %s\r\n", $name);
  return $name . ' !closure!';
});
Hooks::addEvent('test', array($Classes, 'normalHello'), 99);
Hooks::addFilter('test', array('Classes', 'staticHello'));
Hooks::addFilter('test', 'Classes::staticHello');

//Hooks::removeAllFilters('test', 1);

//Hooks::removeFilter('test', 'abc');
//Hooks::removeFilter('test', array('Classes', 'staticHello'));
//Hooks::removeFilter('test', array($Classes, 'normalHello'), 1);
//Hooks::removeFilter('test', 'Classes::staticHello');
print "<hr>";
var_dump(Hooks::hasFilter('test'));
var_dump(Hooks::hasFilter('test', array('Classes', 'staticHello')));
var_dump(Hooks::hasFilter('test', 'Classes::staticHello'));
var_dump(Hooks::hasFilter('test', 'xyz'));
var_dump(Hooks::hasFilter('test123'));

print Hooks::filter('test', 123) . "<hr>";
var_dump(Hooks::filter('testa', false));
print "<hr>";
print Hooks::getDebugPrintCallstack();


function App($value) {
  return $value . ' !mytest!';
}

class Classes {

  public static function staticHello($value) {
    $value .= ' !static!';
//    print "<br><b>staticHello</b><br>{$value}<br>";
//    var_dump($value);
    return $value;
  }


  function normalHello($value) {
    $value .= ' !normalxxxx!';
    print "<br><b>normalHello</b><br>{$value}<br>";
//    var_dump($value);
    return $value;
  }

}
