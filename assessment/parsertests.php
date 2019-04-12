<?php

require("../init.php");
require('mathparser.php');

if ($myrights < 100) {
  exit;
}

$tests = [
 ['log(100)', [], 2],
 ['ln(e^3)', [], 3],
 ['log_3(9)', [], 2],
 ['1/2 + 4/8', [], 1],
 ['1+2*3', [], 7],
 ['8/4/2', [], 1],
 ['2^3^2', [], 512],
 ['2*3^2', [], 18],
 ['8sin(pi/2)/4', [], 2],
 ['4arcsin(sqrt(2)/2)/pi', [], 1],
 ['root(3)(8) + root3(8)', [], 4],
 ['sqrt4+1', [], 3],
 ['sqrt4x', ['x'=>10], 20],
 ['sin^2(pi/4)', [], 1/2],
 ['log_x(9)', ['x'=>3], 2],
 ['llog(l)', ['l'=>100], 200]
];


foreach ($tests as $test) {
  $p = new MathParser(implode(',', array_keys($test[1])));
  $p->parse($test[0]);
  $out = $p->evaluate($test[1]);
  if (abs($out - $test[2]) > 1e-6) {
    echo "Test failed on {$test[0]}: $out vs {$test[2]}<br>";
  }
}
echo "Done";
