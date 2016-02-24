<?php
return array(
    /** Loggers attached to every command */
    "_" => function () {
        return array(
            new \Monolog\Handler\StreamHandler('logs/phpci.build.log', \Monolog\Logger::DEBUG),
        );
    }
);
