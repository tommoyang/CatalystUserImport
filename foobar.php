<?php
/**
 * Created by PhpStorm.
 * User: thomas
 * Date: 8/6/17
 * Time: 4:08 PM
 */

for ($i = 1; $i <= 100; $i++) {

    if ($i % 3 == 0 && $i % 5 == 0) {
        echo "foobar";
    } else if ($i % 3 == 0) {
        echo "foo";
    } else if ($i % 5 == 0) {
        echo "bar";
    } else {
        echo $i;
    }

    if ($i != 100) echo ", ";
}

echo "\n";