<?php
/**
 * PHPCI - Continuous Integration for PHP
 * @copyright    Copyright 2014, Block 8 Limited.
 * @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
 * @link         https://www.phptesting.org/
 */

namespace PHPCI\Plugin;

use PHPCI\Builder;
use PHPCI\Model\Build;
use Nack\Monolog\Handler\GitterImHandler;
use Monolog\Logger;

/**
 * Slack Plugin
 * @author       Stephen Ball <phpci@stephen.rebelinblue.com>
 * @package      PHPCI
 * @subpackage   Plugins
 */
class GitterNotify implements \PHPCI\Plugin
{
    private $phpci;
    private $build;
    private $token;
    private $room;
    private $message;
    private $show_status = true;
    private $logger;

    /**
     * Set up the plugin, configure options, etc.
     * @param Builder $phpci
     * @param Build $build
     * @param array $options
     * @throws \Exception
     */
    public function __construct(Builder $phpci, Build $build, array $options = array())
    {
        $this->phpci = $phpci;
        $this->build = $build;

        if (is_array($options)) {

            if (isset($options['message'])) {
                $this->message = $options['message'];
            } else {



                $this->message = "%PROJECT_TITLE% Build #%BUILD% Result {{RESULT_STATUS}} \n";
                $this->message .= "Push from %COMMIT_EMAIL% \n";
                $this->message .= "on branch %BRANCH% \n";
                $this->message .= "\n\n";
                $this->message .= file_get_contents($this->build->getBuildPath() . '/build/reports/coverage.txt');
            }

            if (!isset($options['token'])) {
                throw new \Exception('Token is a required parameter');
            }

            $this->token = $options['token'];

            if (!isset($options['room'])) {
                throw new \Exception('Room must be specified with Gitter plugin');
            }

            $this->room = $options['room'];
            $this->show_status = isset($options['show_status']) ? (bool)$options['show_status'] : true;

        } else {
            throw new \Exception('Gitter notify plugin requires room and token options to function');
        }


        $logger = new Logger('phpci.gitter.buildLogger');
        $logger->pushHandler(
            new GitterImHandler($this->token, $this->room, Logger::INFO)
        );

        $this->logger = $logger;

    }

    /**
     * Run the Gitter plugin.
     *
     * @return bool
     */
    public function execute()
    {
        $body = $this->phpci->interpolate($this->message);

        $successfulBuild = $this->build->isSuccessful();
        $logLevel = $successfulBuild ? Logger::INFO : Logger::ERROR;
        $this->logger->log($logLevel, $body);

        return true;
    }
}
