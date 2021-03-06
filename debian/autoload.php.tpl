<?php

require_once '/usr/share/php/LC/Common/autoload.php';
require_once '/usr/share/php/Psr/Log/autoload.php';

// @codingStandardsIgnoreFile
// @codeCoverageIgnoreStart
// this is an autogenerated file - do not edit
spl_autoload_register(
    function($class) {
        static $classes = null;
        if ($classes === null) {
            $classes = array(
                ___CLASSLIST___
            );
        }
        $cn = strtolower($class);
        if (isset($classes[$cn])) {
            require ___BASEDIR___$classes[$cn];
        }
    },
    ___EXCEPTION___,
    ___PREPEND___
);
// @codeCoverageIgnoreEnd
